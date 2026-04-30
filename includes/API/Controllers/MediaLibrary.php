<?php

namespace CodeConfig\IGD\API\Controllers;

use function CodeConfig\ccpigd_fs;
use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Integrations\MediaLibrary__premium_only as IntegrationsMediaLibrary;
use CodeConfig\IGD\Models\Attachment;
use CodeConfig\IGD\Utils\Helpers;
use Exception;
use MasterStudy\Lms\Plugin\Media;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class MediaLibrary extends BaseController {
    public function __construct() {
        parent::__construct( 'ccpigd/v1', 'media-library' );
    }

    public function register_routes() : void {
        // Clear all attachments.
        register_rest_route( $this->namespace, $this->rest_base . '/clear', [[
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'deleteAttachment'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [],
        ]] );
    }

    /**
     * Clear all Dropbox attachments from Media Library.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response REST response object.
     */
    public function deleteAttachment( WP_REST_Request $request ) : WP_REST_Response {
        try {
            Attachment::clearAttachments();
            return $this->successResponse( [], __( 'All attachments cleared successfully.', 'integration-google-drive' ) );
        } catch ( Exception $e ) {
            return $this->errorResponse( $e->getMessage(), self::HTTP_INTERNAL_SERVER_ERROR );
        }
    }

}
