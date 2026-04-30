<?php

namespace CodeConfig\IGD;

if (! defined('ABSPATH')) {
    exit;
}

class Cache
{
    /**
     * WordPress filesystem instance
     *
     * @var \WP_Filesystem_Base
     */
    protected $fs;

    /**
     * Upload base directory
     *
     * @var string
     */
    protected $baseDir;

    /**
     * Upload base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Allowed size folders
     *
     * @var array
     */
    protected $allowedSizes = [ 'md',  'lg', 'xl', '4xl', '5xl' ];

    public function __construct()
    {
        $this->initFilesystem();
    }

    /**
     * Initialize WP_Filesystem and paths
     */
    protected function initFilesystem()
    {
        if (! function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        global $wp_filesystem;
        $this->fs = $wp_filesystem;

        if (! $this->fs) {
            error_log('[Cache] WP_Filesystem initialization failed.');

            return;
        }

        $uploadDir     = wp_upload_dir();
        $this->baseDir = trailingslashit($uploadDir['basedir']) . 'ccpigd/';
        $this->baseUrl = trailingslashit($uploadDir['baseurl']) . 'ccpigd/';

        // Ensure base directory exists
        if (! $this->fs->exists($this->baseDir)) {
            if (! $this->fs->mkdir($this->baseDir, FS_CHMOD_DIR)) {
                error_log('[Cache] Failed to create base cache directory.');
            }
        }
    }

    /**
     * Validate size folder
     *
     * @param string $size
     * @return bool
     */
    protected function isValidSize($size)
    {
        return in_array($size, $this->allowedSizes, true);
    }

    /**
     * Save file to cache
     *
     * @param string $fileData Binary data
     * @param string $filename File name without extension
     * @param string $size Size category
     * @param string $ext File extension
     * @return bool
     */
    public function saveFile($fileData, $filename, $size, $ext = 'webp')
    {

        if (! $this->fs || ! $this->isValidSize($size)) {
            error_log('[Cache] Invalid filesystem or size while saving file.');

            return false;
        }

        try {
            $sizeDir = trailingslashit($this->baseDir . $size);

            if (! $this->fs->exists($sizeDir)) {
                if (! $this->fs->mkdir($sizeDir, FS_CHMOD_DIR)) {
                    error_log("[Cache] Failed to create size directory: {$sizeDir}");

                    return false;
                }
            }

            $filePath = $sizeDir . sanitize_file_name($filename) . '.' . $ext;

            if ($this->fs->exists($filePath)) {
                return true;
            }

            if (! $this->fs->put_contents($filePath, $fileData, FS_CHMOD_FILE)) {
                error_log("[Cache] Failed to write file: {$filePath}");

                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log('[Cache] saveFile exception: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if file exists in cache
     *
     * @param string $filename
     * @param string $size
     * @return bool
     */
    public function existsFile($filename, $size, $ext = 'webp')
    {
        if (! $this->fs || ! $this->isValidSize($size)) {
            return false;
        }

        $filePath = trailingslashit($this->baseDir . $size)
            . sanitize_file_name($filename) . '.' . $ext;

        return $this->fs->exists($filePath);
    }

    /**
     * Get cached file public URL
     *
     * @param string $filename
     * @param string $size
     * @param string $ext
     * @return string|false
     */
    public function getFileUrl($filename, $size, $ext = 'webp')
    {
        if (! $this->fs || ! $this->isValidSize($size)) {
            return false;
        }

        $filename = sanitize_file_name($filename) . '.' . $ext;
        $filePath = trailingslashit($this->baseDir . $size) . $filename;

        if (! $this->fs->exists($filePath)) {
            return false;
        }

        return trailingslashit($this->baseUrl . $size) . $filename;
    }

    public function getFileRaw($filename, $size, $ext = 'webp')
    {
        if (! $this->fs || ! $this->isValidSize($size)) {
            return false;
        }

        $filePath = trailingslashit($this->baseDir . $size)
            . sanitize_file_name($filename) . '.' . $ext;

        if (! $this->fs->exists($filePath)) {
            return false;
        }

        return $this->fs->get_contents($filePath);
    }

    /**
     * Delete a cached file
     *
     * @param string $filename
     * @param string $size
     * @param string $ext
     * @return bool
     */
    public function deleteFile($filename, $size, $ext = 'webp')
    {
        if (! $this->fs || ! $this->isValidSize($size)) {
            return false;
        }

        $filePath = trailingslashit($this->baseDir . $size)
            . sanitize_file_name($filename) . '.' . $ext;

        if ($this->fs->exists($filePath)) {
            if (! $this->fs->delete($filePath)) {
                error_log("[Cache] Failed to delete file: {$filePath}");

                return false;
            }
        }

        return true;
    }

    /**
     * Clear entire cache directory
     *
     * @return bool
     */
    public function clearCache()
    {
        if (! $this->fs) {
            return false;
        }

        if ($this->fs->exists($this->baseDir)) {
            if (! $this->fs->delete($this->baseDir, true)) {
                error_log('[Cache] Failed to clear cache directory.');

                return false;
            }
        }

        return true;
    }
}
