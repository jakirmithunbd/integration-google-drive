<?php

namespace CodeConfig\IGD;

defined('ABSPATH') || exit('No direct script access allowed');

use CodeConfig\IGD\Utils\Singleton;

class Database
{
    use Singleton;

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $prefix;


    public function __construct()
    {
        global $wpdb;

        $this->wpdb   = $wpdb;
        $this->prefix = $this->wpdb->prefix;
    }

    public function get_attachments($folder_key)
    {
        if (!empty($folder_key)) {
            global $wpdb;

            $folder_meta_key   = '_ccpigd_media_folder_key';
            $folder_meta_value = $folder_key; // The folder ID you're searching for
            $file_meta_key     = '_ccpigd_media_file_key';

            $cache_key = "ccpigd_attachments_{$folder_meta_value}";

            $get_file_keys = wp_cache_get($cache_key);

            if ($get_file_keys) {
                return $get_file_keys;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $get_file_keys = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pm2.meta_value AS file_key
                    FROM {$wpdb->postmeta} pm1
                    INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                    WHERE pm1.meta_key = %s AND pm1.meta_value = %s
                    AND pm2.meta_key = %s",
                    $folder_meta_key,
                    $folder_meta_value,
                    $file_meta_key
                ),
                ARRAY_A
            );

            $file_keys = wp_list_pluck($get_file_keys, 'file_key');

            // Cache the result for 1 hour
            wp_cache_set($cache_key, $file_keys, '', HOUR_IN_SECONDS);

            return $file_keys;
        }

        return [];
    }


    public static function is_attachment_exists($file_key, $folder_key)
    {
        if (!empty($file_key)) {
            global $wpdb;

            $meta_key    = '_ccpigd_media_file_key';
            $meta_value  = $file_key;
            $meta_key2   = '_ccpigd_media_folder_key';
            $meta_value2 = $folder_key;

            $cache_key = "ccpigd_attachment_exists_{$meta_value}_{$meta_value2}";

            $post_exists = wp_cache_get($cache_key);

            if ($post_exists) {
                return $post_exists;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $post_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT pm1.post_id
                FROM $wpdb->postmeta pm1
                 JOIN $wpdb->postmeta pm2 ON pm1.post_id = pm2.post_id
                 WHERE pm1.meta_key = %s AND pm1.meta_value = %s
                 AND pm2.meta_key = %s AND pm2.meta_value = %s",
                $meta_key,
                $meta_value,
                $meta_key2,
                $meta_value2
            ));

            if (!empty($post_exists)) {
                wp_cache_set($cache_key, $post_exists, '', HOUR_IN_SECONDS);

                return $post_exists;
            } else {
                return false;
            }
        }
    }
}
