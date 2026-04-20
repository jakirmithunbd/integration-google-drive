<?php

namespace CodeConfig\IGD\Updates;

defined('ABSPATH') || exit;

use CodeConfig\IGD\Utils\Singleton;

abstract class Updater
{
    use Singleton;

    abstract public function run_update();

    /**
     * Check whether a column exists in a table.
     *
     * Requires WordPress 6.2+ for %i support.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    protected function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        $cacheKey   = "ccpigd_column_exists_{$table}_{$column}";
        $cacheGroup = 'ccpigd_schema';

        $cached = wp_cache_get($cacheKey, $cacheGroup);

        if ($cached !== false) {
            return (bool) $cached;
        }

        $query = $wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE %s",
            $table,
            $column
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_results($query);

        $exists = !empty($result);

        wp_cache_set($cacheKey, $exists, $cacheGroup, HOUR_IN_SECONDS);

        return $exists;
    }
}
