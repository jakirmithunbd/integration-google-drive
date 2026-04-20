<?php

namespace CodeConfig\IGD\Models;

use function CodeConfig\ccpigd_fs;
use CodeConfig\IGD\App\Account as AppAccount;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\Singleton;
use stdClass;
use WP_Error;
use WP_User;
class Account extends BaseModel {
    use Singleton;
    /**
     * Constructor to initialize the model with database access
     */
    public function __construct() {
        parent::__construct( 'integration_google_drive_accounts' );
    }

    /**
     * Get all accounts from the database
     *
     * @return array|WP_Error
     */
    public function getAccounts() {
        if ( !function_exists( 'wp_get_current_user' ) ) {
            return [];
        }
        // if (!ccpigdGetCurrentUserAccess()) {
        //     return [];
        // }
        $result = $this->fetchAll( "SELECT * FROM %i", [$this->tableName] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $this->processAccounts( $result );
    }

    /**
     * Get account by ID
     *
     * @param string|null $id
     * @return AppAccount|WP_Error|false
     */
    public function getAccount( $id = null ) {
        global $wpdb;
        // if (!ccpigdGetCurrentUserAccess()) {
        //     return null;
        // }
        $result = ( empty( $id ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `active` = %d LIMIT 1", $this->tableName, 1 ) ) : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %s LIMIT 1", $this->tableName, $id ) ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $this->processAccount( $result );
    }

    /**
     * Retrieve an account by its key.
     *
     * @param string $key The key of the account to retrieve.
     * @return AppAccount|WP_Error|false The account object if found, or a WP_Error object if not found.
     */
    public function getAccountByKey( $key ) {
        global $wpdb;
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `accountKey` = %s LIMIT 1", $this->tableName, $key ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $this->processAccount( $result );
    }

    /**
     * Add a new account to the database
     *
     * @param AppAccount $account
     * @return bool|WP_Error
     */
    public function addAccount( AppAccount $account ) {
        // if (!ccpigdGetCurrentUserAccess()) {
        //     return false;
        // }
        $tokens = $account->getAccessToken();
        $storage = maybe_serialize( $account->getStorage() );
        if ( is_string( $tokens ) ) {
            $tokens = maybe_serialize( json_decode( $tokens, true ) );
        }
        $hashTokens = Helpers::encode( $tokens );
        $accountsCount = $this->getAccountCount();
        if ( $accountsCount > 0 ) {
            $message = __( 'You have reached the limit of accounts for the free plan. Please upgrade to add more accounts.', 'integration-google-drive' );
            return new WP_Error('account_limit', $message);
        }
        $decisionToActive = ( $accountsCount > 0 ? 0 : 1 );
        $data = [
            'id'         => $account->getId(),
            'accountKey' => $account->getAccountKey(),
            'name'       => $account->getName(),
            'email'      => $account->getEmail(),
            'photo'      => $account->getPhoto(),
            'storage'    => $storage,
            'lost'       => (int) $account->getLost(),
            'rootId'     => $account->getRootId(),
            'userId'     => $account->getUser(),
            'active'     => (int) $decisionToActive,
            'tokens'     => $hashTokens,
            'createdAt'  => current_time( 'mysql' ),
            'updatedAt'  => current_time( 'mysql' ),
        ];
        $format = [
            '%s',
            // id
            '%s',
            // accountKey
            '%s',
            // name
            '%s',
            // email
            '%s',
            // photo
            '%s',
            // storage
            '%d',
            // lost
            '%s',
            // rootId
            '%d',
            // userId
            '%d',
            // active
            '%s',
            // tokens
            '%s',
            // createdAt
            '%s',
        ];
        $isExistingAccount = $this->getAccount( $data['id'] );
        if ( is_wp_error( $isExistingAccount ) ) {
            return $isExistingAccount;
        }
        if ( $isExistingAccount ) {
            unset($data['id']);
            unset($data['accountKey']);
            unset($data['createdAt']);
            $data['updatedAt'] = current_time( 'mysql' );
            $updateFormat = [
                '%s',
                // name
                '%s',
                // email
                '%s',
                // photo
                '%s',
                // storage
                '%d',
                // lost
                '%s',
                // rootId
                '%d',
                // userId
                '%d',
                // active
                '%s',
                // tokens
                '%s',
            ];
            $where = [
                'id' => $account->getId(),
            ];
            $whereFormat = ['%s'];
            $result = $this->update(
                $data,
                $where,
                $updateFormat,
                $whereFormat
            );
        } else {
            $result = $this->insert( $data, $format );
        }
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return (bool) $result;
    }

    /**
     * Update account in the database
     *
     * @param AppAccount $account
     * @return bool|WP_Error
     */
    public function updateAccount( AppAccount $account ) {
        if ( !ccpigdGetCurrentUserAccess() ) {
            return new WP_Error(401, __( 'You do not have permission to update accounts.', 'integration-google-drive' ));
        }
        $tokens = maybe_serialize( $account->getAccessToken() );
        $hashTokens = Helpers::encode( $tokens );
        $data = [
            'name'      => $account->getName(),
            'email'     => $account->getEmail(),
            'photo'     => $account->getPhoto(),
            'storage'   => maybe_serialize( $account->getStorage() ),
            'lost'      => (int) $account->getLost(),
            'rootId'    => $account->getRootId(),
            'active'    => (int) $account->getActive(),
            'tokens'    => $hashTokens,
            'updatedAt' => current_time( 'mysql' ),
        ];
        $format = [
            '%s',
            // name
            '%s',
            // email
            '%s',
            // photo
            '%s',
            // storage
            '%d',
            // lost
            '%s',
            // rootId
            '%d',
            // active
            '%s',
            // tokens
            '%s',
        ];
        $where = [
            'id' => $account->getId(),
        ];
        $whereFormat = ['%s'];
        $result = $this->update(
            $data,
            $where,
            $format,
            $whereFormat
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return (bool) $result;
    }

    /**
     * Delete an account by ID
     *
     * @param int|string $id
     * @return bool|WP_Error
     */
    public function deleteAccount( $id ) {
        if ( !ccpigdGetCurrentUserAccess() || empty( $id ) ) {
            return new WP_Error(401, __( 'You do not have permission to delete this account.', 'integration-google-drive' ));
        }
        $account = $this->getAccount( $id );
        if ( is_wp_error( $account ) ) {
            return $account;
        }
        if ( empty( $account ) ) {
            return new WP_Error(404, __( 'Account not found.', 'integration-google-drive' ));
        }
        if ( !$account instanceof AppAccount ) {
            return new WP_Error(400, __( 'Invalid account data.', 'integration-google-drive' ));
        }
        $result = $this->delete( [
            'id' => $id,
        ], ['%s'] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( empty( $result ) ) {
            return new WP_Error(400, __( 'Failed to delete account.', 'integration-google-drive' ));
        }
        // Remove all files and folders associated with the account
        $filesModel = Files::getInstance();
        $filesModel->deleteFilesByAccountId( $id );
        return (bool) $result;
    }

    /**
     * Sets the specified account as lost.
     *
     * @param string|int $id The ID of the account to set as lost.
     * @return bool|WP_Error True if the account was successfully set as lost, false otherwise.
     *                       If an error occurred, a WP_Error object is returned.
     */
    public function lostAccount( $id ) {
        if ( empty( $id ) ) {
            return false;
        }
        $result = $this->update(
            [
                'lost' => 1,
            ],
            [
                'id' => $id,
            ],
            ['%d'],
            ['%s']
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return (bool) $result;
    }

    /**
     * Checks if the specified account is lost.
     *
     * @param string|int $id The ID of the account to check.
     *
     * @return bool True if the account is lost, false otherwise.
     *              If the account does not exist, an error occurred, or the user does not have permission, false is returned.
     */
    public function isLost( $id ) {
        if ( empty( $id ) ) {
            return false;
        }
        $account = $this->getAccount( $id );
        if ( is_wp_error( $account ) || !$account instanceof AppAccount ) {
            return false;
        }
        $result = $account->getLost() == 1;
        if ( is_wp_error( $result ) ) {
            return false;
        }
        return (bool) $result;
    }

    /**
     * Get tokens for a given account ID
     *
     * @param int|string|null $id Optional account ID
     * @return array|false|WP_Error
     */
    public function getTokens( $id = null ) {
        global $wpdb;
        if ( !ccpigdGetCurrentUserAccess() ) {
            return new WP_Error(401, __( 'You do not have permission to retrieve tokens.', 'integration-google-drive' ));
        }
        if ( empty( $id ) ) {
            $account = $this->getAccount();
            if ( is_wp_error( $account ) ) {
                return $account;
            }
            if ( $account instanceof AppAccount ) {
                $id = $account->getId();
            }
        }
        if ( empty( $id ) || !is_numeric( $id ) ) {
            return new WP_Error(400, __( 'Account ID is required.', 'integration-google-drive' ));
        }
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT tokens FROM %i WHERE id = %s", $this->tableName, $id ), ARRAY_A );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return Helpers::decode( $result['tokens'] );
    }

    /**
     * Updates the token for a given account ID.
     *
     * Validates user permissions and checks that the provided ID and token are
     * non-empty and valid. Then serializes the token and updates it in the database.
     * Logs any database errors encountered during the update process.
     *
     * @param string $id The account ID for which the token is being updated.
     * @param string $token The token to be set for the account.
     * @return bool|WP_Error True if the token was successfully updated, false otherwise.
     */
    public function setToken( $id, $token ) {
        if ( !ccpigdGetCurrentUserAccess() ) {
            return new WP_Error(401, __( 'You do not have permission to update tokens.', 'integration-google-drive' ));
        }
        if ( empty( $id ) || empty( $token ) ) {
            return new WP_Error(400, __( 'Account ID and token are required.', 'integration-google-drive' ));
        }
        $token = maybe_serialize( json_decode( $token, true ) );
        $hashToken = Helpers::encode( $token );
        $data = [
            'tokens'    => $hashToken,
            'updatedAt' => current_time( 'mysql' ),
        ];
        $where = [
            'id' => $id,
        ];
        $format = ['%s', '%s'];
        $whereFormat = ['%s'];
        $result = $this->update(
            $data,
            $where,
            $format,
            $whereFormat
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $result !== false;
    }

    /**
     * Process a single account object
     *
     * @param stdClass $account
     * @return AppAccount|bool
     */
    private function processAccount( $account ) {
        if ( empty( $account ) ) {
            return false;
        }
        $user = \get_user( $account->userId ?? 0 );
        $userInfo = [];
        if ( $user instanceof WP_User ) {
            $userInfo = [
                'id'     => $user->ID,
                'email'  => $user->user_email,
                'name'   => $user->display_name,
                'avatar' => get_avatar_url( $user->ID ),
                'roles'  => $user->roles,
            ];
        }
        return new AppAccount(
            $account->id,
            $account->name,
            $account->email,
            $account->photo,
            maybe_unserialize( $account->storage ),
            (int) $account->lost,
            $account->rootId,
            $userInfo,
            (int) $account->active,
            maybe_unserialize( Helpers::decode( $account->tokens ) )
        );
    }

    /**
     * Process an array of account objects
     *
     * @param array $accounts
     * @return array
     */
    private function processAccounts( array $accounts ) {
        $processAccounts = array_map( [$this, 'processAccount'], $accounts );
        $accountsById = [];
        foreach ( $processAccounts as $processedAccount ) {
            if ( $processedAccount ) {
                $accountId = $processedAccount->getId();
                if ( $accountId !== null ) {
                    $accountsById[$accountId] = $processedAccount;
                }
            }
        }
        return $accountsById;
    }

    /**
     * Check if an account exists by ID
     *
     * @param string|int $id Account ID
     * @return bool|WP_Error True if account exists, false otherwise
     */
    public function accountExists( $id ) {
        if ( empty( $id ) ) {
            return false;
        }
        return $this->exists( [
            'id' => $id,
        ] );
    }

    /**
     * Get account count
     *
     * @return int|WP_Error Number of accounts or WP_Error on failure
     */
    public function getAccountCount() {
        return $this->count();
    }

    /**
     * Get active account ID
     *
     * @return string|null|WP_Error Active account ID or null if none
     */
    public function getActiveAccountId() {
        $result = $this->getColumn( 'id', [
            'active' => 1,
        ] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $result;
    }

    /**
     * Check if account is valid (exists and not lost)
     *
     * @param string|int $id Account ID
     * @return bool True if account is valid, false otherwise
     */
    public function isAccountValid( $id ) {
        return $this->isValidAccount( $id );
    }

    /**
     * Get accounts with pagination
     *
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 10)
     * @param string $orderBy Order by column (default: 'createdAt')
     * @param string $order Order direction (default: 'DESC')
     * @return array|WP_Error Array of accounts or WP_Error on failure
     */
    public function getAccountsPaginated(
        $page = 1,
        $perPage = 10,
        $orderBy = 'createdAt',
        $order = 'DESC'
    ) {
        $allowedOrderBy = [
            'id',
            'name',
            'email',
            'createdAt',
            'updatedAt',
            'active'
        ];
        $orderBy = $this->sanitizeOrderBy( $orderBy, $allowedOrderBy );
        $order = $this->sanitizeOrder( $order );
        $pagination = $this->sanitizePagination( $page, $perPage );
        $result = $this->fetchAll( "SELECT * FROM %i ORDER BY `{$orderBy}` {$order} LIMIT %d OFFSET %d", [$this->tableName, $pagination['perPage'], $pagination['offset']] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $this->processAccounts( $result );
    }

}
