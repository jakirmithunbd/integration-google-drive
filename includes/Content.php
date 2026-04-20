<?php

namespace CodeConfig\IGD;

use function array_slice;
use CodeConfig\IGD\App\App;
use CodeConfig\IGD\App\Authorization;
use CodeConfig\IGD\App\Stream;
use CodeConfig\IGD\Models\Attachment;
use CodeConfig\IGD\Models\Files;
use CodeConfig\IGD\Models\Shortcode;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\MimeTypeManager;
use CodeConfig\IGD\Utils\Singleton;
use function count;
use function in_array;
use function is_array;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Content {
    use Singleton;
    private $shortcodeId;

    private function doHooks() {
        add_filter( 'query_vars', [$this, 'addQueryVars'] );
        add_action( 'template_redirect', [$this, 'redirectTemplate'] );
    }

    public function addQueryVars( $vars ) {
        return array_merge( $vars, [
            'ccpigd-share',
            'ccpigd-thumbnail',
            'ccpigd-action',
            'ccpigd-key',
            'ccpigd-name',
            'ccpigd-ext',
            'authorization',
            'code'
        ] );
    }

    public function redirectTemplate() {
        foreach ( [
            'authorization'    => fn( $val ) => $this->doingAuth( $val, get_query_var( 'code', '' ) ),
            'ccpigd-thumbnail' => fn( $val ) => $this->thumbnailHash( $val ),
            'ccpigd-action'    => fn( $val ) => $this->url(
                $val,
                get_query_var( 'ccpigd-key', 'full' ),
                get_query_var( 'ccpigd-name', 'unknown' ),
                get_query_var( 'ccpigd-ext', 'jpg' )
            ),
        ] as $queryVar => $callback ) {
            $value = get_query_var( $queryVar, false );
            if ( $value ) {
                $callback( sanitize_text_field( wp_unslash( $value ) ) );
                return;
            }
        }
    }

    private function url(
        $action,
        $key,
        $name,
        $ext
    ) {
        if ( $action === 'authorize' ) {
            $this->authorization( $key );
            return;
        }
        $explodedAction = explode( '-', $action );
        $action = reset( $explodedAction );
        $shortcodeId = $explodedAction[1] ?? null;
        if ( $action === 'thumbnail' ) {
            $this->thumbnail(
                $key,
                $name,
                $ext,
                $shortcodeId
            );
            exit;
        } elseif ( $action === 'attachment' ) {
            $this->attachment(
                $key,
                $name,
                $ext,
                $shortcodeId
            );
            exit;
        } elseif ( $action === 'preview' ) {
            $this->preview(
                $key,
                $name,
                $ext,
                $shortcodeId
            );
            exit;
        } elseif ( $action === 'share' ) {
            $this->share(
                $key,
                $name,
                $ext,
                $shortcodeId
            );
            exit;
        } elseif ( $action === 'download' ) {
            $this->download(
                $key,
                $name,
                $ext,
                $shortcodeId
            );
            exit;
        } else {
            wp_die( 'Invalid action specified.', 'Error', [
                'response' => 400,
            ] );
        }
    }

    /* -------------------------
     * Helpers
     * ------------------------- */
    private function safeRedirect(
        string $url,
        $cache = HOUR_IN_SECONDS,
        $status = 302,
        $referrer = 'no-referrer'
    ) : void {
        if ( !empty( $referrer ) ) {
            header( "Referrer-Policy: {$referrer}" );
        }
        header( "Cache-Control: public, max-age={$cache}" );
        wp_safe_redirect( $url, $status, CCPIGD_NAME . ' Safe Redirect' );
        exit;
    }

    private function safeProxy( string $url, $cache = HOUR_IN_SECONDS ) : void {
        header( "Referrer-Policy: no-referrer" );
        $response = wp_remote_get( $url, [
            'timeout'     => 15,
            'redirection' => 5,
            'sslverify'   => false,
        ] );
        if ( is_wp_error( $response ) ) {
            $this->safeRedirect( $this->getUnknownIcon( 'image/jpeg' ), 0 );
            exit;
        }
        $data = wp_remote_retrieve_body( $response );
        $contentType = wp_remote_retrieve_header( $response, 'content-type' );
        // Whitelist of safe content types to prevent XSS and other security issues
        $allowedContentTypes = [
            'application/vnd.google-apps.spreadsheet',
            'application/vnd.google-apps.folder',
            'application/vnd.google-apps.document',
            'application/vnd.google-apps.presentation',
            'application/vnd.google-apps.script',
            'application/vnd.google-apps.form',
            'application/vnd.google-apps.drawing',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/pdf',
            'text/plain',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'audio/mpeg',
            'audio/wav',
            'video/mp4',
            'video/x-msvideo'
        ];
        // Extract the base content type (remove charset and other parameters)
        $baseContentType = ( $contentType ? explode( ';', $contentType )[0] : '' );
        $baseContentType = trim( $baseContentType );
        // Validate content type is in the allowed list
        if ( !in_array( $baseContentType, $allowedContentTypes, true ) ) {
            $this->safeRedirect( $this->getUnknownIcon( 'image/jpeg' ), 0 );
            exit;
        }
        if ( $data ) {
            header( "Content-Type: {$baseContentType}" );
            header( "Cache-Control: public, max-age={$cache}" );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $data;
        } else {
            $this->safeRedirect( $this->getUnknownIcon( 'image/jpeg' ), 0 );
        }
        exit;
    }

    private function denyAccess( string $message = 'Access denied!', int $status = 403 ) : void {
        ccpigdGetTemplate( 'notice-card/permission-denied', [
            'title'       => __( 'Error', 'integration-google-drive' ),
            'description' => $message,
            'card_status' => 'error',
        ] );
        exit;
    }

    private function getUnknownIcon( string $mimeType = 'application/octet-stream' ) : string {
        // return CCPIGD_ASSETS . '/images/icons/file.png';
        return 'https://drive-thirdparty.googleusercontent.com/128/type/' . $mimeType;
    }

    /* -------------------------
     * Download
     * ------------------------- */
    public function download(
        $key,
        $name,
        $ext,
        $shortcodeId = null
    ) {
        $explodedKey = explode( '-', $key );
        $fileKey = $explodedKey[0] ?? null;
        $linkKey = $explodedKey[1] ?? null;
        if ( !empty( $fileKey ) && !empty( $linkKey ) ) {
            return $this->downloadWithGeneratedLink(
                $fileKey,
                $linkKey,
                $name,
                $ext,
                $shortcodeId
            );
        }
        $this->urlValidation( $key, $name, $ext );
        $this->checkPermission( $shortcodeId, $key, 'download' );
        $downloadLink = App::getInstance()->download( $key, $ext );
        if ( is_wp_error( $downloadLink ) ) {
            ccpigdGetTemplate( 'notice-card/permission-denied', [
                'title'       => __( 'Error', 'integration-google-drive' ),
                'description' => $downloadLink->get_error_message(),
                'card_status' => 'error',
            ] );
            exit;
        }
        wp_safe_redirect( $downloadLink );
        exit;
    }

    private function downloadWithGeneratedLink(
        $fileKey,
        $linkKey,
        $name,
        $ext,
        $shortcodeId = null
    ) {
        if ( empty( $fileKey ) || empty( $linkKey ) ) {
            wp_die( 'File key is required.', 'Error', [
                'response' => 400,
            ] );
        }
        $this->urlValidation( $fileKey, $name, $ext );
        $password = '';
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === 'POST' && isset( $_POST['ccpigd-password-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccpigd-password-nonce'] ) ), 'ccpigd_password_nonce' ) && isset( $_POST['ccpigd-download-password'] ) ) {
            $password = sanitize_text_field( wp_unslash( $_POST['ccpigd-download-password'] ) );
        }
        $isValidLink = Files::getInstance()->validateDownloadLink( "{$fileKey}-{$linkKey}", $password );
        if ( empty( $isValidLink ) ) {
            ccpigdGetTemplate( 'notice-card/permission-denied', [
                'title'       => __( 'Invalid Download URL', 'integration-google-drive' ),
                'description' => __( 'Invalid or expired download link.', 'integration-google-drive' ),
                'card_status' => 'error',
            ] );
            exit;
        }
        if ( is_wp_error( $isValidLink ) ) {
            if ( $isValidLink->get_error_code() === 'password_required' || $isValidLink->get_error_code() === 'invalid_password' ) {
                ccpigdGetTemplate( 'content-password', [
                    'code'      => $isValidLink->get_error_code(),
                    'message'   => $isValidLink->get_error_message(),
                    'fileKey'   => $fileKey,
                    'name'      => $name,
                    'fieldName' => 'ccpigd-download-password',
                ] );
                exit;
            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not output, used internally
                wp_die( $isValidLink->get_error_message(), __( 'Error', 'integration-google-drive' ), [
                    'response' => 400,
                ] );
            }
        }
        $downloadLink = App::getInstance()->download( $fileKey, $ext );
        if ( empty( $downloadLink ) ) {
            ccpigdGetTemplate( 'notice-card/permission-denied', [
                'title'       => __( 'Error', 'integration-google-drive' ),
                'description' => __( 'Something went wrong to download the file.', 'integration-google-drive' ),
                'card_status' => 'error',
            ] );
            exit;
        }
        if ( is_wp_error( $downloadLink ) ) {
            ccpigdGetTemplate( 'notice-card/permission-denied', [
                'title'       => __( 'Error', 'integration-google-drive' ),
                'description' => $downloadLink->get_error_message(),
                'card_status' => 'error',
            ] );
            exit;
        }
        wp_safe_redirect( $downloadLink );
        exit;
    }

    /* -------------------------
     * Preview
     * ------------------------- */
    private function preview(
        $key,
        $name,
        $ext,
        $shortcodeId = null
    ) : void {
        $isVideo = MimeTypeManager::isVideo( $ext );
        $this->urlValidation( $key, $name, $ext );
        $this->checkPermission( $shortcodeId, $key, 'preview' );
        if ( MimeTypeManager::isAudio( $ext ) ) {
            new Stream($key);
            exit;
        }
        if ( $isVideo && !empty( $shortcodeId ) ) {
            $shortcode = Shortcode::getInstance()->getShortcode( $shortcodeId );
            $shortcodeType = ccpigdToCamelCase( $shortcode['type'] ?? '' );
            $isSecureVideo = $shortcode['data']['advanced'][$shortcodeType]['secureVideoPlayback'] ?? false;
            if ( $isSecureVideo ) {
                $referrer = wp_get_raw_referer();
                if ( empty( $referrer ) || !str_contains( $referrer, home_url() ) ) {
                    $this->denyAccess( 'Direct access to video preview is denied.', 403 );
                }
                new Stream($key);
                exit;
            }
        }
        $file = ccpigdGetFileByKey( $key );
        if ( is_wp_error( $file ) ) {
            $this->safeRedirect( $this->getUnknownIcon( 'image/jpeg' ), 0 );
        }
        $previewLink = '';
        if ( empty( $previewLink ) && (ccpigdGetCurrentUserAccess() || Attachment::exists( $file['fileKey'] )) ) {
            $previewLink = App::getInstance( $file['accountId'] )->preview( $file['fileKey'] );
        }
        if ( is_wp_error( $previewLink ) ) {
            $this->denyAccess( $previewLink->get_error_message(), 500 );
        }
        if ( empty( $previewLink ) ) {
            $this->safeRedirect( $this->getUnknownIcon( $file['mimeType'] ?? 'image/jpeg' ), 0 );
        }
        $referrer = ( $isVideo ? '' : 'no-referrer' );
        $this->safeRedirect(
            $previewLink,
            0,
            302,
            $referrer
        );
    }

    /* -------------------------
     * Attachment
     * ------------------------- */
    private function attachment(
        string $key,
        string $name,
        ?string $ext,
        ?int $shortcodeId = null
    ) : void {
        $explodeName = explode( '-', $name );
        $size = end( $explodeName );
        $size = str_replace( [
            'thumbnail',
            'medium',
            'large',
            'full'
        ], [
            'lg',
            'xl',
            '4xl',
            '5xl'
        ], $size );
        $isThumbnail = in_array( $size, [
            'xs',
            'sm',
            'md',
            'lg',
            'xl'
        ], true );
        if ( MimeTypeManager::isImage( $ext ) || $isThumbnail ) {
            $this->thumbnail(
                $key,
                $name,
                $ext,
                null,
                '5xl'
            );
            exit;
        } elseif ( MimeTypeManager::isAudio( $ext ) ) {
            new Stream($key);
            exit;
        } elseif ( MimeTypeManager::isVideo( $ext ) ) {
            if ( Helpers::getSetting( 'advanced.videoSecurePlayback', false ) ) {
                $referrer = wp_get_raw_referer();
                if ( empty( $referrer ) || !str_contains( $referrer, home_url() ) ) {
                    $this->denyAccess( 'Direct access to video attachment is denied.', 403 );
                }
            }
            new Stream($key);
            exit;
        } else {
            $this->preview(
                $key,
                $name,
                $ext,
                null
            );
            exit;
        }
    }

    private function processThumbnail( $file, string $size = '5xl', string $mimeType = 'application/octet-stream' ) : void {
        if ( !is_array( $file ) || empty( $file ) ) {
            $this->safeRedirect( $this->getUnknownIcon( $file['mimeType'] ?? $mimeType ), DAY_IN_SECONDS );
        }
        $lifeTime = Helpers::checkLifeTime( $file['updatedAt'] ?? '' );
        if ( $lifeTime <= 0 || empty( $file['thumbnail'] ) ) {
            $file = App::getInstance( $file['accountId'] )->getFile( $file['id'], $file['accountId'], true );
            $lifeTime = Helpers::checkLifeTime( $file['updatedAt'] ?? '' );
        }
        if ( empty( $file ) || empty( $file['thumbnail'] ) ) {
            $this->safeRedirect( $this->getUnknownIcon( $file['mimeType'] ?? $mimeType ), DAY_IN_SECONDS );
        }
        $size = ccpigdSizeToString( $size );
        $thumbnailUrl = str_replace( '=s220', ( $size ? "={$size}" : '' ), $file['thumbnail'] );
        $redirection = Helpers::getSetting( 'integrations.mediaLibrary.redirection', true );
        if ( $redirection ) {
            $this->safeRedirect( apply_filters( 'ccpigd_thumbnail_url', $thumbnailUrl ), $lifeTime );
        } else {
            $this->safeProxy( apply_filters( 'ccpigd_thumbnail_url', $thumbnailUrl ), $lifeTime );
        }
    }

    public function thumbnail(
        $key,
        $name,
        $ext,
        $shortcodeId = null,
        $size = '2xl'
    ) {
        $parts = explode( '-', $name );
        $availableSizes = ccpigdGetAvailableThumbnailSizes();
        $sizeKeys = array_map( 'strval', array_keys( $availableSizes ) );
        if ( count( $parts ) > 1 ) {
            $possibleSize = strtolower( end( $parts ) );
            $possibleSize = str_replace( [
                'thumbnail',
                'medium',
                'large',
                'full'
            ], [
                'lg',
                'xl',
                '4xl',
                '5xl'
            ], $possibleSize );
            if ( in_array( $possibleSize, $sizeKeys, true ) ) {
                $size = $possibleSize;
                $name = strtolower( implode( '-', array_slice( $parts, 0, -1 ) ) );
            }
        }
        if ( $ext === 'json' || $ext === 'zip' || $ext === 'script' ) {
            $folderIcon = $this->getUnknownIcon( ccpigdGetMimeTypeByExtension( $ext ) );
            header( "Content-Type: image/jpeg" );
            header( "Cache-Control: public, max-age=" . DAY_IN_SECONDS );
            wp_safe_redirect( $folderIcon, 302 );
            exit;
        }
        $file = ccpigdGetFileByKey( $key );
        if ( is_wp_error( $file ) ) {
            $fileMimeType = ( is_wp_error( $file ) ? 'unknown' : $file['mimeType'] ?? 'unknown' );
            $folderIcon = $this->getUnknownIcon( $fileMimeType );
            wp_safe_redirect( $folderIcon, 302 );
            exit;
        }
        if ( $file['mimeType'] === 'application/vnd.google-apps.folder' ) {
            $folderIcon = str_replace( '32', '128', $file['icon'] ?? '' ) ?? $this->getUnknownIcon( $file['mimeType'] );
            wp_safe_redirect( $folderIcon, 302 );
            exit;
        }
        $basename = $file['additionalData']['baseName'] ?? '';
        $cleanName = ccpigdTitleToUrlSlug( $basename );
        $cleanName = str_replace( '_', '-', $cleanName );
        $decodedName = urldecode( $name );
        $decodedName = str_replace( '_', '-', $decodedName );
        if ( $decodedName !== $cleanName ) {
            wp_safe_redirect( $file['icon'] ?? '', 302 );
            exit;
        }
        $this->checkPermission( $shortcodeId, $key, 'thumbnail' );
        if ( $file['mimeType'] === 'application/vnd.google-apps.shortcut' && !empty( $file['additionalData']['shortcutDetails']['targetId'] ?? '' ) ) {
            $file = App::getInstance()->getFile( $file['additionalData']['shortcutDetails']['targetId'], $file['accountId'] );
            if ( is_wp_error( $file ) ) {
                $this->safeRedirect( $this->getUnknownIcon( 'image/jpeg' ), 0 );
                exit;
            }
        }
        $this->processThumbnail( $file, $size, 'image/jpeg' );
    }

    private function share(
        $combinedKey,
        $name,
        $ext,
        $shortcodeId = null
    ) : void {
        $explodedKey = explode( '-', $combinedKey );
        $fileKey = $explodedKey[0] ?? null;
        $linkKey = $explodedKey[1] ?? null;
        if ( empty( $fileKey ) || empty( $linkKey ) ) {
            wp_die( 'File key is required.', 'Error', [
                'response' => 400,
            ] );
        }
        $this->urlValidation( $fileKey, $name, $ext );
        $password = '';
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === 'POST' && isset( $_POST['ccpigb-password-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccpigb-password-nonce'] ) ), 'ccpigb_password_nonce' ) && isset( $_POST['ccpigb-download-password'] ) ) {
            $password = sanitize_text_field( wp_unslash( $_POST['ccpigb-download-password'] ) );
        }
        $isValidLink = Files::getInstance()->validateSharedLink( "{$fileKey}-{$linkKey}", $password );
        if ( empty( $isValidLink ) ) {
            wp_die( 'Invalid or expired share link.', 'Error', [
                'response' => 400,
            ] );
        }
        if ( is_wp_error( $isValidLink ) ) {
            if ( $isValidLink->get_error_code() === 'password_required' || $isValidLink->get_error_code() === 'invalid_password' ) {
                ccpigdGetTemplate( 'content-password', [
                    'code'      => $isValidLink->get_error_code(),
                    'message'   => $isValidLink->get_error_message(),
                    'fileKey'   => $fileKey,
                    'name'      => $name,
                    'fieldName' => 'ccpigb-download-password',
                ] );
                exit;
            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not output, used internally
                wp_die( $isValidLink->get_error_message(), 'Error', [
                    'response' => 400,
                ] );
            }
        }
        $embedLink = App::getInstance()->preview( $fileKey );
        if ( $ext === 'folder' || $ext === 'zip' ) {
            wp_safe_redirect( $embedLink, 302 );
            exit;
        }
        echo '<iframe src="' . esc_url( $embedLink ) . '" width="100%" height="100%" style="border:none;"></iframe>';
        exit;
    }

    private function authorization( $key ) {
        if ( empty( $key ) ) {
            $this->denyAccess( 'Invalid authorization code!', 400 );
        }
        Authorization::getInstance()->doingAuth( urldecode( base64_decode( $key ) ) );
    }

    private function doingAuth( $action, $code ) {
        if ( 'integration-google-drive' !== $action || empty( $code ) ) {
            return;
        }
        Authorization::getInstance()->doingAuth( $code );
    }

    private function checkPermission( $shortcodeId, $key, $action ) {
        if ( ccpigdHasUserAccessPage( 'file_browser' ) ) {
            return true;
        }
        $mediaLibraryFiles = Helpers::getSetting( 'integrations.mediaLibrary.folders', [] );
        if ( !empty( $mediaLibraryFiles ) ) {
            if ( Helpers::validateFileKey( $key, $mediaLibraryFiles ) ) {
                return true;
            }
        }
        if ( empty( $shortcodeId ) ) {
            ccpigdGetTemplate( 'notice-card/permission-denied', [
                'title'       => __( 'Permission Denied', 'integration-google-drive' ),
                'description' => __( 'You do not have permission to access this file. Shortcode ID is missing.', 'integration-google-drive' ),
                'card_status' => 'error',
            ] );
            exit;
        }
        if ( Helpers::hasShortcodePermission( $shortcodeId, $action, $key ) ) {
            return true;
        }
        ccpigdGetTemplate( 'notice-card/permission-denied', [
            'title'       => "#{$shortcodeId} - " . __( 'Permission Denied', 'integration-google-drive' ),
            'description' => __( 'You do not have permission to access this file.', 'integration-google-drive' ),
            'card_status' => 'error',
        ] );
        exit;
    }

    private function urlValidation( $key, $name, $ext ) {
        $file = ccpigdGetFileByKey( $key );
        if ( is_wp_error( $file ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not output, used internally
            wp_die( $file->get_error_message(), 'Error | ' . $file->get_error_code(), [
                'response' => 404,
            ] );
        }
        $suffixes = [
            '-xs',
            '-sm',
            '-md',
            '-lg',
            '-xl',
            '-2xl',
            '-3xl',
            '-4xl',
            '-5xl',
            '-thumbnail',
            '-medium',
            '-large',
            '-full'
        ];
        $cleanSlug = static function ( string $value ) use($suffixes) : string {
            return ccpigdTitleToUrlSlug( str_replace( $suffixes, '', $value ) );
        };
        $cleanName = $cleanSlug( $file['additionalData']['baseName'] ?? $file['name'] ?? '' );
        $cleanName = str_replace( '_', '-', $cleanName );
        $name = $cleanSlug( urldecode( $name ) );
        $name = str_replace( '_', '-', $name );
        if ( $name !== $cleanName ) {
            wp_safe_redirect( $file['icon'] ?? $this->getUnknownIcon( 'application/octet-stream' ), 302 );
            exit;
        }
        if ( $ext !== $file['extension'] && ($ext !== 'zip' && $file['extension'] === 'folder') ) {
            wp_safe_redirect( $file['icon'] ?? $this->getUnknownIcon( 'application/octet-stream' ), 302 );
            exit;
        }
    }

    private function thumbnailHash( $dataString ) {
        if ( $dataString ) {
            $thumbnail = Helpers::decode( $dataString );
            $thumbnail = json_decode( $thumbnail, true );
            $mimeType = $thumbnail['mimeType'] ?? 'application/octet-stream';
            $unknownIcon = $this->getUnknownIcon( $mimeType );
            if ( empty( $thumbnail['key'] ) || empty( $thumbnail['sz'] ) ) {
                $this->safeRedirect( $unknownIcon, 0 );
                exit;
            }
            $file = App::getInstance()->getFileByKey( $thumbnail['key'] ?? '' );
            if ( is_wp_error( $file ) || empty( $file ) ) {
                $this->safeRedirect( $unknownIcon, 0 );
            }
            $this->processThumbnail( $file, '3xl', 'image/jpeg' );
        }
    }

}
