<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\Models\Notices as NoticeModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit('No direct script access allowed');

class Notices extends BaseController
{
    public function __construct()
    {
        parent::__construct('ccpigd/v1', 'notice');
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getAll'],
                'permission_callback' => [$this, 'managePermission'],
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add'],
                'permission_callback' => [$this, 'managePermission'],
                'args'                => $this->get_create_params(),
            ],
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => [$this, 'managePermission']
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this, 'managePermission']
            ]
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/clear', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'clear'],
            'permission_callback' => [$this, 'managePermission'],
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/status/(?P<id>\d+)/', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'changeStatus'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => $this->get_status_params(),
        ]);

        register_rest_route($this->namespace, $this->rest_base . '/mark-all-read', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'markAllAsRead'],
            'permission_callback' => [$this, 'managePermission'],
        ]);
    }

    public function getAll(WP_REST_Request $request): WP_REST_Response
    {

        try {

            $page    = (int) $request->get_param('page') ?: 1;
            $perPage = (int) $request->get_param('perPage') ?: 10;
            $status  = sanitize_text_field($request->get_param('status') ?: '');

            $args = [
                'page'    => $page,
                'perPage' => $perPage
            ];

            if (!empty($status)) {
                $args['status'] = $status;
            }

            $notices = NoticeModel::getInstance()->getAll($args);

            if (is_wp_error($notices)) {
                return $this->errorResponse($notices->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($notices)) {

                return $this->errorResponse('No notices found.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($notices, 'Notices fetched successfully.');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to retrieve notices');
        }
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');

            if (!$id) {
                return $this->errorResponse('Notice ID is required.', self::HTTP_BAD_REQUEST);
            }

            $notice = NoticeModel::getInstance()->get($id);

            if (empty($notice)) {
                return $this->errorResponse('Notice not found.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse(
                (array) $notice,
                'Notice fetched successfully.'
            );

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to retrieve notice');
        }
    }

    public function add(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $title       = sanitize_text_field($request->get_param('title'));
            $type        = sanitize_text_field($request->get_param('type'));
            $description = sanitize_text_field($request->get_param('description') ?: '');
            $status      = sanitize_text_field($request->get_param('status') ?: 'unread');

            if (empty($title) || empty($type)) {
                return $this->errorResponse('Notice title and type are required.', self::HTTP_BAD_REQUEST);
            }

            $notice_data = [
                'title'       => $title,
                'type'        => $type,
                'description' => $description,
                'status'      => $status
            ];

            $notices = NoticeModel::getInstance()->add($notice_data);

            if (is_wp_error($notices)) {
                return $this->errorResponse($notices->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($notices)) {
                return $this->errorResponse('Failed to add notice.', self::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse(
                ['notices' => $notices],
                'Notice added successfully.'
            );

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to add notice');
        }
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');

            if (!$id) {
                return $this->errorResponse('Notice ID is required.', self::HTTP_BAD_REQUEST);
            }

            $result = NoticeModel::getInstance()->deleteNotice($id);

            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($result)) {
                return $this->errorResponse('Notice not found or already deleted.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($result, 'Notice deleted successfully.');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to delete notice');
        }
    }

    public function clear(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $result = NoticeModel::getInstance()->deleteAll();

            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($result)) {
                return $this->errorResponse('No notices found to clear.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($result, 'All notices cleared successfully.');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to clear notices');
        }
    }

    public function changeStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id     = (int) $request->get_param('id');

            if (!$id) {
                return $this->errorResponse('Notice ID is required.', self::HTTP_BAD_REQUEST);
            }

            $result = NoticeModel::getInstance()->changeStatus($id);

            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($result)) {
                return $this->errorResponse('Notice not found.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($result, 'Notice status changed successfully.');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to change notice status');
        }
    }

    public function markAllAsRead(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $result = NoticeModel::getInstance()->markAllAsRead();

            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (empty($result)) {
                return $this->errorResponse('No notices found to mark as read.', self::HTTP_NOT_FOUND);
            }

            return $this->successResponse($result, 'All notices marked as read successfully.');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to mark all notices as read');
        }
    }

    private function get_collection_params(): array
    {
        return [
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number for pagination',
                'default'     => 1,
                'minimum'     => 1,
            ],
            'perPage' => [
                'type'        => 'integer',
                'description' => 'Number of notices per page',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 100,
            ],
            'status' => [
                'type'        => 'string',
                'description' => 'Filter notices by status',
                'enum'        => ['read', 'unread'],
            ],
        ];
    }

    private function get_create_params(): array
    {
        return [
            'title' => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'Notice title',
            ],
            'type' => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'Notice type',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'Notice description',
                'default'     => '',
            ],
            'status' => [
                'type'        => 'string',
                'description' => 'Notice status',
                'enum'        => ['read', 'unread'],
                'default'     => 'unread',
            ],
        ];
    }

    private function get_status_params(): array
    {
        return [
            'id' => [
                'required'    => true,
                'type'        => 'integer',
                'description' => 'Notice ID',
                'minimum'     => 1,
            ]
        ];
    }
}
