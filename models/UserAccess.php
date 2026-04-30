<?php

namespace CodeConfig\IGD\Models;

use CodeConfig\IGD\Utils\Singleton;

use function in_array;
use function is_array;

class UserAccess extends BaseModel
{
    use Singleton;
    private $table;
    /**
     * @var \wpdb
     * @access protected
     */
    protected $wpdb;

    private function __construct()
    {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = "{$wpdb->prefix}integration_google_drive_user_access";
    }

    // Create a new record
    public function create($type, $value, $folders = null, $pages = [])
    {
        global $wpdb;

        $isExists = $this->get_by($type, $value);

        if ($isExists) {
            $data = [
                'folders'    => maybe_serialize($folders),
                'pages'      => maybe_serialize($pages),
                'updatedAt'  => current_time('mysql', 1),
            ];

            $cacheKey = "ccpigd_user_access_{$type}_{$value}";
            wp_cache_delete($cacheKey, 'ccpigd_user_access');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $this->table,
                $data,
                ['id' => $isExists['id']],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return $isExists['id'];
        }

        $data = [
            'type'       => $type,
            'value'      => $value,
            'folders'    => maybe_serialize($folders),
            'pages'      => maybe_serialize($pages),
            'createdAt'  => current_time('mysql', 1),
            'updatedAt'  => current_time('mysql', 1),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%s'];
        $this->wpdb->insert($this->table, $data, $format);

        $cacheKey = "ccpigd_user_access_{$type}_{$value}";
        wp_cache_delete($cacheKey, 'ccpigd_user_access');

        return $this->wpdb->insert_id;
    }

    public function get($id)
    {
        global $wpdb;

        $cacheKey  = "ccpigd_user_access_id_{$id}";
        $cached    = wp_cache_get($cacheKey, 'ccpigd_user_access');
        if ($cached !== false) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $this->table, $id), ARRAY_A);

        if ($result) {
            if (isset($result['folders'])) {
                $result['folders'] = maybe_unserialize($result['folders']);
            }

            if (isset($result['pages'])) {
                $result['pages'] = maybe_unserialize($result['pages']);
            }
        }

        wp_cache_set($cacheKey, $result, 'ccpigd_user_access');

        return $result;
    }

    public function get_by($type, $value)
    {

        $valueString = is_array($value) ? implode(', ', $value) : $value;

        $cacheKey = "ccpigd_user_access_{$type}_{$valueString}";
        $cached   = wp_cache_get($cacheKey, 'ccpigd_user_access');

        if ($cached !== false) {
            return $cached === "no-data-found" ? false : $cached;
        }

        global $wpdb;

        if (empty($type) || empty($value)) {
            return false;
        }

        $allowTypes  = ['user', 'role'];

        if (!in_array($type, $allowTypes, true)) {
            return false;
        }

        if (empty($value)) {
            return false;
        }

        $query  = $wpdb->prepare("SELECT * FROM %i WHERE type = %s", $this->table, $type);

        if (is_array($value)) {
            $placeholders = implode(',', array_fill(0, count($value), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $query       .= $wpdb->prepare(" AND value IN ($placeholders)", $value);
        } else {
            $query       .= $wpdb->prepare(" AND value = %s", $value);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_row($query, ARRAY_A);

        if (!empty($result)) {
            if (isset($result['folders'])) {
                $result['folders'] = maybe_unserialize($result['folders']);
            }

            if (isset($result['pages'])) {
                $result['pages'] = maybe_unserialize($result['pages']);
            }
            wp_cache_set($cacheKey, $result, 'ccpigd_user_access');

            return $result;
        }

        wp_cache_add($cacheKey, "no-data-found", 'ccpigd_user_access');

        return false;

    }


    // Read folders by user name and role
    public function getAccessData($username, $roles)
    {
        $userData = $this->get_by('user', $username);

        if (!empty($userData)) {
            return $userData;
        }

        $roleData = $this->get_by('role', $roles);

        if (!empty($roleData)) {
            return $roleData;
        }

        return [];
    }


    public function getAll()
    {
        $cacheKey = "ccpigd_user_access_all";
        $cached   = wp_cache_get($cacheKey, 'ccpigd_user_access');
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY createdAt DESC", $this->table), ARRAY_A);

        foreach ($results as &$record) {
            $record['folders'] = maybe_unserialize($record['folders']);
            $record['pages']   = maybe_unserialize($record['pages']);
        }

        wp_cache_set($cacheKey, $results, 'ccpigd_user_access');

        return $results;
    }


    /**
     * Update a record by ID
     *
     * @param int $id
     * @param string $type
     * @param string $value
     * @param array $folders
     * @param array $pages
     * @return int|false Number of rows updated or false on failure
     */
    public function updateRecord($id, $type, $value, $folders = [], $pages = [])
    {
        if (empty($id)) {
            return false;
        }

        $data = [
            'type'       => $type,
            'value'      => $value,
            'folders'    => maybe_serialize($folders),
            'pages'      => maybe_serialize($pages),
            'updatedAt'  => current_time('mysql', 1),
        ];
        $where = ['id' => $id];

        $format       = ['%s', '%s', '%s', '%s', '%s'];
        $where_format = ['%d'];

        $result = $this->wpdb->update($this->table, $data, $where, $format, $where_format);

        $cacheKey = "ccpigd_user_access_{$type}_{$value}";
        wp_cache_flush_group('ccpigd_user_access');

        return $result;
    }

    /**
     * Delete a record by ID
     *
     * @param int $id
     * @return int|false Number of rows deleted or false on failure
     */
    public function deleteRecord($id)
    {
        $where        = ['id' => $id];
        $where_format = ['%d'];

        $result = $this->wpdb->delete($this->table, $where, $where_format);

        wp_cache_flush_group('ccpigd_user_access');

        return $result;
    }
}
