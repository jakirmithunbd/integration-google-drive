<?php

namespace CodeConfig\IGD;

use CodeConfig\IGD\API\ApiRegistry;
use CodeConfig\IGD\Integrations\Forms\ContactForm7;
use function defined;
use function sprintf;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
use CodeConfig\IGD\Integrations\Blocks;
use CodeConfig\IGD\Integrations\ClassicEditor;
use CodeConfig\IGD\Integrations\Elementor;
use CodeConfig\IGD\Integrations\MediaLibrary;
use CodeConfig\IGD\Shortcode\Locations;
use CodeConfig\IGD\Utils\Singleton;
class CodeConfig {
    use Singleton;
    public function __construct() {
        $this->init();
    }

    private function init() {
        register_activation_hook( CCPIGD_FILE, [Activation::class, 'init'] );
        register_deactivation_hook( CCPIGD_FILE, [Deactivation::class, 'init'] );
        // Check for updates on every page load.
        Update::getInstance()->maybeUpdate();
        // Initialize the admin class.
        Content::getInstance();
        Admin::getInstance();
        Enqueue::getInstance();
        ApiRegistry::getInstance();
        Update::getInstance();
        Shortcode::getInstance();
        Locations::getInstance();
        MediaLibrary::getInstance();
        ClassicEditor::getInstance();
        Blocks::getInstance();
        Elementor::getInstance();
        ContactForm7::getInstance();
    }

    /**
     * Adds hooks to the WordPress hooks system.
     *
     * @return void
     */
    private function doHooks() {
        // Adds a link to the plugin's row meta in the WordPress plugin list.
        // The link points to the plugin's documentation page.
        add_filter(
            'plugin_row_meta',
            [$this, 'pluginRowMeta'],
            10,
            2
        );
        // Add a filter to modify the plugin action links.
        // This filter allows adding additional links to the plugin's entry in the plugins list.
        add_filter( 'plugin_action_links_' . plugin_basename( CCPIGD_FILE ), [$this, 'actionLinks'] );
        add_action( 'init', [$this, 'registerRewriteRules'] );
        add_filter( 'allowed_redirect_hosts', [$this, 'addAllowedRedirectHosts'] );
    }

    /**
     * Adds a link to the plugin's row meta in the WordPress plugin list.
     *
     * The link points to the plugin's documentation page.
     *
     * @param array $links The current links in the plugin row meta.
     * @param string $file The path to the plugin's main file.
     *
     * @return array The updated links in the plugin row meta.
     */
    public function pluginRowMeta( $links, $file ) {
        if ( $file == plugin_basename( CCPIGD_FILE ) ) {
            $links[] = sprintf( '<a target="_blank" href="%s">%s</a>', CCPIGD_DOCUMENTATION_URL, __( 'Docs & FAQs', 'integration-google-drive' ) );
        }
        return $links;
    }

    /**
     * Adds a settings link to the plugin's action links in the WordPress plugins list.
     *
     * This link points to the plugin's settings page in the WordPress admin dashboard.
     *
     * @param array $links The current action links for the plugin.
     *
     * @return array The updated action links for the plugin.
     */
    public function actionLinks( $links ) {
        $links[] = sprintf( '<a target="_blank" href="%s">%s</a>', admin_url( 'admin.php?page=integration-google-drive#/settings' ), __( 'Settings', 'integration-google-drive' ) );
        return $links;
    }

    /**
     * Registers rewrite rules for the plugin.
     *
     * Registers a rewrite rule that matches the following pattern:
     * ^ccpigd/([^/]+)/([^/]+)/([^/]+)\.([^/]+)$
     *
     * The pattern matches the following example URL:
     * https://example.com/ccpigd/action/key/name.ext
     *
     * The matched groups are mapped to the following query parameters:
     * - action: $matches[1]
     * - key: $matches[2]
     * - name: $matches[3]
     * - ext: $matches[4]
     * The rewrite rule is added with a priority of 'top' to ensure it takes precedence over other rules.
     * @since 1.2.0
     *
     * @return void
     */
    public function registerRewriteRules() {
        add_rewrite_rule( '^ccpigd/([^/]+)/([^/]+)/([^/]+)\\.([^/]+)$', 'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]', 'top' );
        add_rewrite_rule( '^ccpigd/([^/]+)/([^/]+)/([^/]+)/([^/]+)$', 'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]', 'top' );
        add_rewrite_rule( '^ccpigd/([^/]+)/([^/]+)$', 'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]', 'top' );
        if ( get_transient( 'ccpigd_rewrite_rules_error' ) ) {
            delete_transient( 'ccpigd_rewrite_rules_error' );
            flush_rewrite_rules();
        }
    }

    public function addAllowedRedirectHosts( $hosts ) {
        $hosts[] = 'drive.google.com';
        $hosts[] = 'docs.google.com';
        $hosts[] = 'sites.google.com';
        $hosts[] = 'script.google.com';
        $hosts[] = 'lh3.googleusercontent.com';
        $hosts[] = 'drive-thirdparty.googleusercontent.com';
        return $hosts;
    }

}
