<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\Models\UserAccess;
use CodeConfig\IGD\Utils\Helpers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit('No direct script access allowed');

class Users extends BaseController
{
    public function __construct()
    {
        parent::__construct('ccpigd/v1', 'user');
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, $this->rest_base . '/list', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getUserList'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'hideCurrentUser' => [
                    'required'    => false,
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => __('Whether to hide the current logged-in user from the list.', 'integration-google-drive'),
                ],

                'fields' => [
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => __('Comma-separated list of user fields to retrieve. Allowed fields are: ID, user_login, user_nicename, user_email, user_url, user_registered, display_name, nickname, first_name, last_name, description, roles.', 'integration-google-drive'),
                    'default'           => 'ID,user_login',
                    'validate_callback' => function ($param, $request, $key) {
                        if (!is_string($param)) {
                            return false;
                        }
                        $allowed_fields = [
                            'ID', 'user_login', 'user_nicename', 'user_email', 'user_url',
                            'user_registered', 'display_name', 'nickname', 'first_name',
                            'last_name', 'description', 'roles'
                        ];
                        $fields = array_map('trim', explode(',', $param));
                        foreach ($fields as $field) {
                            if (!in_array($field, $allowed_fields, true)) {

                                /* translators: %s: Invalid field name */
                                return new WP_Error('invalid_field', sprintf(__('Invalid field: %s', 'integration-google-drive'), $field));
                            }
                        }

                        return true;
                    },
                ],
            ]
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/roles', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getUserRoles'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/access', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getUsersAccess'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'setUserAccess'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->get_create_params(),
            ]
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/access/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getUserAccess'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->get_access_params(),
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateUserAccess'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->get_update_params(),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'removeUserAccess'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->get_access_params(),
            ],
        ]);
    }

    public function getUserRoles(WP_REST_Request $request): WP_REST_Response
    {
        try {
            global $wp_roles;

            if (!isset($wp_roles)) {
                $wp_roles = new \WP_Roles();
            }

            $roles     = $wp_roles->get_names();
            $rolesList = [];

            foreach ($roles as $role_key => $role_name) {
                $rolesList[] = [
                    'roleKey'   => $role_key,
                    'roleName'  => $role_name
                ];
            }

            return $this->successResponse([ 'roles' => $rolesList ], 'Roles retrieved successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve roles');
        }
    }

    public function getUserList(WP_REST_Request $request): WP_REST_Response
    {
        $hideCurrentUser = $request->get_param('hideCurrentUser');
        $fields          = $request->get_param('fields');
        $fields          = array_map('trim', explode(',', $fields));
        $currentUserId   = get_current_user_id();
        try {
            $args = [
                'orderby' => 'display_name',
                'order'   => 'ASC'
            ];

            $users     = get_users($args);
            $user_list = [];

            foreach ($users as $user) {
                if ($hideCurrentUser && $user->ID == $currentUserId) {
                    continue;
                }

                $list = [];

                foreach ($fields as $field) {
                    $list[$field] = $user->{$field} ?? null;
                }

                $user_list[] = $list;
            }

            return $this->successResponse([
                'users' => $user_list
            ], 'Users retrieved successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve users');
        }
    }

    public function setUserAccess(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $type    = sanitize_text_field($request->get_param('type'));
            $value   = sanitize_text_field($request->get_param('value'));
            $pages   = $request->get_param('pages');
            $folders = $request->get_param('folders');

            if (empty($type) || empty($value)) {
                return $this->errorResponse('Type and Value are required fields.', self::HTTP_BAD_REQUEST);
            }

            if (empty($folders)) {
                return $this->errorResponse('Folders field is required!', self::HTTP_BAD_REQUEST);
            }

            if (empty($pages)) {
                return $this->errorResponse('Assign Settings field is required!', self::HTTP_BAD_REQUEST);
            }

            $folders = Helpers::sanitization($folders);
            $pages   = Helpers::sanitization($pages);

            if ('user' === $type) {
                $current_user = wp_get_current_user();
                if ($current_user->user_login === $value) {
                    return $this->errorResponse('You cannot set access for the currently logged-in user!', self::HTTP_BAD_REQUEST);
                }

                $user         = get_user_by('login', $value);

                if (empty($user)) {
                    return $this->errorResponse('User not found!', self::HTTP_NOT_FOUND);
                }

            } elseif ('role' === $type) {
                if ('administrator' === $value) {
                    return $this->errorResponse('You cannot set access for the administrator role!', self::HTTP_BAD_REQUEST);
                }

                $role = get_role($value);

                if (empty($role)) {
                    return $this->errorResponse('Role not found!', self::HTTP_NOT_FOUND);
                }
            } else {
                return $this->errorResponse('Invalid type specified! Must be "user" or "role".', self::HTTP_BAD_REQUEST);
            }

            $insert_id = UserAccess::getInstance()->create($type, $value, $folders, $pages);

            if (! $insert_id) {
                return $this->errorResponse('Failed to create access record', self::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse([
                'id'      => $insert_id,
                'type'    => $type,
                'value'   => $value,
                'folders' => $folders,
                'pages'   => $pages,
            ], 'Access successfully set!');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to set user access');
        } catch (\Error $e) {
            return $this->errorResponse('System error: ' . $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function removeUserAccess(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');

            if ($id <= 0) {
                return $this->errorResponse('Valid ID is required!', self::HTTP_BAD_REQUEST);
            }

            $UserAccess = UserAccess::getInstance();

            $delete = $UserAccess->deleteRecord($id);

            if (empty($delete)) {
                return $this->errorResponse('Failed to delete record!', self::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse($delete, 'User access removed successfully!');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to remove user access');
        } catch (\Error $e) {
            return $this->errorResponse('System error: ' . $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUsersAccess(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $users_access = UserAccess::getInstance()->getAll();

            if (is_wp_error($users_access)) {
                return $this->errorResponse($users_access->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse($users_access ?? [], 'User access retrieved successfully!');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve users access');
        } catch (\Error $e) {
            return $this->errorResponse('System error: ' . $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUserAccess(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');

            if ($id <= 0) {
                return $this->errorResponse('Valid ID is required!', self::HTTP_BAD_REQUEST);
            }

            $user_access = UserAccess::getInstance()->get($id);

            if (is_wp_error($user_access)) {
                return $this->errorResponse($user_access->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            } elseif (empty($user_access)) {
                return $this->errorResponse('No access data found for the specified ID.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($user_access, 'User access retrieved successfully!');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve user access');
        } catch (\Error $e) {
            return $this->errorResponse('System error: ' . $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateUserAccess(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id      = (int) $request->get_param('id');
            $type    = sanitize_text_field($request->get_param('type'));
            $value   = sanitize_text_field($request->get_param('value'));
            $pages   = $request->get_param('pages');
            $folders = $request->get_param('folders') ?? [];

            if ($id <= 0 || empty($type) || empty($value)) {
                return $this->errorResponse('ID, type, and value are required!', self::HTTP_BAD_REQUEST);
            }

            if (empty($folders)) {
                return $this->errorResponse('Folders is required!', self::HTTP_BAD_REQUEST);
            }

            if ('user' === $type) {
                $user = get_user_by('login', $value);

                if (empty($user)) {
                    return $this->errorResponse('User not found!', self::HTTP_NOT_FOUND);
                }

            } elseif ('role' === $type) {
                $role = get_role($value);

                if (empty($role)) {
                    return $this->errorResponse('Role not found!', self::HTTP_NOT_FOUND);
                }
            } else {
                return $this->errorResponse('Invalid type specified! Must be "user" or "role".', self::HTTP_BAD_REQUEST);
            }

            $folders = Helpers::sanitization($folders);
            $pages   = Helpers::sanitization($pages);

            $UserAccess = UserAccess::getInstance();

            $update = $UserAccess->updateRecord($id, $type, $value, $folders, $pages);

            if (is_wp_error($update)) {
                return $this->errorResponse('Update failed!', self::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse([
                'id'      => $id,
                'type'    => $type,
                'value'   => $value,
                'folders' => $folders,
                'pages'   => $pages,
            ], 'User access updated successfully!');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to update user access');
        } catch (\Error $e) {
            return $this->errorResponse('System error: ' . $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function get_access_params(): array
    {
        return [
            'id' => [
                'required'    => true,
                'type'        => 'integer',
                'description' => 'User access ID',
                'minimum'     => 1,
            ],
        ];
    }

    /**
     * Get parameters for create endpoint
     */
    private function get_create_params(): array
    {
        return [
            'type'    => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'Access type (user or role)',
                'enum'        => ['user', 'role'],
            ],
            'value'   => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'User ID or role name',
            ],
            'folders' => [
                'required'    => true,
                'type'        => 'array',
                'description' => 'Array of folder keys',
                'items'       => [
                    'type' => 'string',
                ],
            ],
            'pages'   => [
                'type'              => 'array',
                'description'       => 'Pages access setting',
                'required'          => true,
                'validate_callback' => function ($value) {
                    if (! is_array($value)) {
                        return false;
                    }

                    $allowed = ['file_browser', 'settings', 'module_builder', 'media_library'];

                    foreach ($value as $item) {
                        if (! in_array($item, $allowed, true)) {
                            return false;
                        }
                    }

                    return true;
                },
            ],
        ];
    }

    private function get_update_params(): array
    {
        return array_merge($this->get_access_params(), $this->get_create_params());
    }
}
