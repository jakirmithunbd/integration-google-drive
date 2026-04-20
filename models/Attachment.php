<?php

namespace CodeConfig\IGD\Models;

defined('ABSPATH') || exit('No direct script access allowed');

class Attachment
{
    public static function get($folderKey)
    {
        if (!empty($folderKey)) {
            global $wpdb;

            $folder_meta_key   = '_ccpigd_media_folder_key';
            $file_meta_key     = '_ccpigd_media_file_key';

            $cacheKey           = 'ccpigd_attachment_get_' . md5($folderKey);
            $cachedFileKeys     = wp_cache_get($cacheKey);
            if ($cachedFileKeys !== false) {
                return $cachedFileKeys;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $get_file_keys = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_value AS file_key
                    FROM $wpdb->postmeta
                    WHERE meta_key = %s
                    AND post_id IN (
                        SELECT post_id
                        FROM $wpdb->postmeta
                        WHERE meta_key = %s
                        AND meta_value = %s
                    )",
                    $file_meta_key,
                    $folder_meta_key,
                    $folderKey
                ),
                ARRAY_A
            );

            $file_keys = wp_list_pluck($get_file_keys, 'file_key');
            wp_cache_set($cacheKey, $file_keys);

            return $file_keys;
        }

        return [];
    }

    public static function exists($fileKey)
    {
        if (!empty($fileKey)) {
            global $wpdb;

            $meta_key   = '_ccpigd_media_file_key';
            $meta_value = $fileKey;

            $cacheKey     = 'ccpigd_attachment_exists_' . md5($meta_key . '_' . $meta_value);
            $cachedPostId = wp_cache_get($cacheKey);
            if ($cachedPostId !== false) {
                return $cachedPostId;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $post_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = %s
                    AND meta_value COLLATE utf8mb4_bin = %s
                    LIMIT 1",
                    $meta_key,
                    $meta_value
                )
            );

            if (!empty($post_exists)) {
                wp_cache_set($cacheKey, (int) $post_exists);

                return (int) $post_exists;
            }
        }

        return false;
    }

    public static function clearAttachments()
    {
        $attachments = get_posts([
            'post_type'         => 'attachment',
            'numberposts'       => -1,
            'meta_query'        => [
                [
                    'key'     => '_ccpigd_media_file_key',
                    'compare' => 'EXISTS',
                ]
            ]
        ]);

        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }
}
