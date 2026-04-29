import defaultConfig from "@wordpress/scripts/config/webpack.config.js";
import CopyPlugin from "copy-webpack-plugin";
import TerserPlugin from "terser-webpack-plugin";
import { globSync } from "glob";
import path, { dirname } from "path";
import { fileURLToPath } from "url";
import fs from "fs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// ======================================================================
// Detect all entry files dynamically
// ======================================================================
const jsEntries = Object.fromEntries(
    globSync("./source/**/*.js", {
        ignore: [
            "./source/utils/**/*.js",
            "./source/hooks/**/*.js",
            "./source/assets/plugins/**/*.js",
            "./source/gutenberg/Blocks/**/*.js",
            "./source/media-library/context/**/*.js",
        ],
        cwd: __dirname,
    }).map((file) => [
        path.basename(file, ".js"),
        path.resolve(__dirname, file),
    ]),
);

// ======================================================================
// Detect Gutenberg Block entries separately
// ======================================================================
const blocksDir = path.resolve(process.cwd(), "source/gutenberg/Blocks");

const blockFolders = fs.readdirSync(blocksDir).filter((folder) => {
    const fullPath = path.join(blocksDir, folder);
    return fs.statSync(fullPath).isDirectory() && folder !== "utils";
});

const blockEntries = blockFolders.reduce((acc, block) => {
    acc[`blocks/${block}/index`] = path.resolve(blocksDir, block, "index.js");
    return acc;
}, {});

const copyBlockPatterns = blockFolders.map((block) => ({
    from: path.resolve(blocksDir, block, "block.json"),
    to: path.join(`blocks/${block}`, "block.json"),
}));

function AddDepsPlugin(extraDeps = [], matchPattern = false) {
    function getAssetFiles(dir) {
        if (!fs.existsSync(dir)) {
            return [];
        }

        return fs.readdirSync(dir).flatMap((file) => {
            const fullPath = path.join(dir, file);
            const stat = fs.statSync(fullPath);

            if (stat.isDirectory()) return getAssetFiles(fullPath);

            if (matchPattern) {
                return fullPath.match(matchPattern) && fullPath.endsWith(".asset.php")
                    ? [fullPath]
                    : [];
            }

            return file.endsWith(".asset.php") ? [fullPath] : [];
        });
    }

    return {
        apply(compiler) {
            compiler.hooks.done.tap("CustomDepsPlugin", () => {
                const outputPath = compiler.options.output.path;

                const assetFiles = getAssetFiles(outputPath);
                console.log(
                    "🔧 Checking asset files for dependency updates...",
                    assetFiles,
                );
                assetFiles.forEach((fullPath) => {
                    let content = fs.readFileSync(fullPath, "utf8");

                    const match = content.match(
                        /'dependencies' => array\((.*?)\)/s,
                    );
                    if (match) {
                        let deps = match[1]
                            .split(",")
                            .map((d) => d.trim().replace(/'| /g, ""))
                            .filter(Boolean);

                        // Add extra dependencies if missing
                        extraDeps.forEach((dep) => {
                            if (!deps.includes(dep)) deps.push(dep);
                        });

                        const newDeps = deps.map((d) => `'${d}'`).join(", ");
                        content = content.replace(
                            /'dependencies' => array\((.*?)\)/s,
                            `'dependencies' => array(${newDeps})`,
                        );

                        fs.writeFileSync(fullPath, content, "utf8");
                        console.log(`🔧 Updated dependencies in: ${fullPath}`);
                    }
                });
            });
        },
    };
}

// ======================================================================
// Plugin: Prepend semicolon to avoid JS concatenation issues
// ======================================================================
class PrependSemicolonPlugin {
    apply(compiler) {
        compiler.hooks.emit.tapAsync(
            "PrependSemicolonPlugin",
            (compilation, callback) => {
                try {
                    for (const filename in compilation.assets) {
                        if (
                            filename.endsWith(".js") &&
                            !filename.includes("runtime")
                        ) {
                            const asset = compilation.assets[filename];
                            const original = asset.source();

                            // Only prepend if not already starting with semicolon
                            if (!original.trim().startsWith(";")) {
                                const updated = ";" + original;
                                compilation.assets[filename] = {
                                    source: () => updated,
                                    size: () => updated.length,
                                    map: () => (asset.map ? asset.map() : null), // Preserve source maps
                                };
                            }
                        }
                    }
                    callback();
                } catch (error) {
                    callback(error);
                }
            },
        );
    }
}

// ======================================================================
// Main Webpack Configuration for regular JS files
// ======================================================================
const mainConfig = {
    ...defaultConfig,

    mode: "production",

    entry: {
        ...jsEntries,
        ...blockEntries,
    },

    resolve: {
        extensions: [".ts", ".tsx", ".js", ".jsx"],

        alias: {
            "~": path.resolve(__dirname, "source"),
        },
    },

    externals: {
        sweetalert2: "window.Swal",
        plupload: "window.plupload",
    },

    output: {
        path: path.resolve(__dirname, "assets/js"),
        filename: (pathData) => {
            const name = pathData.chunk.name;
            const match = name.match(/^([a-zA-Z0-9_-]+)--(.+)$/);

            if (match) {
                const [, prefix, filename] = match;
                return `${prefix}s/${filename}.js`;
            }

            return `${name}.js`;
        },
        chunkFilename: "chunks/[name].chunk.js",
        clean: true,
    },

    optimization: {
        minimizer: [
            new TerserPlugin({
                terserOptions: {
                    compress: {
                        join_vars: false,
                    },
                    format: {
                        comments: (node, comment) => {
                            return comment.value.includes('fs_premium_only');
                        },
                    },
                },
                extractComments: false,
            }),
        ],
        splitChunks: {
            chunks: "all",
            cacheGroups: {
                vendors: {
                    test: /[\\/]node_modules[\\/]/,
                    name: "vendors",
                    chunks: "all",
                    priority: -10,
                    enforce: true,
                },
                shared: {
                    name: "shared",
                    minChunks: 2,
                    priority: -20,
                    reuseExistingChunk: true,
                },
            },
        },
        runtimeChunk: "single",
    },

    plugins: [
        ...(defaultConfig.plugins || []),
        new PrependSemicolonPlugin(),
        new AddDepsPlugin(
            ["ccpigd-shared"],
            /blocks\/(file-list|file-browser|gallery|embed-documents|media-player|search-box|slider|shortcode)\//,
        ),
        new CopyPlugin({
            patterns: [
                {
                    from: path.resolve(__dirname, "source/assets/images"),
                    to: path.resolve(__dirname, "assets/images"),
                },
                {
                    from: path.resolve(__dirname, "source/assets/fonts"),
                    to: path.resolve(__dirname, "assets/fonts"),
                },
                {
                    from: path.resolve(__dirname, "source/assets/plugins"),
                    to: path.resolve(__dirname, "assets/plugins"),
                },
                ...copyBlockPatterns,
            ],
            options: {
                concurrency: 100,
            },
        }),
    ],
};

export default [mainConfig];
