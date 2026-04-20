<?php

namespace CodeConfig\IGD;

use CodeConfig\IGD\Utils\Helpers;

defined('ABSPATH') || exit('No direct script access allowed');

class Deactivation
{
    public static function init()
    {
        self::unscheduleCron();
        // Check uninstall settings before proceeding
        if (empty(Helpers::getSetting('advanced.deleteDataOnUninstall', false))) {
            return;
        }

        self::removeTables();
        self::removePluginData();
        self::removeCustomCapabilities();

        flush_rewrite_rules();
    }

    /**
     * Remove custom database tables
     */
    private static function removeTables()
    {
        global $wpdb;

        $tables = [
            'integration_google_drive_shortcodes',
            'integration_google_drive_user_access',
            'integration_google_drive_files',
            'integration_google_drive_accounts',
            'integration_google_drive_logs',
        ];

        foreach ($tables as $table) {
            $table = "{$wpdb->prefix}$table";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));
        }
    }

    /**
     * Remove plugin options and transients
     */
    private static function removePluginData()
    {
        $options = [
            'ccpigd_install_time',
            'ccpigd_encryption_key',
            'ccpigd_notice',
            'ccpigd_settings',
            'ccpigd_version',
        ];

        foreach ($options as $key) {
            delete_option($key);
        }

        // Delete all transients
        global $wpdb;

        $pattern1 = $wpdb->esc_like('_transient_ccpigd_') . '%';
        $pattern2 = $wpdb->esc_like('_transient_timeout_ccpigd_') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $pattern1,
            $pattern2
        ));

        // Delete all plugin attachments
        ccpigdDeleteAllAttachments();
    }

    /**
     * Remove custom capabilities from all users
     */
    private static function removeCustomCapabilities()
    {
        $users = get_users([
            'fields' => ['ID']
        ]);

        foreach ($users as $user) {
            $userObj = get_user_by('ID', $user->ID);
            if ($userObj) {
                $userObj->remove_cap(CCPIGD_ACCESS_CAP);
            }
        }
    }

    private static function unscheduleCron()
    {
        $timestamp = wp_next_scheduled('ccpigd_cron_fire');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ccpigd_cron_fire');
        }
    }
}
