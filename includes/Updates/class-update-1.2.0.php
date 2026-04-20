<?php

namespace CodeConfig\IGD\Updates;

defined('ABSPATH') || exit;

class Update_1_2_0 extends Updater
{
    public const VERSION = '1.2.0';

    public function run_update()
    {
        try {
            $this->addUserLogs();
            $this->updateDefaultSettings();
            add_action('init', [$this, 'setRewriteRules']);

            return self::VERSION;
        } catch (\Exception $e) {
            error_log('Error during update ' . self::VERSION . ': ' . $e->getMessage());
        }
    }

    /**
     * Add user logs table if it doesn't exist, or rename sync_google_drive_logs table to integration_google_drive_logs if it does.
     *
     * This function is used to ensure that the user logs table exists and has the correct name.
     *
     * If the sync_google_drive_logs table exists, it is renamed to integration_google_drive_logs.
     * If the integration_google_drive_logs table does not exist, it is created.
     */
    protected function addUserLogs()
    {

        $getLogsTable = ccpigdGetTablesDefinitions('logs');
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($getLogsTable);
    }

    /**
     * Sets up rewrite rules for the plugin.
     *
     * This function adds a rewrite rule for the plugin to handle downloads of files from Google Drive.
     * The rule matches URLs of the form `ccpigd/<action>/<key>/<name>.<ext>`.
     * The action, key, name and extension are passed as query variables to index.php.
     *
     * The rule is added with a priority of 'top' to ensure it takes precedence over other rules.
     * The rewrite rules are then flushed to ensure they are applied.
     */
    public function setRewriteRules()
    {
        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)/([^/]+)\.([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]',
            'top'
        );

        flush_rewrite_rules();
    }

    private function updateDefaultSettings()
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
        } else {
            $default_settings["integrations"]["activeIntegrations"] = ['mediaLibrary'];
            $default_settings["integrations"]["mediaLibrary"]       = [
                "folders"         => [],
                "mlHoverPreview"  => false,
                "deleteCloudFile" => false
            ];
            update_option(CCPIGD_OPTIONS_NAME, $default_settings);
        }
    }
}
