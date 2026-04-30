<?php

namespace CodeConfig\IGD\Updates;

defined('ABSPATH') || exit;

class Update_1_3_0 extends Updater
{
    public const VERSION = '1.3.0';

    public function run_update()
    {
        try {
            $this->alterModuleTable();

            return self::VERSION;
        } catch (\Exception $e) {
            return new \WP_Error('update_failed', __('Update to version 1.3.0 failed: ', 'integration-google-drive') . $e->getMessage());
        }
    }

    private function alterModuleTable()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}integration_google_drive_shortcodes LIKE 'integration'");

        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("ALTER TABLE {$wpdb->prefix}integration_google_drive_shortcodes ADD COLUMN `integration` VARCHAR(60) DEFAULT NULL AFTER `type`;");
        }
    }
}
