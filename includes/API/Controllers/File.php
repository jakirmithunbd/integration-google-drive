<?php

namespace CodeConfig\IGD\API\Controllers;

use function CodeConfig\ccpigd_fs;
use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Models\Files as ModelFiles;
use CodeConfig\IGD\Models\Shortcode;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class File extends BaseController {
    public function __construct() {
        parent::__construct( 'ccpigd/v1', 'file' );
    }

    public function register_routes() : void {
        register_rest_route( $this->namespace, "{$this->rest_base}/rename", [[
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rename'],
            'permission_callback' => [$this, 'managePermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/open-in-drive/(?P<fileKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'openInDrive'],
            'permission_callback' => [$this, 'managePermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/share/(?P<fileKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'shareLink'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'expiry'      => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 3600,
                ],
                'password'    => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => null,
                ],
                'shortcodeId' => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => null,
                ],
            ],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/download/(?P<fileKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'downloadLink'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'expiry'      => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 3600,
                ],
                'limit'       => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => null,
                ],
                'password'    => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => null,
                ],
                'shortcodeId' => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => null,
                ],
            ],
        ]] );
        // register_rest_route($this->namespace, "{$this->rest_base}/search", [
        //     [
        //         'methods'             => WP_REST_Server::READABLE,
        //         'callback'            => [$this, 'search'],
        //         'permission_callback' => [$this, 'managePermission'],
        //     ]
        // ]);
        register_rest_route( $this->namespace, "{$this->rest_base}/upload", [[
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'upload'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'name'        => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'type'        => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'description' => [
                    'type'     => 'string',
                    'required' => false,
                ],
                'folderKey'   => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'size'        => [
                    'type'     => 'integer',
                    'required' => true,
                ],
            ],
        ], [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getUploadedFile'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'fileId'      => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'uploadId'    => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'folderKey'   => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'shortcodeId' => [
                    'type'     => 'string',
                    'required' => false,
                ],
            ],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/by-keys", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getFiles'],
            'permission_callback' => [$this, 'managePermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/(?P<fileKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => [$this, 'managePermission'],
        ]] );
        register_rest_route( $this->namespace, "{$this->rest_base}/", [[
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'managePermission'],
        ]] );
    }

    public function upload( WP_REST_Request $request ) {
        try {
            $name = sanitize_file_name( $request->get_param( 'name' ) ?? '' );
            $type = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
            $description = sanitize_text_field( $request->get_param( 'description' ) ?? '' );
            $folderKey = sanitize_text_field( $request->get_param( 'folderKey' ) ?? '' );
            $size = absint( $request->get_param( 'size' ) ?? 0 );
            $postId = absint( $request->get_param( 'postId' ) ?? 0 );
            $queueIndex = absint( $request->get_param( 'queueIndex' ) ?? 0 );
            $shortcodeId = sanitize_text_field( $request->get_param( 'shortcodeId' ) ?? '' );
            if ( empty( $name ) || empty( $type ) || empty( $folderKey ) || empty( $size ) ) {
                return $this->errorResponse( 'Missing required parameters', self::HTTP_BAD_REQUEST );
            }
            if ( !empty( $shortcodeId ) ) {
                $shortcode = Shortcode::getInstance()->getShortcode( $shortcodeId );
                if ( is_wp_error( $shortcode ) ) {
                    return $this->errorResponse( $shortcode->get_error_message(), 500 );
                }
                if ( $folderKey == '/' || empty( $folderKey ) || $folderKey === 'my-drive' ) {
                    $files = $shortcode['data']['source']['fileKeys'] ?? [];
                    if ( empty( $files ) ) {
                        return $this->errorResponse( 'No files found in the shortcode for root upload.', 400 );
                    }
                    if ( $shortcode['type'] === 'file-browser' ) {
                        if ( $shortcode['data']['advanced']['fileBrowser']['headerOptions']['rootUpload'] ?? false ) {
                            $folderKey = 'my-drive';
                        } else {
                            return $this->errorResponse( 'Root upload is not allowed for this shortcode.', self::HTTP_FORBIDDEN );
                        }
                    }
                    if ( $shortcode['type'] === 'file-uploader' ) {
                        $folderKey = $files[0]['fileKey'];
                    }
                }
                $template = $shortcode['data']['advanced']['fileUploader']['renameFile'] ?? '';
                if ( $template ) {
                    $name = $this->generateFileNameFromTemplate(
                        $template,
                        $name,
                        $queueIndex,
                        $postId
                    );
                }
            }
            $resumeUrl = App::getInstance()->upload(
                $name,
                $type,
                $folderKey,
                '',
                $description,
                $size
            );
            if ( is_wp_error( $resumeUrl ) ) {
                return $this->errorResponse( $resumeUrl->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $resumeUrl, 'Resume URL generated successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to generate resume URL' );
        }
    }

    public function getUploadedFile( WP_REST_Request $request ) {
        try {
            $fileId = sanitize_text_field( $request->get_param( 'fileId' ) ?? '' );
            $token = sanitize_text_field( $request->get_param( 'uploadId' ) ?? '' );
            $folderKey = sanitize_text_field( $request->get_param( 'folderKey' ) ?? '' );
            $shortcodeId = sanitize_text_field( $request->get_param( 'shortcodeId' ) ?? '' );
            if ( empty( $fileId ) || empty( $token ) || empty( $folderKey ) ) {
                return $this->errorResponse( 'Missing required parameters', self::HTTP_BAD_REQUEST );
            }
            $file = App::getInstance()->getUploadedFile( $fileId, $token, $folderKey );
            if ( is_wp_error( $file ) ) {
                return $this->errorResponse( $file->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( empty( $file ) ) {
                return $this->errorResponse( 'File not found', self::HTTP_NOT_FOUND );
            }
            if ( !empty( $shortcodeId ) && !is_wp_error( $file ) && $folderKey === 'my-drive' ) {
                $shortcode = Shortcode::getInstance()->getShortcode( $shortcodeId );
                $isRootUpload = $shortcode['data']['advanced']['fileBrowser']['headerOptions']['rootUpload'] ?? false;
                $fileKey = $file['fileKey'] ?? '';
                if ( $shortcode['type'] === 'file-browser' && $isRootUpload && $fileKey ) {
                    Shortcode::getInstance()->insertFile( $shortcodeId, $fileKey );
                }
            }
            return $this->successResponse( $file, 'File retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve uploaded file' );
        }
    }

    public function get( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $fileKey = $request->get_param( 'fileKey' );
            $from = $request->get_param( 'from' ) ?? 'cache';
            if ( empty( $fileKey ) ) {
                return $this->errorResponse( 'File key is required', self::HTTP_BAD_REQUEST );
            }
            $file = App::getInstance()->getFileByKey( $fileKey, $from === 'server' );
            if ( empty( $file ) ) {
                return $this->errorResponse( 'File not found', self::HTTP_NOT_FOUND );
            }
            if ( is_wp_error( $file ) ) {
                return $this->errorResponse( $file->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $file, 'File retrieved successfully' );
            return $this->errorResponse( 'File not found', self::HTTP_NOT_FOUND );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve file' );
        }
    }

    public function delete( WP_REST_Request $request ) {
        try {
            $fileKeys = $request->get_param( 'fileKeys' );
            $shortcodeId = $request->get_param( 'shortcodeId' );
            $isMigrateAttachment = $request->get_param( 'isMigrateAttachment' ) ?? false;
            if ( empty( $fileKeys ) ) {
                return $this->errorResponse( 'File key is required', self::HTTP_BAD_REQUEST );
            }
            // if ($isMigrateAttachment) {
            //     Importer::importFileToMediaLibrary($fileKeys, true);
            // } else {
            //     foreach ($fileKeys as $fileKey) {
            //         if ($attachmentId = Attachment::exists($fileKey)) {
            //             wp_delete_attachment($attachmentId, true);
            //         }
            //     }
            // }
            $file = App::getInstance()->delete( $fileKeys );
            if ( is_wp_error( $file ) ) {
                return $this->errorResponse( $file->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $file, 'File deleted successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to delete file' );
        }
    }

    public function rename( WP_REST_Request $request ) {
        try {
            $fileKey = $request->get_param( 'fileKey' );
            $name = $request->get_param( 'name' );
            $shortcodeId = $request->get_param( 'shortcodeId' );
            if ( empty( $fileKey ) || empty( $name ) ) {
                return $this->errorResponse( 'File key and new name are required', self::HTTP_BAD_REQUEST );
            }
            $folder = App::getInstance()->rename( $fileKey, $name );
            if ( empty( $folder ) ) {
                return $this->errorResponse( 'Failed to rename folder', self::HTTP_INTERNAL_SERVER_ERROR );
            }
            if ( is_wp_error( $folder ) ) {
                return $this->errorResponse( $folder->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $folder, 'Folder renamed successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to rename folder' );
        }
    }

    public function openInDrive( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $fileKey = $request->get_param( 'fileKey' );
            if ( empty( $fileKey ) ) {
                return $this->errorResponse( 'File key is required', self::HTTP_BAD_REQUEST );
            }
            $shareLink = App::getInstance()->preview( $fileKey );
            if ( is_wp_error( $shareLink ) ) {
                return $this->errorResponse( $shareLink->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $shareLink, 'Preview retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve preview' );
        }
    }

    public function shareLink( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $fileKey = $request->get_param( 'fileKey' );
            $expireIn = $request->get_param( 'expireIn' );
            $password = $request->get_param( 'password' );
            $shortcodeId = $request->get_param( 'shortcodeId' );
            if ( empty( $fileKey ) ) {
                return $this->errorResponse( 'File key is required', self::HTTP_BAD_REQUEST );
            }
            $shareLink = App::getInstance()->generateSharedLink( $fileKey, [
                'expireIn' => $expireIn,
                'password' => $password,
            ] );
            if ( is_wp_error( $shareLink ) ) {
                return $this->errorResponse( $shareLink->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $shareLink, 'Share link retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve share link' );
        }
    }

    public function downloadLink( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $fileKey = $request->get_param( 'fileKey' );
            $expireIn = $request->get_param( 'expireIn' );
            $limit = $request->get_param( 'limit' );
            $password = $request->get_param( 'password' );
            $exactFormat = $request->get_param( 'exactFormat' );
            $shortcodeId = $request->get_param( 'shortcodeId' );
            if ( empty( $fileKey ) ) {
                return $this->errorResponse( 'File key is required', self::HTTP_BAD_REQUEST );
            }
            $shareLink = App::getInstance()->generateDownloadLink( $fileKey, [
                'expireIn'    => $expireIn,
                'password'    => $password,
                'limit'       => $limit,
                'exactFormat' => $exactFormat,
            ] );
            if ( is_wp_error( $shareLink ) ) {
                return $this->errorResponse( $shareLink->get_error_message(), self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $shareLink, 'Download link retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve download link' );
        }
    }

    public function getFiles( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $fileKeys = $request->get_param( 'fileKeys' );
            $fileKeys = explode( ',', $fileKeys );
            if ( empty( $fileKeys ) ) {
                return $this->errorResponse( 'File keys are required', self::HTTP_BAD_REQUEST );
            }
            $files = ModelFiles::getInstance()->getFilesByKeys( $fileKeys );
            if ( is_wp_error( $files ) ) {
                return $this->errorResponse( 'Files not found', self::HTTP_INTERNAL_SERVER_ERROR );
            }
            return $this->successResponse( $files, 'Files retrieved successfully' );
        } catch ( Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve files' );
        }
    }

    private function generateFileNameFromTemplate(
        string $template,
        string $name,
        int $queueIndex = 0,
        int $postId = 0
    ) : string {
        $fileInfo = explode( '.', $name );
        $extension = array_pop( $fileInfo );
        $baseName = implode( '.', $fileInfo );
        $currentDate = gmdate( 'Y-m-d' );
        $currentTime = gmdate( 'H-i-s' );
        $uniqueId = uniqid();
        $queueIndex = $queueIndex ?? '0';
        $postId = $postId ?? '0';
        $postTitle = get_the_title( $postId );
        $postTitle = ( !empty( $postTitle ) ? sanitize_title( $postTitle ) : "post-{$postId}" );
        $newName = str_replace( [
            '{file_name}',
            '{file_extension}',
            '{current_date}',
            '{current_time}',
            '{unique_id}',
            '{queue_index}',
            '{post_id}',
            '{post_title}'
        ], [
            $baseName,
            $extension,
            $currentDate,
            $currentTime,
            $uniqueId,
            $queueIndex,
            $postId,
            $postTitle
        ], $template );
        return "{$newName}.{$extension}";
    }

}
