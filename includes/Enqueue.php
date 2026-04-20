<?php

namespace CodeConfig\IGD;

use CodeConfig\IGD\App\Accounts;
use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\Singleton;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Enqueue {
    use Singleton;
    /**
     * Initialize hooks
     *
     * @return void
     */
    private function doHooks() : void {
        add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueue'] );
        add_action( 'wp_enqueue_scripts', [$this, 'frontendEnqueue'] );
    }

    /**
     * Register a script with default parameters
     *
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @param array $deps Script dependencies
     * @param string $ver Script version
     * @param bool $in_footer Load in footer
     * @return void
     */
    protected function register_script(
        string $handle,
        string $src,
        array $deps = [],
        string $ver = CCPIGD_VERSION,
        bool $in_footer = true
    ) : void {
        wp_register_script(
            $handle,
            $src,
            $deps,
            $ver,
            $in_footer
        );
    }

    /**
     * Register a style with default parameters
     *
     * @param string $handle Style handle
     * @param string $src Style source URL
     * @param array $deps Style dependencies
     * @param string $ver Style version
     * @param bool $rtl Support RTL
     * @return void
     */
    protected function register_style(
        string $handle,
        string $src,
        array $deps = [],
        string $ver = CCPIGD_VERSION,
        bool $rtl = false
    ) : void {
        wp_register_style(
            $handle,
            $src,
            $deps,
            $ver
        );
        if ( $rtl ) {
            wp_style_add_data( $handle, 'rtl', 'replace' );
        }
    }

    private function style( string $handle, array $deps = [], $args = [] ) {
        $_args = [
            'ver'       => CCPIGD_VERSION,
            'folder'    => "css",
            'in_footer' => false,
            'type'      => 'enqueue',
            'nesting'   => false,
            'priority'  => 10,
        ];
        $args = wp_parse_args( $args, $_args );
        if ( $args['nesting'] ) {
            $args['folder'] = "css/{$handle}";
        }
        $filePath = CCPIGD_ASSETS . "/{$args['folder']}/{$handle}.css";
        if ( $args['type'] === 'enqueue' ) {
            wp_enqueue_style(
                "ccpigd-{$handle}",
                $filePath,
                $deps,
                $args['ver']
            );
        } elseif ( $args['type'] === 'register' ) {
            wp_register_style(
                "ccpigd-{$handle}",
                $filePath,
                $deps,
                $args['ver']
            );
        } else {
            Notices::getInstance()->add( [
                'type'        => 'error',
                'title'       => "Unknown style type.",
                'description' => "Unknown style type '{$args['type']}' provided for handle '{$handle}'.",
            ] );
        }
    }

    /**
     * Registers a style for later use.
     *
     * @param string $handle The style handle.
     * @param array $deps Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
     * @param array $args Optional. Additional arguments for registering the style. Default empty array.
     */
    private function r_style( string $handle, array $deps = [], $args = [] ) {
        $args['type'] = 'register';
        $this->style( $handle, $deps, $args );
    }

    /**
     * Enqueue a script or register it for later use.
     *
     * @param string $handle Script handle
     * @param array $deps Script dependencies
     * @param array $args {
     *                    Optional. Additional args for the script.
     *
     * @type string $ver Script version. Default is CCPIGD_VERSION.
     * @type string $folder Folder to look for the script in. Default is 'js'.
     * @type bool $in_footer Load script in footer. Default is true.
     * @type string $type Type of enqueue action. 'enqueue' or 'register'. Default is 'enqueue'.
     *              }
     * @return void
     * @uses wp_enqueue_script()
     * @uses wp_register_script()
     */
    private function script( string $handle, array $deps = [], $args = [] ) : void {
        $_args = [
            'ver'       => CCPIGD_VERSION,
            'folder'    => "js",
            'in_footer' => true,
            'type'      => 'enqueue',
            'priority'  => 10,
        ];
        $defaultDeps = [];
        $deps = wp_parse_args( $deps, $defaultDeps );
        $args = wp_parse_args( $args, $_args );
        $assetsPath = CCPIGD_PATH . "assets/{$args['folder']}/{$handle}.asset.php";
        if ( file_exists( $assetsPath ) ) {
            $assets = (include $assetsPath);
            if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' ) {
                $args['ver'] = $assets['version'];
            }
            $deps = wp_parse_args( $deps, $assets['dependencies'] );
        }
        $filePath = CCPIGD_ASSETS . "/{$args['folder']}/{$handle}.js";
        if ( $args['type'] === 'enqueue' ) {
            wp_enqueue_script(
                "ccpigd-" . $handle,
                $filePath,
                $deps,
                $args['ver'],
                $args['in_footer']
            );
        } elseif ( $args['type'] === 'register' ) {
            wp_register_script(
                "ccpigd-" . $handle,
                $filePath,
                $deps,
                $args['ver'],
                $args['in_footer']
            );
        } else {
            Notices::getInstance()->add( [
                'type'        => 'error',
                'title'       => "Unknown script type.",
                'description' => "Unknown script type '{$args['type']}' provided for handle '{$handle}'.",
            ] );
        }
    }

    /**
     * Registers a script for later use.
     *
     * @param string $handle The script handle.
     * @param array $deps Optional. An array of registered script handles this script depends on. Default empty array.
     * @param array $args Optional. Additional arguments for registering the script. Default empty array.
     * @return void
     * @uses self::script()
     * @uses wp_register_script()
     */
    private function r_script( string $handle, array $deps = [], $args = [] ) {
        $args['type'] = 'register';
        $this->script( $handle, $deps, $args );
    }

    /**
     * Registers a plugin's scripts and styles for later use.
     *
     * @param string $handle The handle of the plugin script/style.
     * @param array $deps Optional. An array of registered handles this script/style depends on. Default empty array.
     * @param array $args Optional. Additional arguments for registering the script/style. Default empty array.
     * @uses self::r_script()
     * @uses self::r_style()
     */
    private function r_plugins( string $handle, array $deps = [], $args = [] ) {
        $this->r_script( $handle, ['jquery'], [
            'folder' => 'plugins',
        ] );
    }

    private array $admin_hooks = [
        'toplevel_page_integration-google-drive',
        'toplevel_page_ninja-forms',
        'toplevel_page_fluent_forms',
        'toplevel_page_formidable',
        'post.php',
        'post-new.php',
        'site-editor.php',
        'upload.php'
    ];

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function adminEnqueue( string $hook ) : void {
        $this->common_scripts( $hook );
        if ( in_array( $hook, $this->admin_hooks, true ) ) {
            $this->script( 'admin', [
                'ccpigd-shared',
                'wp-plupload',
                'ccpigd-file-selector',
                'ccpigd-module-builder',
                'ccpigd-ccplayer',
                'ccpigd-swiper'
            ] );
        }
        $this->style( 'admin', [] );
    }

    /**
     * Check if current page is Elementor editor
     *
     * @return bool
     */
    private function is_elementor_editor() : bool {
        if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
            return true;
        }
        if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\\Elementor\\Plugin' ) ) {
            return \Elementor\Plugin::$instance->editor->is_edit_mode();
        }
        return false;
    }

    /**
     * Enqueue common scripts and styles
     *
     * @param string $hook Current page hook
     * @param string $context Context (admin or frontend)
     * @return void
     */
    public function common_scripts( string $hook, string $context = 'admin' ) : void {
        $this->r_style( 'admin', [] );
        $this->r_script( 'runtime' );
        $this->r_script( 'vendors', ['ccpigd-runtime'] );
        $this->r_script( 'common', ['wp-util', 'ccpigd-vendors', 'ccpigd-popup/gallery'] );
        $sharedDeps = apply_filters(
            'ccpigd_shared_script_dependencies',
            ['ccpigd-common'],
            $hook,
            $context
        );
        $this->r_script( 'shared', $sharedDeps );
        $this->r_script( 'module-builder' );
        $this->r_script( 'file-selector' );
        $this->r_script( 'integrations', ['ccpigd-file-selector', 'ccpigd-module-builder', 'ccpigd-shared'] );
        if ( current_user_can( 'manage_options' ) && $this->is_elementor_editor() ) {
            $this->script( 'integrations', ['ccpigd-file-selector', 'ccpigd-module-builder', 'ccpigd-shared'] );
        }
        $this->style( 'common', ['ccpigd-popup/gallery'], [
            'priority' => 5,
        ] );
        $this->r_script( 'popup/gallery', [], [
            'folder' => 'plugins',
        ] );
        $this->r_style( 'popup/gallery', [], [
            'folder' => 'plugins',
        ] );
        $this->r_script( 'ccplayer', [], [
            'folder' => 'plugins/ccplayer',
        ] );
        $this->r_style( 'ccplayer', [], [
            'folder' => 'plugins/ccplayer',
        ] );
        $this->r_script( 'swiper', [], [
            'folder' => 'plugins/swiper',
        ] );
        $this->r_style( 'swiper', [], [
            'folder' => 'plugins/swiper',
        ] );
        $this->r_script( 'media-library', [
            'media-models',
            'media-views',
            'media-editor',
            'wp-mediaelement',
            'ccpigd-shared',
            'ccpigd-file-selector'
        ] );
        wp_localize_script( 'ccpigd-common', 'ccpigd', $this->getLocalizeData( $hook, $context ) );
        // =======================================================================
        // Register Module Scripts
        // -----------------------------------------------------------------------
        // This section handles the registration of all JavaScript files related
        // to individual modules used in the project.
        // =======================================================================
        $commonDeps = ['ccpigd-common'];
        $args = [
            'folder' => 'js/modules',
        ];
        $this->r_script( 'file-browser', array_merge( $commonDeps, ['wp-plupload'] ), $args );
        $this->r_script( 'file-uploader', array_merge( $commonDeps, ['wp-plupload'] ), $args );
        $this->r_script( 'file-list', $commonDeps, $args );
        $this->r_script( 'gallery', $commonDeps, $args );
        $this->r_script( 'embed-documents', $commonDeps, $args );
        $this->r_script( 'media-player', array_merge( $commonDeps, ['ccpigd-ccplayer'] ), $args );
        $this->r_script( 'search-box', $commonDeps, $args );
        $this->r_script( 'slider-carousel', array_merge( $commonDeps, ['ccpigd-swiper', 'ccpigd-popup/gallery'] ), $args );
        // =======================================================================
        // Register Module Styles
        // -----------------------------------------------------------------------
        // This section handles the registration of all CSS files related
        // to individual modules used in the project.
        // =======================================================================
        $moduleStyleArgs = [
            'folder' => 'css/frontend',
        ];
        $commonDeps = [];
        $this->r_style( 'file-browser', array_merge( $commonDeps, ['wp-components'] ), $moduleStyleArgs );
        $this->r_style( 'file-uploader', array_merge( $commonDeps, ['wp-components'] ), $moduleStyleArgs );
        $this->r_style( 'file-list', $commonDeps, $moduleStyleArgs );
        $this->r_style( 'gallery', array_merge( $commonDeps ), $moduleStyleArgs );
        $this->r_style( 'embed-documents', $commonDeps, $moduleStyleArgs );
        $this->r_style( 'media-player', array_merge( $commonDeps, ['ccpigd-ccplayer'] ), $moduleStyleArgs );
        $this->r_style( 'search-box', array_merge( $commonDeps ), $moduleStyleArgs );
        $this->r_style( 'slider-carousel', array_merge( $commonDeps, ['ccpigd-swiper', 'ccpigd-popup/gallery'] ), $moduleStyleArgs );
    }

    /**
     * Adds a script or style to be enqueued if not already enqueued.
     *
     * @param string $handle Handle of the script/style
     * @param string $type Type: 'js' or 'css'
     * @param array $deps Dependencies
     * @param array $args Extra arguments
     *
     * @return void
     */
    public function add(
        string $handle,
        string $type,
        array $deps = [],
        array $args = [],
        bool $register = false
    ) : void {
        $fullHandle = "ccpigd-{$handle}";
        if ( $type === 'js' ) {
            if ( empty( $deps ) && wp_script_is( $fullHandle, 'enqueued' ) ) {
                return;
            }
            global $wp_scripts;
            $previousDeps = ( isset( $wp_scripts->registered[$fullHandle] ) ? $wp_scripts->registered[$fullHandle]->deps : [] );
            $deps = array_unique( array_merge( $previousDeps, $deps ) );
            wp_deregister_script( $fullHandle );
            if ( $register ) {
                $this->r_script( $handle, $deps, $args );
            } else {
                $this->script( $handle, $deps, $args );
            }
        } elseif ( $type === 'css' ) {
            if ( empty( $deps ) && wp_style_is( $fullHandle, 'enqueued' ) ) {
                return;
            }
            global $wp_styles;
            $previousDeps = ( isset( $wp_styles->registered[$fullHandle] ) ? $wp_styles->registered[$fullHandle]->deps : [] );
            $deps = array_unique( array_merge( $previousDeps, $deps ) );
            wp_deregister_style( $fullHandle );
            if ( !wp_style_is( $fullHandle ) ) {
                if ( $register ) {
                    $this->r_style( $handle, $deps, $args );
                } else {
                    $this->style( $handle, $deps, $args );
                }
            }
        }
    }

    /**
     * Enqueue frontend scripts and styles
     * * @param string $context Context (gallery, fileBrowser, etc.)
     * @return void
     */
    public function frontendEnqueue() : void {
        $this->common_scripts( '', 'frontend' );
    }

    /**
     * Get general localize data
     *
     * @param string $hook Current page hook
     * @param string $context Context (admin or frontend)
     * @return array
     */
    private function getLocalizeData( $hook = false, $script = 'admin' ) {
        $appearance = Helpers::getSettings( ['appearance'] );
        $data = [
            'ajaxUrl'         => esc_url( admin_url( 'admin-ajax.php' ) ),
            'restUrl'         => esc_url( rest_url( 'ccpigd/v1/' ) ),
            'isPlain'         => get_option( 'permalink_structure' ) === '',
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'siteUrl'         => site_url(),
            'pluginUrl'       => CCPIGD_URL,
            'isAdmin'         => is_admin(),
            'isLoggedIn'      => is_user_logged_in(),
            'version'         => CCPIGD_VERSION,
            'pluginName'      => CCPIGD_NAME,
            'assetUrl'        => CCPIGD_ASSETS,
            'textDomain'      => CCPIGD_TEXTDOMAIN,
            'settings'        => [
                "appearance" => $appearance['appearance'] ?? ['#00ac47'],
            ],
            'extensionGroups' => ccpigdGetExtensionGroups(),
            'moduleList'      => ccpigdGetModules(),
        ];
        $data['isPro'] = false;
        if ( is_user_logged_in() ) {
            $data['currentUser'] = [
                'id'    => get_current_user_id(),
                'name'  => wp_get_current_user()->display_name,
                'roles' => wp_get_current_user()->roles ?? ['subscriber'],
                'can'   => [
                    'manageSettings'      => ccpigdHasUserAccessPage( 'settings' ),
                    'manageFileBrowser'   => ccpigdHasUserAccessPage( 'file_browser' ),
                    'manageModuleBuilder' => ccpigdHasUserAccessPage( 'module_builder' ),
                    'manageMediaLibrary'  => ccpigdHasUserAccessPage( 'media_library' ),
                    'hasFullAccess'       => ccpigdGetCurrentUserAccess() === true,
                ],
            ];
        }
        if ( ccpigdGetCurrentUserAccess() ) {
            $data['upgradeUrl'] = ccpigd_fs()->get_upgrade_url();
            $accounts = Accounts::getInstance()->getAccounts();
            if ( is_wp_error( $accounts ) ) {
                $accounts = [];
            }
            $data['accounts'] = array_values( $accounts ?? [] );
            $data['settings'] = Helpers::getSettings();
            $data['defaultSettings'] = ccpigdGetDefaultSettings();
            $data['adminPageUrl'] = admin_url( 'admin.php?page=integration-google-drive' );
            $data['redirectUri'] = CCPIGD_MANUAL_REDIRECT_URI;
            $data['userAccess'] = ccpigdGetCurrentUserAccess();
        }
        $data = apply_filters(
            'ccpigd_localize_data',
            $data,
            $script,
            $hook
        );
        return $data;
    }

}
