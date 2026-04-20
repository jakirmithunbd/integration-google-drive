<?php

namespace CodeConfig\IGD\API\Controllers;

use function CodeConfig\ccpigd_fs;
use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\Models\Shortcode as ShortcodeModel;
use function in_array;
use function is_array;
use WP_REST_Request;
use WP_REST_Server;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Shortcode extends BaseController {
    public function __construct() {
        parent::__construct( "ccpigd/v1", "shortcode" );
    }

    public function register_routes() : void {
        register_rest_route( $this->namespace, $this->rest_base, [[
            "methods"             => WP_REST_Server::READABLE,
            "callback"            => [$this, "getAll"],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
        ], [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "add"],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
            "args"                => $this->get_create_params(),
        ], [
            "methods"             => WP_REST_Server::DELETABLE,
            "callback"            => [$this, "delete"],
            "permission_callback" => [$this, "manageModuleBuilderPermission"],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/duplicate", [[
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'duplicate'],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/import", [[
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'import'],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/(?P<type>file-browser|gallery)", [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
            'callback'            => [$this, 'getDefaultTemplate'],
            'args'                => [
                'type' => [
                    'validate_callback' => fn( $param ) => in_array( $param, ['file-browser', 'gallery'], true ),
                ],
            ],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/(?P<shortcodeId>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'shortcodeId' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
                'page'        => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 1,
                ],
                'perPage'     => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 20,
                ],
                'fileKey'     => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => '/',
                ],
                'order'       => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'DESC',
                ],
                'orderBy'     => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'createdAt',
                ],
                'search'      => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => '',
                ],
                'searchScope' => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'folder',
                ],
                'from'        => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'cache',
                ],
                'password'    => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => '',
                ],
                'types'       => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'all',
                ],
                'isAdmin'     => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ],
            ],
        ], [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update'],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
            'args'                => [
                'shortcodeId' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
                'title'       => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'type'        => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'status'      => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'data'        => [
                    'required'          => false,
                    'type'              => 'object',
                    'validate_callback' => function ( $value, $request, $param ) {
                        if ( !is_array( $value ) ) {
                            return new \WP_Error('invalid_data', 'Data must be an array.');
                        }
                        return true;
                    },
                ],
                'location'    => [
                    'required' => false,
                    'type'     => 'string',
                ],
            ],
        ], [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'manageModuleBuilderPermission'],
            'args'                => [
                'shortcodeId' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
            ],
        ]] );
    }

    public function getDefaultTemplate( WP_REST_Request $request ) {
        $type = $request->get_param( 'type' );
        try {
            $template = ccpigdGetModuleDefaultData( $type );
            if ( empty( $template ) || is_wp_error( $template ) ) {
                return $this->errorResponse( __( 'Default template not found.', 'integration-google-drive' ), self::HTTP_NOT_FOUND );
            }
            return $this->successResponse( [
                'shortcode' => $template,
            ] );
        } catch ( \Throwable $e ) {
            return $this->handleException( $e, __( 'Failed to retrieve default template.', 'integration-google-drive' ) );
        }
    }

    public function add( WP_REST_Request $request ) {
        $title = $request->get_param( 'title' );
        $type = $request->get_param( 'type' );
        $status = $request->get_param( 'status' );
        $data = $request->get_param( 'data' );
        $location = $request->get_param( 'location' );
        $integration = $request->get_param( 'integration' );
        try {
            $shortcode = ShortcodeModel::getInstance()->add( [
                'title'       => $title,
                'type'        => $type,
                'status'      => $status,
                'data'        => $data,
                'locations'   => $location,
                'integration' => $integration,
            ] );
            if ( is_wp_error( $shortcode ) ) {
                return $this->errorResponse( $shortcode->get_error_message(), self::HTTP_BAD_REQUEST );
            }
            return $this->successResponse( [
                'shortcode' => $shortcode,
            ], __( 'Shortcode created successfully.', 'integration-google-drive' ) );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, __( 'Failed to create shortcode.', 'integration-google-drive' ) );
        }
    }

    public function get( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'shortcodeId' );
        $page = $request->get_param( 'page' );
        $perPage = $request->get_param( 'perPage' );
        $fileKey = $request->get_param( 'fileKey' );
        $order = $request->get_param( 'order' );
        $orderBy = $request->get_param( 'orderBy' );
        $search = $request->get_param( 'search' );
        $searchScope = $request->get_param( 'searchScope' );
        $from = $request->get_param( 'from' );
        $password = $request->get_param( 'password' );
        $types = $request->get_param( 'types' );
        $isAdmin = $request->get_param( 'isAdmin' );
        $types = ( $types === 'all' ? [] : array_filter( array_map( 'trim', explode( ',', $types ) ) ) );
        try {
            $totalCount = ShortcodeModel::getInstance()->totalCount();
            if ( $totalCount > 5 && !ccpigd_fs()->can_use_premium_code__premium_only() ) {
                return $this->errorResponse( __( 'Your shortcode limit 5 is reached.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
            }
            $shortcode = ShortcodeModel::getInstance()->get( $id, [
                'page'        => $page,
                'perPage'     => $perPage,
                'fileKey'     => $fileKey,
                'order'       => $order,
                'orderBy'     => $orderBy,
                'search'      => $search,
                'searchScope' => $searchScope,
                'from'        => $from,
                'password'    => $password,
                'types'       => $types,
                'isAdmin'     => $isAdmin,
            ] );
            if ( is_wp_error( $shortcode ) ) {
                return $this->errorResponse( $shortcode->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( empty( $shortcode ) ) {
                return $this->errorResponse( __( 'Shortcode not found.', 'integration-google-drive' ), self::HTTP_NOT_FOUND );
            }
            return $this->successResponse( [
                'shortcode' => $shortcode,
            ] );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, __( 'Failed to retrieve shortcode.', 'integration-google-drive' ) );
        }
    }

    public function getAll( WP_REST_Request $request ) {
        $config = $request->get_query_params();
        $defaults = [
            'type'    => 'all',
            'search'  => '',
            'status'  => 'all',
            'order'   => 'DESC',
            'orderBy' => 'updatedAt',
            'page'    => 1,
            'perPage' => 10,
        ];
        $queryArgs = wp_parse_args( $config, $defaults );
        $queryArgs['page'] = (int) $queryArgs['page'];
        $queryArgs['perPage'] = (int) $queryArgs['perPage'];
        try {
            $shortcodes = ShortcodeModel::getInstance()->getAll( $queryArgs );
            $totalResult = ShortcodeModel::getInstance()->totalCount( $queryArgs );
            $totalCount = ShortcodeModel::getInstance()->totalCount();
            if ( is_wp_error( $shortcodes ) ) {
                return $this->errorResponse( $shortcodes->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( is_wp_error( $totalResult ) ) {
                return $this->errorResponse( $totalResult->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            $total = (int) $totalResult;
            $totalPages = ( $total > 0 ? (int) ceil( $total / $queryArgs['perPage'] ) : 0 );
            $hasMore = $queryArgs['page'] < $totalPages;
            return $this->successResponse( [
                'shortcodes' => $shortcodes,
                'totalPages' => $totalPages,
                'hasMore'    => $hasMore,
                'total'      => $totalCount,
                'page'       => (int) $queryArgs['page'],
            ] );
        } catch ( \Throwable $e ) {
            return $this->handleException( $e, __( 'Failed to retrieve shortcodes.', 'integration-google-drive' ) );
        }
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'shortcodeId' );
        $title = $request->get_param( 'title' );
        $type = $request->get_param( 'type' );
        $status = $request->get_param( 'status' );
        $data = $request->get_param( 'data' );
        $location = $request->get_param( 'location' );
        try {
            $shortcode = ShortcodeModel::getInstance()->add( [
                'id'        => $id,
                'title'     => $title,
                'type'      => $type,
                'status'    => $status,
                'data'      => $data,
                'locations' => $location,
            ] );
            if ( is_wp_error( $shortcode ) ) {
                return $this->errorResponse( $shortcode->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( [
                'shortcode' => $shortcode,
            ], __( 'Shortcode updated successfully.', 'integration-google-drive' ) );
        } catch ( \Throwable $e ) {
            return $this->handleException( $e, __( 'Failed to update shortcode.', 'integration-google-drive' ) );
        }
    }

    public function delete( WP_REST_Request $request ) {
        $ids = $request->get_param( 'ids' );
        $id = $request->get_param( 'shortcodeId' );
        if ( !empty( $id ) ) {
            $ids = [$id];
        }
        try {
            $deleted = ShortcodeModel::getInstance()->remove( $ids );
            if ( is_wp_error( $deleted ) ) {
                return $this->errorResponse( $deleted->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $deleted, __( 'Shortcode deleted successfully.', 'integration-google-drive' ) );
        } catch ( \Throwable $e ) {
            return $this->handleException( $e, __( 'Failed to delete shortcode.', 'integration-google-drive' ) );
        }
    }

    public function duplicate( WP_REST_Request $request ) {
        $ids = $request->get_param( 'ids' );
        if ( empty( $ids ) ) {
            return $this->errorResponse( __( 'Shortcode IDs are required.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
        }
        if ( !is_array( $ids ) ) {
            $ids = [$ids];
        }
        try {
            $result = ShortcodeModel::getInstance()->duplicate( $ids );
            if ( is_wp_error( $result ) ) {
                return $this->errorResponse( $result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( [
                'duplicated' => $result,
            ], __( 'Shortcode(s) duplicated successfully.', 'integration-google-drive' ) );
        } catch ( \Throwable $e ) {
            return $this->handleException( $e, __( 'Failed to duplicate shortcode.', 'integration-google-drive' ) );
        }
    }

    public function import( WP_REST_Request $request ) {
        $shortcodes = $request->get_param( 'shortcodes' );
        if ( empty( $shortcodes ) ) {
            return $this->errorResponse( __( 'Import shortcodes is required.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
        }
        try {
            $importedShortcodes = ShortcodeModel::getInstance()->import( $shortcodes );
            if ( is_wp_error( $importedShortcodes ) ) {
                return $this->errorResponse( $importedShortcodes->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( [
                'imported' => $importedShortcodes,
            ], __( 'Shortcodes imported successfully.', 'integration-google-drive' ) );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, __( 'Failed to import shortcodes.', 'integration-google-drive' ) );
        }
    }

    private function get_create_params() : array {
        return [
            'title'    => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'Shortcode title',
            ],
            'type'     => [
                'required'    => true,
                'type'        => 'string',
                'description' => 'Shortcode type',
            ],
            'status'   => [
                'type'        => 'string',
                'description' => 'Shortcode status',
                'enum'        => ['active', 'inactive'],
                'default'     => 'active',
            ],
            'data'     => [
                'required'          => true,
                'type'              => 'object',
                'description'       => 'Shortcode data',
                'validate_callback' => function ( $value, $request, $param ) {
                    if ( !is_array( $value ) ) {
                        return new \WP_Error('invalid_data', 'Data must be an array.');
                    }
                    return true;
                },
            ],
            'location' => [
                'type'        => 'string',
                'description' => 'Shortcode location',
            ],
        ];
    }

}
