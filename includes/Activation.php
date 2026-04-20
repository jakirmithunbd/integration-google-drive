<?php

namespace CodeConfig\IGD;

use CodeConfig\IGD\Utils\Helpers;

defined('ABSPATH') || exit('No direct script access allowed');

class Activation
{
    public static function init()
    {
        Helpers::checkPluginRequirements();
        $update = Update::getInstance();

        if ($update->maybeUpdate() === false) {
            self::setDefaultTable();
            self::setDefaultData();
            self::setDefaultSettings();
            self::setCustomCap();
            self::setRewriteRules();
        }
    }

    private static function setDefaultTable()
    {
        global $wpdb;
        $wpdb->hide_errors();
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $tables = ccpigdGetTablesDefinitions();

        foreach ($tables as $table) {
            dbDelta($table);
        }
    }

    private static function setDefaultData()
    {
        if (!get_option('ccpigd_version')) {
            update_option('ccpigd_version', CCPIGD_VERSION);
        }

        if (!get_option('ccpigd_install_time')) {
            update_option('ccpigd_install_time', current_time('mysql'));
        }

        if (!get_option('ccpigd_encryption_key')) {
            update_option('ccpigd_encryption_key', wp_generate_uuid4());
        }

        set_transient('ccpigd_rating_notice_interval', 'off', 10 * DAY_IN_SECONDS);
    }

    private static function setDefaultSettings()
    {
        $default_settings = get_option(CCPIGD_OPTIONS_NAME, false);

        if (! $default_settings) {

            $default_settings = [
                "integrations" => [
                    "activeIntegrations" => ['mediaLibrary'],
                    "mediaLibrary"       => [
                        "folders"         => [],
                        "mlHoverPreview"  => false,
                        "deleteCloudFile" => false
                    ]
                ],
            ];
            update_option(CCPIGD_OPTIONS_NAME, $default_settings);
        }
    }

    private static function setCustomCap()
    {
        $role = get_role('administrator');
        if (!empty($role)) {
            $role->add_cap(CCPIGD_ACCESS_CAP);
        }
    }

    /**
     * Sets up rewrite rules for the plugin.
     *
     * This function adds a rewrite rule that matches the following pattern:
     * ^ccpigd/([^/]+)/([^/]+)/([^/]+)\.([^/]+)$
     * The pattern matches the following example URL:
     * https://example.com/ccpigd/action/key/name.ext
     *
     * The matched groups are mapped to the following query parameters:
     * - action: $matches[1]
     * - key: $matches[2]
     * - name: $matches[3]
     * - ext: $matches[4]
     * The rewrite rule is added with a priority of 'top' to ensure it takes precedence over other rules.
     *
     * @since 1.2.0
     *
     * @return void
     */
    private static function setRewriteRules()
    {
        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)/([^/]+)\.([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]',
            'top'
        );

        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)/([^/]+)/([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]',
            'top'
        );

        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]',
            'top'
        );

        flush_rewrite_rules();
    }
}
