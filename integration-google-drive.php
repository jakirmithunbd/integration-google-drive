<?php

namespace CodeConfig;

use CodeConfig\IGD\Autoload;
use CodeConfig\IGD\CodeConfig;
use Freemius;
/**
 * Plugin Name:       Integration for Google Drive
 * Plugin URI:        https://codeconfig.dev/integration-google-drive/
 * Description:       Seamlessly integrate Google Drive with WordPress to embed, share, play, and download documents and media files directly from Google Drive.
 * Version:           1.4.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            CodeConfig
 * Author URI:        https://codeconfig.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       integration-google-drive
 * Domain Path:       /languages/
 */
if ( !defined( 'ABSPATH' ) ) {
    exit( 'Direct access to this file is not allowed.' );
}
if ( function_exists( __NAMESPACE__ . '\\ccpigd_fs' ) ) {
    ccpigd_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( __NAMESPACE__ . '\\ccpigd_fs' ) ) {
        function ccpigd_fs() {
            global $ccpigd_fs;
            if ( isset( $ccpigd_fs ) && $ccpigd_fs instanceof Freemius ) {
                return $ccpigd_fs;
            }
            if ( !class_exists( 'Freemius' ) ) {
                require_once dirname( __FILE__ ) . '/freemius/start.php';
            }
            $ccpigd_fs = fs_dynamic_init( [
                'id'               => '17204',
                'slug'             => 'integration-google-drive',
                'type'             => 'plugin',
                'public_key'       => 'pk_63fb19d3ca70dcc101ed3e6d80497',
                'premium_suffix'   => 'PRO',
                'is_premium'       => false,
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'is_org_compliant' => true,
                'trial'            => [
                    'days'               => 7,
                    'is_require_payment' => true,
                ],
                'menu'             => [
                    'slug' => 'integration-google-drive',
                ],
                'is_live'          => true,
            ] );
            return $ccpigd_fs;
        }

        ccpigd_fs();
        do_action( 'ccpigd_loaded' );
    }
    define( 'CCPIGD_FILE', __FILE__ );
    require_once plugin_dir_path( CCPIGD_FILE ) . 'core/config.php';
    require_once plugin_dir_path( CCPIGD_FILE ) . 'core/functions.php';
    $ccpigd_include_files = ['Autoload'];
    foreach ( $ccpigd_include_files as $ccpigd_include_file ) {
        $file_path = CCPIGD_INCLUDES . '/' . $ccpigd_include_file . '.php';
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
    Autoload::register();
    CodeConfig::getInstance();
}