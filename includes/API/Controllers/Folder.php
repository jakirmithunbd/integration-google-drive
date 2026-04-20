<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Models\Shortcode;
use CodeConfig\IGD\Models\UserAccess;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Folder extends BaseController {
    public function __construct() {
        parent::__construct( 'ccpigd/v1', 'folder' );
    }

    public function register_routes() : void {
        register_rest_route( $this->namespace, "{$this->rest_base}/create", [[
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'name'        => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'fileKey'     => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'shortcodeId' => [
                    'type'     => 'integer',
                    'required' => false,
                ],
            ],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/tree", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'tree'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'fileKey'     => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'my-drive',
                ],
                'shortcodeId' => [
                    'type'     => 'integer',
                    'required' => false,
                ],
            ],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/(?P<fileKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => [$this, 'getFolderPermission'],
            'args'                => [
                'fileKey'     => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'from'        => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'cache',
                ],
                'perPage'     => [
                    'type'     => 'integer',
                    'required' => false,
                    'default'  => 20,
                ],
                'page'        => [
                    'type'     => 'integer',
                    'required' => false,
                    'default'  => 1,
                ],
                'orderBy'     => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'updatedAt',
                ],
                'order'       => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'desc',
                ],
                'types'       => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'all',
                ],
                'search'      => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => null,
                ],
                'searchScope' => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => 'folder',
                ],
            ],
        ]] );
    }

    public function get( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $key = $request->get_param( 'fileKey' );
            $from = $request->get_param( 'from' );
            $perPage = $request->get_param( 'perPage' );
            $page = $request->get_param( 'page' );
            $orderBy = $request->get_param( 'orderBy' );
            $order = $request->get_param( 'order' );
            $types = $request->get_param( 'types' );
            $search = $request->get_param( 'search' );
            // $searchScope   = $request->get_param('searchScope');
            $types = ( $types === 'all' ? [] : array_filter( array_map( 'trim', explode( ',', $types ) ) ) );
            $args = [
                'from'    => $from,
                'perPage' => $perPage,
                'page'    => $page,
                'orderBy' => $orderBy,
                'order'   => $order,
                'types'   => $types,
                'search'  => $search,
            ];
            $folder = App::getInstance()->getFolderByKey( $key, $args );
            if ( empty( $folder ) ) {
                return $this->errorResponse( 'Folder not found', self::HTTP_NOT_FOUND );
            }
            if ( is_wp_error( $folder ) ) {
                $message = $folder->get_error_message();
                return $this->errorResponse( $message, self::HTTP_INTERNAL_SERVER_ERROR );
            }
            $folder['breadcrumbs'] = array_reverse( App::getInstance()->getBreadcrumbByKey( $key ) );
            return $this->successResponse( $folder, 'Folder retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve folder' );
        }
    }

    public function tree( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $folderKey = $request->get_param( 'fileKey' );
            $shortcodeId = $request->get_param( 'shortcodeId' );
            $orderBy = $request->get_param( 'orderBy' ) ?? 'name';
            $order = $request->get_param( 'order' ) ?? 'ASC';
            $args = [
                'shortcodeId' => $shortcodeId,
                'orderBy'     => $orderBy,
                'order'       => $order,
            ];
            $folderTree = App::getInstance()->getFolderTree( $folderKey, $args );
            if ( is_wp_error( $folderTree ) ) {
                $message = $folderTree->get_error_message();
                if ( $json = json_decode( $message, true ) ) {
                    if ( isset( $json['error_summary'] ) ) {
                        $message = $json['error_summary'];
                    }
                }
                return $this->errorResponse( "Folder tree not found: {$message}", self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $folderTree, 'Folder tree retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve folder tree' );
        }
    }

    public function create( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $name = $request->get_param( 'name' );
            $parent = $request->get_param( 'fileKey' );
            $shortcodeId = $request->get_param( 'shortcodeId' ) ?? null;
            if ( empty( $name ) || empty( $parent ) ) {
                return $this->errorResponse( __( 'Folder name and parent file key are required', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
            }
            $shortcode = null;
            $isRootUpload = false;
            if ( $shortcodeId ) {
                $shortcode = Shortcode::getInstance()->getShortcode( $shortcodeId );
                if ( is_wp_error( $shortcode ) ) {
                    return $this->errorResponse( $shortcode->get_error_message(), 500 );
                }
            }
            if ( $parent === 'my-drive' || $parent === '/' || $parent === '' ) {
                if ( $shortcodeId ) {
                    if ( is_wp_error( $shortcode ) ) {
                        return $this->errorResponse( $shortcode->get_error_message(), 500 );
                    }
                    $shortcodeType = $shortcode['type'] ?? '';
                    if ( $shortcodeType === 'file-uploader' ) {
                        $files = $shortcode['data']['source']['fileKeys'] ?? [];
                        if ( empty( $files[0]['fileKey'] ) ) {
                            return $this->errorResponse( 'No files found in the shortcode for root upload.', 400 );
                        }
                        $parent = $files[0]['fileKey'];
                    } elseif ( $shortcodeType === 'file-browser' ) {
                        $isRootUpload = $shortcode['data']['advanced']['fileBrowser']['headerOptions']['rootUpload'] ?? false;
                        if ( !$isRootUpload ) {
                            return $this->errorResponse( 'Root upload is not enabled for this file browser shortcode.', 400 );
                        }
                        $parent = 'my-drive';
                    } else {
                        return $this->errorResponse( 'Invalid shortcode type for root folder creation.', 400 );
                    }
                }
            }
            $folder = App::getInstance()->newFolder( $name, $parent );
            if ( empty( $folder['fileKey'] ) ) {
                return $this->errorResponse( 'Failed to create folder. No file key returned.', self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( is_wp_error( $folder ) ) {
                return $this->errorResponse( $folder->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( $parent === 'my-drive' ) {
                if ( !empty( $shortcode ) ) {
                    $shortcodeType = $shortcode['type'] ?? '';
                    if ( empty( $shortcodeType ) ) {
                        return $this->errorResponse( 'Shortcode type is missing', 500 );
                    }
                    if ( $shortcodeType === 'file-browser' && $isRootUpload ) {
                        $folderKey = $folder['fileKey'];
                        $result = Shortcode::getInstance()->insertFile( $shortcodeId, $folderKey );
                        if ( is_wp_error( $result ) ) {
                            return $this->errorResponse( $result->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
                        }
                    }
                } elseif ( empty( $shortcodeId ) && ccpigdHasUserAccessPage( 'file_browser' ) === true ) {
                    $userAccess = ccpigdGetCurrentUserAccess();
                    if ( !empty( $userAccess['folders'] ) && is_array( $userAccess['folders'] ) && !empty( $userAccess['type'] ) && !empty( $userAccess['id'] ) && !empty( $userAccess['value'] ) ) {
                        $userAccess['folders'][] = $folder['fileKey'];
                        $type = $userAccess['type'];
                        $value = $userAccess['value'];
                        $id = $userAccess['id'];
                        UserAccess::getInstance()->updateRecord(
                            $id,
                            $type,
                            $value,
                            $userAccess['folders'],
                            $userAccess['pages']
                        );
                    }
                }
            }
            return $this->successResponse( $folder, 'Folder created successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to create folder' );
        }
    }

    public function getFolderPermission( WP_REST_Request $request ) {
        return ccpigdGetCurrentUserAccess();
    }

}
