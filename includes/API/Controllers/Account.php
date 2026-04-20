<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\App\Account as AppAccount;
use CodeConfig\IGD\App\Accounts;
use CodeConfig\IGD\App\Client;
use CodeConfig\IGD\Utils\Helpers;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Account extends BaseController {
    public function __construct() {
        parent::__construct( 'ccpigd/v1', 'account' );
    }

    public function register_routes() : void {
        register_rest_route( $this->namespace, "{$this->rest_base}/auth-url", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getAuthUrl'],
            'permission_callback' => [$this, 'manageFilePermission'],
            'args'                => [
                'accountKey'     => [
                    'required'    => false,
                    'type'        => 'string',
                    'description' => 'The unique key of the account to reconnect. If not provided, a new auth URL will be generated for a new account connection.',
                ],
                'appKey'         => [
                    'required'    => false,
                    'type'        => 'string',
                    'description' => 'The application key for the account. If not provided, the default app key will be used.',
                ],
                'appSecret'      => [
                    'required'    => false,
                    'type'        => 'string',
                    'description' => 'The application secret for the account. If not provided, the default app secret will be used.',
                ],
                'connectionType' => [
                    'required'    => false,
                    'type'        => 'string',
                    'default'     => null,
                    'description' => 'The type of connection for the account (e.g., "standard", "premium"). This can be used to determine the level of access or features available for the account.',
                ],
            ],
        ] );
        register_rest_route( $this->namespace, "{$this->rest_base}/all", [
            "methods"             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getAllAccounts'],
            'permission_callback' => [$this, 'managePermission'],
        ] );
        register_rest_route( $this->namespace, "{$this->rest_base}/switch", [
            "methods"             => WP_REST_Server::EDITABLE,
            "callback"            => [$this, "switch"],
            "permission_callback" => [$this, "managePermission"],
        ] );
        register_rest_route( $this->namespace, "{$this->rest_base}/(?P<accountKey>[^/]+)", [[
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getAccount'],
            'permission_callback' => [$this, 'managePermission'],
            'args'                => [
                'accountKey' => [
                    'required' => true,
                ],
            ],
        ], [
            "methods"             => WP_REST_Server::DELETABLE,
            "callback"            => [$this, "deleteAccount"],
            "permission_callback" => [$this, "manageSettingsPermission"],
        ]] );
    }

    public function getAuthUrl( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $accountKey = $request->get_param( 'accountKey' );
            $appKey = $request->get_param( 'appKey' );
            $appSecret = $request->get_param( 'appSecret' );
            $connectionType = $request->get_param( 'connectionType' );
            if ( !empty( $accountKey ) ) {
                $account = ccpigdGetAccountByKey( $accountKey );
                if ( is_wp_error( $account ) ) {
                    return self::errorResponse( $account->get_error_message(), self::HTTP_BAD_REQUEST );
                }
                if ( empty( $account ) || !$account instanceof AppAccount ) {
                    return self::errorResponse( __( 'Invalid account key provided.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
                }
                // If accountKey exists, check existing account status
                if ( !empty( $accountKey ) && $accountKey !== 'null' ) {
                    $account = Accounts::getInstance()->syncAccount( $account->getId() );
                    if ( $account instanceof AppAccount && (int) $account->getLost() === 0 ) {
                        return $this->successResponse( $account->jsonSerialize(), __( 'Reconnected account successfully', 'integration-google-drive' ) );
                    }
                }
            }
            if ( !empty( $appKey ) && !empty( $appSecret ) ) {
                $existingAppKey = Helpers::getSetting( 'accounts.appClientId', null );
                $existingAppSecret = Helpers::getSetting( 'accounts.appClientSecret', null, 'decode' );
                if ( $existingAppKey !== $appKey ) {
                    Helpers::updateSetting( 'accounts.appClientId', $appKey );
                }
                if ( $existingAppSecret !== $appSecret ) {
                    Helpers::updateSetting( 'accounts.appClientSecret', $appSecret );
                }
            }
            if ( !empty( $connectionType = $request->get_param( 'connectionType' ) ) ) {
                $validatedConnectionTypes = ['automatic', 'manual'];
                if ( in_array( $connectionType, $validatedConnectionTypes, true ) ) {
                    $existingConnectionType = Helpers::getSetting( 'accounts.connectionType', 'manual' );
                    if ( $existingConnectionType !== $connectionType ) {
                        Helpers::updateSetting( 'accounts.connectionType', $connectionType );
                    }
                }
            }
            // Generate new auth URL
            $authUrl = Client::getInstance( 'new' )->getAuthUrl();
            if ( empty( $authUrl ) ) {
                return $this->errorResponse( __( 'Auth URL could not be generated', 'integration-google-drive' ), self::HTTP_NOT_FOUND );
            }
            return $this->successResponse( $authUrl, __( 'Auth URL retrieved successfully', 'integration-google-drive' ) );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve auth URL' );
        }
    }

    public function getAllAccounts( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $accounts = Accounts::getInstance()->getAccounts();
            if ( empty( $accounts ) ) {
                return $this->errorResponse( 'No account found', self::HTTP_NOT_FOUND );
            }
            return $this->successResponse( array_values( $accounts ), 'Accounts retrieved successfully' );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve accounts' );
        }
    }

    public function getAccount( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $accountKey = $request->get_param( 'accountKey' );
            $account = Accounts::getInstance()->getAccountByKey( $accountKey );
            if ( empty( $account ) ) {
                return $this->errorResponse( 'No account found', self::HTTP_NOT_FOUND );
            }
            return $this->successResponse( $account->jsonSerialize(), 'Account retrieved successfully' );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, 'Failed to retrieve account' );
        }
    }

    public function deleteAccount( WP_REST_Request $request ) : WP_REST_Response {
        $accountKey = $request->get_param( 'accountKey' );
        $account = ccpigdGetAccountByKey( $accountKey );
        if ( is_wp_error( $account ) ) {
            return self::errorResponse( $account->get_error_message(), self::HTTP_BAD_REQUEST );
        }
        if ( empty( $account ) || !$account instanceof AppAccount ) {
            return self::errorResponse( __( 'Invalid account key provided.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
        }
        try {
            $result = Accounts::getInstance()->deleteAccount( $account->getId() );
            if ( is_wp_error( $result ) ) {
                return $this->errorResponse( $result->get_error_message(), $result->get_error_code() );
            }
            return $this->successResponse( $result, 'Account deleted successfully' );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, 'Failed to delete account' );
        }
    }

    public function switch( WP_REST_Request $request ) : WP_REST_Response {
        try {
            $accountKey = $request->get_param( 'accountKey' );
            if ( empty( $accountKey ) ) {
                return self::errorResponse( __( 'Account key is required.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
            }
            $account = ccpigdGetAccountByKey( $accountKey );
            if ( is_wp_error( $account ) ) {
                return self::errorResponse( $account->get_error_message(), self::HTTP_BAD_REQUEST );
            }
            if ( empty( $account ) || !$account instanceof AppAccount ) {
                return self::errorResponse( __( 'Invalid account key provided.', 'integration-google-drive' ), self::HTTP_BAD_REQUEST );
            }
            $account = Accounts::getInstance()->syncAccount( $accountKey );
            if ( $account instanceof AppAccount && (int) $account->getLost() === 0 ) {
                return $this->successResponse( $account->jsonSerialize(), __( 'Reconnected account successfully', 'integration-google-drive' ) );
            }
            return $this->errorResponse( 'Failed to switch account', 400 );
        } catch ( \Exception $e ) {
            return $this->handleException( $e, 'Failed to switch account' );
        }
    }

}
