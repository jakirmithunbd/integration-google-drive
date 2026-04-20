<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Google\Client as GoogleClient;
use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\Singleton;
use Exception;
use WP_Error;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Client {
    use Singleton;
    /**
     * Google Client instance
     *
     * @var GoogleClient
     */
    public $client;

    /**
     * Account ID associated with the client
     *
     * @var int
     */
    public $accountId;

    /**
     * Account details
     *
     * @var Account
     */
    public $account;

    /**
     * Google Client ID
     *
     * @var string
     */
    private $clientId;

    /**
     * Google Client Secret
     *
     * @var string
     */
    private $clientSecret;

    /**
     * Redirect URI for OAuth
     *
     * @var string
     */
    private $redirectUri;

    /**
     * Constructor to initialize the client.
     *
     * @param string|null $accountId Optional account ID to configure the client for.
     */
    public function __construct( $accountId = null ) {
        $credentials = $this->setCredentials();
        if ( is_wp_error( $credentials ) ) {
            Notices::getInstance()->add( [
                'type'        => 'error',
                'title'       => __( 'Configuration Error', 'integration-google-drive' ),
                'description' => $credentials->get_error_message(),
            ] );
            add_action( 'admin_notices', function () use($credentials) {
                ?>
                <div class="ccpigd-notice notice notice-error">
                    <p><?php 
                echo esc_html( $credentials->get_error_message() );
                ?></p>
                </div>
                <?php 
            } );
            return;
        }
        $this->accountId = $accountId;
        if ( 'new' === $accountId ) {
            return;
        }
        $account = Accounts::getInstance()->getAccount( $this->accountId );
        if ( $account instanceof Account ) {
            $this->account = $account;
            $this->accountId = $account->getId();
            if ( $account->getLost() ) {
                return;
            }
        }
    }

    /**
     * Get the Google Client instance.
     *
     * This method returns the Google Client instance for the current account. If the client instance does not exist yet,
     * it will be created.
     *
     * @return GoogleClient The Google Client instance.
     */
    public function getClient() {
        if ( empty( $this->client ) ) {
            $client = $this->gettingClient();
            if ( is_wp_error( $client ) || empty( $client ) ) {
                return new GoogleClient();
            }
            $this->client = $client;
        }
        return $this->client;
    }

    public function getNewClient() {
        $this->initializeGoogleClient();
        $this->configureGoogleClient();
        return $this->client;
    }

    /**
     * Get the authorization URL.
     *
     * @return string|WP_Error The authorization URL.
     */
    public function getAuthUrl() {
        $accounts = Accounts::getInstance()->getAccounts();
        if ( count( $accounts ) >= 1 ) {
            return new WP_Error(403, __( 'You cannot authorize more than one account in free version.', 'integration-google-drive' ));
        }
        return $this->getNewClient()->createAuthUrl();
    }

    /**
     * Retrieve the access token for the current account.
     *
     * @return string The access token associated with the account, or false if no token is found.
     */
    public function getAccessToken() {
        $accessToken = $this->getClient()->getAccessToken();
        if ( $accessToken ) {
            return json_decode( $accessToken )->access_token;
        }
        return false;
    }

    /**
     * Retrieve the refresh token for the current account.
     *
     * This function will return the refresh token associated with the account.
     *
     * @return string The refresh token associated with the account, or false if no token is found.
     */
    public function getRefreshToken() {
        return $this->getClient()->getRefreshToken();
    }

    /**
     * Refresh the access token
     *
     * This function will refresh the access token and will update the account
     * with the new access token. If the refresh token is empty, it will revoke
     * the token and delete the account.
     *
     * @return GoogleClient|WP_Error
     */
    public function refreshToken() {
        $refreshToken = $this->getClient()->getRefreshToken();
        if ( empty( $refreshToken ) ) {
            // Revoke the token # TODO -> Remove token not delete account
            $this->getClient()->revokeToken();
            $lostAccount = Accounts::getInstance()->lostAccount( $this->accountId );
            if ( is_wp_error( $lostAccount ) ) {
                Notices::getInstance()->add( [
                    'type'        => 'error',
                    'title'       => __( 'Account connection lost', 'integration-google-drive' ),
                    'description' => $lostAccount->get_error_message(),
                ] );
                return $lostAccount;
            }
            Notices::getInstance()->add( [
                'type'        => 'error',
                'title'       => __( 'Account connection lost', 'integration-google-drive' ),
                'description' => __( 'Your account connection was lost. Please re-authorize to continue.', 'integration-google-drive' ),
            ] );
        }
        try {
            $this->client->refreshToken( $refreshToken );
            $newTokens = $this->getClient()->getAccessToken();
            $this->account->setAccessTokens( $newTokens );
            return $this->client;
        } catch ( \Throwable $th ) {
            $lostAccount = Accounts::getInstance()->lostAccount( $this->accountId );
            if ( is_wp_error( $lostAccount ) ) {
                return $lostAccount;
            }
            return new WP_Error(403, $th->getMessage());
        }
    }

    // =============== PRIVATE METHODS ===============
    /**
     * Setup the Google Client
     *
     * This function sets up the Google Client and check if the authorization is
     * still valid. If the authorization is not valid, it will refresh the token.
     *
     * @return GoogleClient|WP_Error
     */
    private function gettingClient() {
        try {
            $this->initializeGoogleClient();
        } catch ( Exception $exception ) {
            return new WP_Error(403, $exception->getMessage());
        }
        $this->configureGoogleClient();
        if ( empty( $this->account ) || empty( $this->account->hasAccessToken() ) ) {
            return $this->client;
        }
        $accessToken = $this->account->getAccessToken();
        if ( empty( $accessToken ) ) {
            return new WP_Error(403, __( 'Access token is empty. Please re-authenticate.', 'integration-google-drive' ));
        }
        if ( !is_array( $accessToken ) ) {
            $accessToken = json_decode( $accessToken, true );
        }
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(403, __( 'Access token is invalid. Please re-authenticate.', 'integration-google-drive' ));
        }
        $this->client->setAccessToken( wp_json_encode( $accessToken ) );
        if ( !$this->client->isAccessTokenExpired() ) {
            return $this->client;
        }
        try {
            $refreshedClient = $this->refreshToken();
            if ( !$refreshedClient || is_wp_error( $refreshedClient ) ) {
                return new WP_Error(403, __( 'Refresh token is empty. Please re-authenticate.', 'integration-google-drive' ));
            }
            return $refreshedClient;
        } catch ( Exception $exception ) {
            return new WP_Error(403, $exception->getMessage());
        }
    }

    private function initializeGoogleClient() {
        $this->client = new GoogleClient();
    }

    private function configureGoogleClient() {
        $this->client->setApplicationName( CCPIGD_NAME . ' -v ' . CCPIGD_VERSION );
        $this->client->setClientId( $this->clientId );
        $this->client->setClientSecret( $this->clientSecret );
        $this->client->setRedirectUri( $this->redirectUri );
        $this->client->setApprovalPrompt( 'force' );
        $this->client->setAccessType( 'offline' );
        $pluginUrl = site_url( "/ccpigd/authorize/" );
        $state = [
            'authorize_url' => $pluginUrl,
            'site_url'      => site_url(),
            'is_trial'      => ccpigd_fs()->is_trial(),
            'site_name'     => get_bloginfo( 'name' ),
        ];
        $state['is_pro'] = false;
        $jsonState = serialize( $state );
        $this->client->setState( base64_encode( $jsonState ) );
        $this->client->setScopes( ['https://www.googleapis.com/auth/drive'] );
    }

    private function setCredentials() {
        $connectionType = Helpers::getSetting( 'accounts.connectionType', 'manual' );
        $clientId = CCPIGD_CLIENT_ID;
        $clientSecret = CCPIGD_CLIENT_SECRET;
        $redirectUri = CCPIGD_REDIRECT_URI;
        if ( $connectionType === 'manual' ) {
            $clientId = Helpers::getSetting( 'accounts.appClientId', null );
            $clientSecret = Helpers::getSetting( 'accounts.appClientSecret', null, 'decode' );
            $redirectUri = CCPIGD_MANUAL_REDIRECT_URI;
        }
        if ( empty( $clientId ) ) {
            return new WP_Error(403, __( 'Client ID is required. Please set it in the settings.', 'integration-google-drive' ));
        }
        if ( empty( $clientSecret ) ) {
            return new WP_Error(403, __( 'Client Secret is required. Please set it in the settings.', 'integration-google-drive' ));
        }
        if ( empty( $redirectUri ) ) {
            return new WP_Error(403, __( 'Redirect URL is required. Please set it in the settings.', 'integration-google-drive' ));
        }
        $this->clientId = apply_filters( 'ccpigd/clientId', $clientId );
        $this->clientSecret = apply_filters( 'ccpigd/clientSecret', $clientSecret );
        $this->redirectUri = apply_filters( 'ccpigd/redirectUri', $redirectUri );
        return [
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri'  => $this->redirectUri,
        ];
    }

}
