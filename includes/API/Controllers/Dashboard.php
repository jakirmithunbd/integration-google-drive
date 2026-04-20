<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit('No direct script access allowed');

class Dashboard extends BaseController
{
    private $fs;

    public function __construct()
    {
        parent::__construct('ccpigd/v1', 'dashboard');

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();
        global $wp_filesystem;
        $this->fs = $wp_filesystem;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, "{$this->rest_base}/status", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
        ]);

        register_rest_route($this->namespace, "{$this->rest_base}/delete/cache", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'deleteCache'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'type' => [
                    'required'    => false,
                    'enum'        => ['all', 'md', 'xl', '4xl', '5xl'],
                    'description' => __('Type of cache to delete (all, thumbnails, etc.)', 'integration-google-drive'),
                    'default'     => 'all',
                ],
            ],
        ]);


    }

    public function deleteCache(WP_REST_Request $request): WP_REST_Response
    {
        $cacheType = $request->get_param('type') ?? 'all';

        try {
            $uploadDir = wp_upload_dir();
            $basePath  = ($cacheType === 'all') ? trailingslashit($uploadDir['basedir']) . 'ccpigd-cache/' : trailingslashit($uploadDir['basedir']) . 'ccpigd-cache/' . $cacheType . '/';

            if ($this->fs && $this->fs->exists($basePath)) {
                $this->fs->rmdir($basePath, true);
            }

            return $this->successResponse(
                [],
                __('Cache deleted successfully.', 'integration-google-drive')
            );
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                __('Failed to delete cache.', 'integration-google-drive')
            );
        }
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uploadDir = wp_upload_dir();
            $basePath  = trailingslashit($uploadDir['basedir']) . 'ccpigd-cache/';

            $sizes        = ['xl', 'md', '4xl', '5xl'];
            $cacheFolders = [];

            foreach ($sizes as $size) {
                $path = $basePath . $size;

                [$bytes, $files] = $this->getFolderStats($path);

                $cacheFolders[$size] = [
                    'size'  => size_format($bytes),
                    'files' => $files,
                ];
            }

            global $wpdb;

            $totalDBCacheFiles = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i",
                    "{$wpdb->prefix}ccpigd_files"
                )
            );

            $totalShortcodes = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i",
                    "{$wpdb->prefix}ccpigd_shortcodes"
                )
            );

            $userAccess = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                FROM %i ",
                    "{$wpdb->prefix}ccpigd_user_access",
                )
            );

            $authorizedAccounts = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                FROM %i ",
                    "{$wpdb->prefix}ccpigd_accounts",
                )
            );

            return $this->successResponse(
                [
                    'cacheFolders'     => $cacheFolders,
                    'files'            => $totalDBCacheFiles,
                    'shortcodes'       => $totalShortcodes,
                    'userAccessRules'  => $userAccess,
                    'accounts'         => $authorizedAccounts,
                ],
                __('Dashboard data retrieved successfully.', 'integration-google-drive')
            );
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                __('Failed to retrieve dashboard data.', 'integration-google-drive')
            );
        }
    }

    /**
     * Get total size (bytes) and file count for a directory
     */
    private function getFolderStats(string $path): array
    {
        if (!$this->fs || !$this->fs->exists($path)) {
            return [0, 0];
        }

        $size  = 0;
        $count = 0;

        $items = $this->fs->dirlist($path, true);

        if (empty($items)) {
            return [0, 0];
        }

        foreach ($items as $item) {
            if ($item['type'] === 'f') {
                $size += (int) $item['size'];
                $count++;
            }
        }

        return [$size, $count];
    }
}
