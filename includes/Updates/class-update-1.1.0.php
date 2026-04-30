<?php

namespace CodeConfig\IGD\Updates;

defined('ABSPATH') || exit;

class Update_1_1_0 extends Updater
{
    public const VERSION = '1.1.0';

    public function run_update()
    {
        try {
            $this->updateShortcodeTable();
            $this->updateFilesTable();
            $this->updateAccountsTable();
            $this->updateUserAccessTable();

            return self::VERSION;
        } catch (\Exception $e) {
            return new \WP_Error('update_failed', __('Update to version 1.1.0 failed: ', 'integration-google-drive') . $e->getMessage());
        }
    }

    protected function updateShortcodeTable()
    {
        global $wpdb;

        $table    = "{$wpdb->prefix}integration_google_drive_shortcodes";
        $oldTable = "{$wpdb->prefix}sync_google_drive_shortcodes";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %i", $oldTable)) === $oldTable) {
            $wpdb->query($wpdb->prepare("RENAME TABLE %i TO %i", $oldTable, $table));
        }

        if (!$this->columnExists($table, 'type')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD COLUMN `type` VARCHAR(20) NOT NULL AFTER `title`", $table));
        }

        if ($this->columnExists($table, 'status')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `status` VARCHAR(10) NOT NULL DEFAULT 'on'", $table));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $title = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM %i WHERE Field = 'title'", $table));
        if ($title && preg_match('/varchar\((\d+)\)/i', $title->Type, $matches)) {
            $length = (int) $matches[1];
            if ($length < 120) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `title` VARCHAR(120) DEFAULT NULL", $table));
            }
        }
    }

    protected function updateFilesTable()
    {
        global $wpdb;

        $table    = "{$wpdb->prefix}integration_google_drive_files";
        $oldTable = "{$wpdb->prefix}sync_google_drive_files";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %i", $oldTable)) === $oldTable) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("RENAME TABLE %i TO %i", $oldTable, $table));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `id` VARCHAR(120) NOT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE COLUMN `key` `fileKey` VARCHAR(120) NOT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `parentId` VARCHAR(120) DEFAULT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `accountId` VARCHAR(120) NOT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `extension` VARCHAR(60) DEFAULT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i DROP PRIMARY KEY, ADD PRIMARY KEY (`fileKey`)", $table));
    }

    protected function updateAccountsTable()
    {
        global $wpdb;

        $table    = "{$wpdb->prefix}integration_google_drive_accounts";
        $oldTable = "{$wpdb->prefix}sync_google_drive_accounts";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %i", $table)) !== $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("RENAME TABLE %i TO %i", $oldTable, $table));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `id` VARCHAR(120) NOT NULL", $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE COLUMN `key` `accountKey` VARCHAR(120) NOT NULL", $table));
    }

    protected function updateUserAccessTable()
    {
        global $wpdb;

        $table    = "{$wpdb->prefix}integration_google_drive_user_access";
        $oldTable = "{$wpdb->prefix}sync_google_drive_user_access";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %i", $table)) !== $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("RENAME TABLE %i TO %i", $oldTable, $table));
        }
    }
}
