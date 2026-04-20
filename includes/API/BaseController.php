<?php

namespace CodeConfig\IGD\API;

use function defined;

defined('ABSPATH') || exit;

use CodeConfig\IGD\Utils\Helpers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

abstract class BaseController
{
    protected string $namespace;
    protected string $rest_base;

    // HTTP Status Codes
    protected const HTTP_OK                    = 200;
    protected const HTTP_CREATED               = 201;
    protected const HTTP_BAD_REQUEST           = 400;
    protected const HTTP_UNAUTHORIZED          = 401;
    protected const HTTP_FORBIDDEN             = 403;
    protected const HTTP_NOT_FOUND             = 404;
    protected const HTTP_INTERNAL_SERVER_ERROR = 500;

    public function __construct(string $namespace, string $rest_base)
    {
        $this->namespace = $namespace;
        $this->rest_base = "/$rest_base";
    }

    /**
     * Register routes - must be implemented by child classes
     */
    abstract public function register_routes(): void;

    /**
     * Check permissions - can be overridden by child classes
     */
    public function hasAllPermission(): bool
    {
        return ccpigdHasUserAccessPage(['file_browser', 'settings', 'module_builder', 'media_library']);
    }

    public function hasAnyPermission(): bool
    {
        return ccpigdHasUserAccessPage(['file_browser', 'settings', 'module_builder', 'media_library'], 'OR');
    }

    public function managePermission(WP_REST_Request $request)
    {
        if ($this->hasAllPermission()) {
            return true;
        }

        $action        = '';
        $route         = $request->get_route();
        $method        = $request->get_method();

        switch ($route) {
            case strpos($route, '/ccpigd/v1/folder/create') !== false && $method === 'POST':
                $action = 'newFolder';
                break;
            case strpos($route, '/ccpigd/v1/file/rename') !== false && $method === 'POST':
                $action = 'rename';
                break;
            case strpos($route, '/ccpigd/v1/file') !== false && $method === 'DELETE':
                $action = 'delete';
                break;
            case strpos($route, '/ccpigd/v1/file/upload') !== false && ($method === 'POST' || $method === 'GET'):
                $action = 'upload';
                break;
            case strpos($route, '/ccpigd/v1/file/share') !== false && $method === 'GET':
                $action = 'share';
                break;
            case strpos($route, '/ccpigd/v1/file/move') !== false && $method === 'POST':
                $action = 'move';
                break;
            case strpos($route, '/ccpigd/v1/file/copy') !== false && $method === 'POST':
                $action = 'copy';
                break;
            case strpos($route, '/ccpigd/v1/shortcode') !== false && $method === 'GET':
                $action = 'search';
                break;
            case strpos($route, '/ccpigd/v1/folder/tree') !== false && $method === 'GET':
                $action = 'tree';
                break;
            case strpos($route, '/ccpigd/v1/file/open-in-drive') !== false && $method === 'GET':
                $action = 'preview';
                break;
            case strpos($route, '/ccpigd/v1/account/auth-url') !== false && $method === 'GET':
                $action = 'authUrl';
                break;
            case strpos($route, '/ccpigd/v1/file/download') !== false && $method === 'GET':
                $action = 'download';
                break;
            case strpos($route, '/ccpigd/v1/file/by-keys') !== false && $method === 'GET':
                $action = 'byKeys';
                break;
            case strpos($route, '/ccpigd/v1/account/switch') !== false && $method === 'GET':
                $action = 'switch';
                break;
            default:
                $action = '';
                break;
        }

        if (empty($action)) {
            return new WP_Error('forbidden', 'You do not have permission to access this resource.', [ 'status' => self::HTTP_FORBIDDEN ]);
        }

        $permissionPrivileges = [
            'file_browser'    => ['newFolder', 'rename', 'delete', 'upload', 'share', 'move', 'copy', 'search', 'tree', 'preview', 'download', 'byKeys', 'authUrl', 'switch'],
            'settings'        => ['authUrl'],
            'media_library'   => ['move', 'rename', 'delete', 'newFolder', 'upload'],
        ];

        $shortcodeId = $request->get_param('shortcodeId');

        if (empty($shortcodeId)) {
            $userAccess    = ccpigdGetCurrentUserAccess();
            if (!empty($userAccess['folders']) && is_array($userAccess['folders'])) {
                foreach ($permissionPrivileges as $page => $actions) {
                    if (in_array($action, $actions) && ccpigdHasUserAccessPage($page)) {
                        return true;
                    }
                }
            }

            return new WP_Error('forbidden', 'You do not have permission to access this resource, Shortcode ID is missing.', [ 'status' => self::HTTP_FORBIDDEN ]);
        }

        if ($action === 'tree' && $request->get_param('fileKey') === 'my-drive') {
            return Helpers::hasShortcodePermission($shortcodeId, 'tree');
        }

        if ($action === 'search') {
            $query = $request->get_param('search');
            if (empty($query)) {
                $fileKey = $request->get_param('fileKey');

                if (!empty($fileKey) && '/' !== $fileKey) {
                    if (Helpers::hasShortcodePermission($shortcodeId, 'getFolder', $fileKey)) {
                        return true;
                    } else {
                        return new WP_Error('forbidden', 'You do not have permission to access this folder.', [ 'status' => self::HTTP_FORBIDDEN ]);
                    }
                } else {
                    return true;
                }
            }
        }

        $fileKey    = (array) $request->get_param('fileKey')         ?? [];
        $folderKey  = (array) $request->get_param('folderKey')       ?? [];
        $fileKeys   = (array) $request->get_param('fileKeys')        ?? [];

        $mergedKeys = array_merge($fileKey, $folderKey, $fileKeys);

        if (empty($mergedKeys)) {
            return Helpers::hasShortcodePermission($shortcodeId, $action);
        }

        foreach ($mergedKeys as $key) {
            if (!Helpers::hasShortcodePermission($shortcodeId, $action, $key)) {
                return new WP_Error('forbidden', 'You do not have permission to access this resource.', [ 'status' => self::HTTP_FORBIDDEN ]);
            }
        }

        return true;
    }

    public function manageFilePermission(WP_REST_Request $request): bool
    {
        return ccpigdHasUserAccessPage('file_browser');
    }

    public function manageSettingsPermission(WP_REST_Request $request): bool
    {
        return ccpigdHasUserAccessPage('settings');
    }

    public function manageModuleBuilderPermission(WP_REST_Request $request): bool
    {
        return ccpigdHasUserAccessPage('module_builder');
    }

    public function manageMediaLibraryPermission(WP_REST_Request $request): bool
    {
        return ccpigdHasUserAccessPage('media_library');
    }

    /**
     * Create success response
     */
    protected function successResponse($data, string $message = 'Success', array $meta = []): WP_REST_Response
    {
        $response_data = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $response_data['meta'] = $meta;
        }

        return new WP_REST_Response($response_data, self::HTTP_OK);
    }

    /**
     * Create error response
     */
    protected function errorResponse(string $message, int $status = self::HTTP_BAD_REQUEST, array $extra = []): WP_REST_Response
    {
        $response_data = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($extra)) {
            $response_data['extra'] = $extra;
        }

        return new WP_REST_Response($response_data, $status);
    }

    /**
     * Validate and sanitize request data
     */
    protected function validateRequestData(WP_REST_Request $request, array $rules = []): array
    {
        $data = $request->get_json_params() ?: $request->get_params();

        // Add your validation logic here
        foreach ($rules as $field => $rule) {
            if ($rule['required'] && !isset($data[$field])) {
                throw new \InvalidArgumentException(esc_html("Missing required field: {$field}"));
            }
        }

        return $data;
    }

    /**
     * Handle exceptions and return appropriate response
     */
    protected function handleException(\Exception $e, string $default_message = 'An error occurred'): WP_REST_Response
    {
        $message = $default_message;
        $status  = self::HTTP_INTERNAL_SERVER_ERROR;
        $extra   = [];

        if ($e instanceof \InvalidArgumentException) {
            $message = $e->getMessage();
            $status  = self::HTTP_BAD_REQUEST;
        }

        if ($e instanceof WP_Error) {
            $message     = $e->get_error_message() ?: $message;
            $errorCode   = $e->get_error_code();
            $status      = is_numeric($errorCode) ? (int) $errorCode : self::HTTP_INTERNAL_SERVER_ERROR;
        } elseif ($e instanceof \Exception) {
            $extra['error'] = $e->getMessage();
        }

        return $this->errorResponse($message, $status, $extra);
    }
}
