<?php

namespace CodeConfig\IGD\Models;

use CodeConfig\IGD\Utils\Singleton;
use WP_Error;

defined('ABSPATH') || exit('No direct script access allowed');

/**
 * Notice Model Class for Integration Google Drive Plugin
 *
 * This class extends BaseModel to provide notice/log management functionality
 * with full compatibility with the base model's CRUD operations and error handling.
 *
 * @package CodeConfig\IGD\Models
 * @since 1.0.0
 */
class Notices extends BaseModel
{
    use Singleton;

    /**
     * Allowed columns for ordering
     * @var array
     */
    private $allowedOrderColumns = ['id', 'createdAt', 'updatedAt', 'type', 'status', 'title'];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('integration_google_drive_logs');
    }

    /**
     * Get a single notice by ID
     *
     * @param int $id The notice ID
     * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N
     * @return object|array|null|WP_Error
     */
    public function get(int $id, $output = OBJECT)
    {
        global $wpdb;

        $cacheKey = "ccpigd_notice_{$id}";
        if (false !== ($cachedNotice = wp_cache_get($cacheKey, 'ccpigd_notices'))) {
            return $cachedNotice;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $notice = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $this->tableName, $id), $output);
        if ($notice) {
            wp_cache_set($cacheKey, $notice, 'ccpigd_notices');
        }

        return $notice;
    }

    /**
     * Get all notices
     *
     * @param array $args Optional arguments for pagination and filtering
     * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N
     * @return array|WP_Error
     */
    public function getAll(array $args = [], $output = ARRAY_A)
    {

        $page    = intval($args['page'])    ?? 1;
        $perPage = intval($args['perPage']);
        $offset  = ($page - 1) * $perPage;

        $status  = $args['status'] ?? 'all';

        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM %i WHERE 1=1", $this->tableName);

        if (!empty($status) && $status !== 'all') {
            $sql .= $wpdb->prepare(" AND status = %s", $status);
        }

        $sql .= $wpdb->prepare(" ORDER BY createdAt DESC LIMIT %d OFFSET %d", $perPage, $offset);

        $validOutputTypes = [OBJECT, ARRAY_A, ARRAY_N];

        if (!in_array($output, $validOutputTypes, true)) {
            return new WP_Error(400, __('Invalid output type specified.', 'integration-google-drive'));
        }

        $cacheKey = "ccpigd_notices_page_{$page}_perPage_{$perPage}_status_{$status}_output_{$output}";
        if (false !== ($cachedNotices = wp_cache_get($cacheKey, 'ccpigd_notices'))) {
            return $cachedNotices;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $notices = $wpdb->get_results($sql, $output);

        if (is_wp_error($notices)) {
            return $notices;
        }

        if (empty($notices)) {
            return [];
        }

        // Get total count for pagination

        $count       = $this->count();
        $unreadCount = $this->count(['status' => 'unread']);
        $hasMore     = $page < ceil($count / $perPage);

        $response = [
            'notices'     => array_values($notices),
            'hasMore'     => $hasMore,
            'total'       => $count,
            'currentPage' => $page
        ];

        if (!empty($unreadCount)) {
            $response['unreadCount'] = $unreadCount;
        }

        if ($hasMore) {
            $response['nextPage'] = $page + 1;
        }

        wp_cache_set($cacheKey, $response, 'ccpigd_notices');

        return $response;
    }


    /**
     * Add a new notice to the database.
     *
     * @param array $data {
     *                    An array of data to be inserted.
     *
     * @type string $moduleId    Module ID.
     * @type int $userId      User ID.
     * @type string $fileKey     File key.
     * @type string $fileName    File name.
     * @type string $page        Page.
     * @type array $data        Data.
     * @type string $type        Notice type.
     * @type string $title       Notice title.
     * @type string $status      Notice status. Default 'unread'.
     * @type string $description Notice description.
     *              }
     *
     * @return int|WP_Error The ID of the new notice on success, WP_Error on failure.
     */
    public function add(array $data)
    {
        if (empty($data['type']) || empty($data['title'])) {
            return new WP_Error(400, __('Type and title are required fields.', 'integration-google-drive'));
        }

        $notice = [
            'moduleId'    => $data['moduleId'] ?? null,
            'userId'      => $data['userId']   ?? get_current_user_id(),
            'fileKey'     => $data['fileKey']  ?? null,
            'fileName'    => $data['fileName'] ?? null,
            'page'        => $data['page']     ?? null,
            'data'        => maybe_serialize($data['data'] ?? ''),
            'type'        => $data['type'],
            'title'       => $data['title'],
            'status'      => $data['status']      ?? 'unread',
            'description' => $data['description'] ?? null,
            'createdAt'   => current_time('mysql'),
            'updatedAt'   => current_time('mysql'),
        ];

        $format = [
            '%s', // moduleId
            '%d', // userId
            '%s', // fileKey
            '%s', // fileName
            '%s', // page
            '%s', // data
            '%s', // type
            '%s', // title
            '%s', // status
            '%s', // description
            '%s', // createdAt
            '%s', // updatedAt
        ];

        $result = $this->insert($notice, $format, ARRAY_A);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_cache_flush_group('ccpigd_notices');

        return $result;
    }

    /**
     * Delete a single notice
     *
     * @param int $id The notice ID
     * @return bool|WP_Error
     */
    public function deleteNotice(int $id)
    {
        if (empty($id)) {
            return new WP_Error(400, __('Invalid ID provided.', 'integration-google-drive'));
        }

        if (!$this->exists(['id' => $id])) {
            return new WP_Error(404, __('Notice not found.', 'integration-google-drive'));
        }

        $result = $this->delete(['id' => $id], ['%d']);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_cache_flush_group('ccpigd_notices');

        return $result;
    }

    /**
     * Delete all notices
     *
     * @return bool|WP_Error
     */
    public function deleteAll()
    {
        $result = $this->delete([], [], true);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_cache_flush_group('ccpigd_notices');

        return $result;
    }

    /**
     * Change the status of a notice
     *
     * @param int $id The notice ID
     * @param string $status The new status
     * @param string $output Output type: 'bool', ARRAY_A, ARRAY_N, or OBJECT
     * @return bool|array|object|WP_Error
     */
    public function changeStatus(int $id, $status = 'read', $output = ARRAY_A)
    {
        if (empty($id)) {
            return new WP_Error(400, __('Invalid ID provided.', 'integration-google-drive'));
        }

        $status = in_array($status, ['read', 'unread']) ? $status : 'read';

        $result = $this->update(['status' => $status], ['id' => $id], ['%s'], ['%d'], ARRAY_A);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_cache_flush_group('ccpigd_notices');

        return $result;
    }

    /**
     * Mark all notices as read
     *
     * @return bool|WP_Error
     */
    public function markAllAsRead()
    {
        $result = $this->update(['status' => 'read'], ['status' => 'unread'], ['%s'], ['%s']);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_cache_flush_group('ccpigd_notices');

        return $result;
    }
}
