<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\App\API\Account as APIAccount;
use CodeConfig\IGD\Models\Account as AccountModel;
use CodeConfig\IGD\Models\Files;
use CodeConfig\IGD\Utils\Singleton;
use function in_array;
use WP_Error;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Accounts {
    use Singleton;
    /**
     * Instance of the AccountModel class, used to interact with the database
     *
     * @var AccountModel
     */
    private $model;

    /**
     * Array of Account objects, used to store the accounts in memory
     *
     * @var array|WP_Error
     */
    private $accounts;

    /**
     * Instance of the Account class, used to store the current account
     *
     * @var Account|WP_Error
     */
    private $currentAccount;

    public function __construct() {
        $this->model = new AccountModel();
        $this->accounts = $this->model->getAccounts();
        $this->currentAccount = $this->model->getAccount();
    }

    /**
     * Add a new account to the database
     *
     * @param string $id The ID of the account
     * @param string $name The name of the account
     * @param string $email The email address of the account owner
     * @param string $photo The URL of the account owner's avatar
     * @param array $storage The storage details of the account
     * @param bool $lost Whether the account is lost or not
     * @param string $rootId The ID of the root folder of the account
     * @param int $userId The ID of the user who owns the account
     * @param string $tokens The access tokens associated with the account
     *
     * @return Account|WP_Error The newly created account
     */
    public function addAccount(
        $id,
        $name,
        $email,
        $photo,
        $storage,
        $lost,
        $rootId,
        $userId,
        $tokens = ''
    ) {
        $account = new Account(
            $id,
            $name,
            $email,
            $photo,
            $storage,
            $lost,
            $rootId,
            $userId,
            false,
            $tokens
        );
        $result = $this->model->addAccount( $account );
        if ( !is_wp_error( $result ) ) {
            $this->accounts[$id] = $account;
        }
        return $account;
    }

    /**
     * Retrieves all accounts from memory or the database.
     *
     * If the accounts are not already loaded in memory, it fetches them
     * from the database and stores them in memory.
     *
     * @return array|WP_Error An array of Account objects, or a WP_Error object if an error occurred.
     */
    public function getAccounts() {
        if ( !isset( $this->accounts ) || empty( $this->accounts ) ) {
            $this->accounts = $this->model->getAccounts();
        }
        return $this->accounts;
    }

    /**
     * {@inheritdoc}
     *
     * The `accounts` property is a cached version of all accounts in the database.
     * It is stored in the session so that it does not need to be fetched from the
     * database on every page load.
     *
     * The `currentAccount` property is the currently active account, as determined
     * by the `switchAccount` method.
     */
    public function __sleep() {
        return ['accounts', 'currentAccount'];
    }

    /**
     * On wakeup, fetch all accounts from the database and store them in memory.
     *
     * This method is called automatically when the object is unserialized.
     *
     * @return void
     */
    public function __wakeup() {
        $this->accounts = $this->model->getAccounts();
    }

    /**
     * Retrieves an account by ID.
     *
     * If no account ID is provided, the currently active account is returned.
     *
     * @param string|null $accountId The ID of the account to retrieve.
     *
     * @return Account|WP_Error The account object if found, or a WP_Error object if not found.
     */
    public function getAccount( $accountId = null ) {
        if ( empty( $accountId ) ) {
            return $this->currentAccount;
        }
        if ( !isset( $this->accounts[$accountId] ) ) {
            $account = $this->model->getAccount( $accountId );
            if ( is_wp_error( $account ) ) {
                return $account;
            }
            $this->accounts[$accountId] = $account;
        }
        return $this->accounts[$accountId];
    }

    /**
     * Syncs an account from Google Drive with the local database.
     *
     * Retrieves the account information from Google Drive and updates the local
     * database with the retrieved information. If the account does not exist in
     * the database, it is created.
     *
     * @param string $accountId The ID of the account to sync.
     *
     * @return Account|WP_Error The updated account object if the synchronization
     *                          was successful, or a WP_Error object if an error
     *                          occurred.
     */
    public function syncAccount( $accountId ) {
        $client = Client::getInstance( $accountId );
        $accountAPI = new APIAccount($client);
        $account = $accountAPI->get();
        if ( is_wp_error( $account ) ) {
            return $account;
        }
        return $this->updateAccount( $accountId, $account );
    }

    /**
     * Retrieve an account by its key.
     *
     * @param string $key The key of the account to retrieve.
     * @return Account|WP_Error The account object if found, or a WP_Error object if not found.
     */
    public function getAccountByKey( $key ) {
        if ( in_array( $key, [
            'my-drive',
            'shared',
            'computers',
            'shared-drives',
            'starred'
        ] ) ) {
            if ( ccpigdGetCurrentUserAccess() ) {
                return $this->getAccount();
            } else {
                return new WP_Error('no_access', 'You do not have access to this account', [
                    'status' => 403,
                ]);
            }
        }
        foreach ( $this->accounts as $account ) {
            if ( $account->getAccountKey() === $key && !$account->getLost() ) {
                return $account;
            }
        }
        return $this->model->getAccountByKey( $key );
    }

    /**
     * Updates an existing account in the database.
     *
     * @param string $accountId The ID of the account to update.
     * @param array $updatedData The updated account data.
     *
     * @return Account|WP_Error The updated account object, or a WP_Error object on failure.
     */
    public function updateAccount( string $accountId, array $updatedData ) {
        $account = $this->model->getAccount( $accountId );
        if ( is_wp_error( $account ) ) {
            return $account;
        }
        $updatedAccount = new Account(
            $accountId,
            $updatedData['name'] ?? $account->getName(),
            $updatedData['email'] ?? $account->getEmail(),
            $updatedData['photo'] ?? $account->getPhoto(),
            $updatedData['storage'] ?? $account->getStorage(),
            $updatedData['lost'] ?? $account->getLost(),
            $updatedData['rootId'] ?? $account->getRootId(),
            $updatedData['userId'] ?? $account->getUser(),
            $updatedData['active'] ?? $account->getActive(),
            $updatedData['tokens'] ?? $account->getAccessToken()
        );
        $result = $this->model->updateAccount( $updatedAccount );
        if ( !is_wp_error( $result ) ) {
            $this->accounts[$accountId] = $updatedAccount;
        }
        return $this->accounts[$accountId] ?? $result;
    }

    /**
     * Deletes an account by ID.
     *
     * @param string $accountId The ID of the account to delete.
     *
     * @return bool|WP_Error True if the deletion was successful, false otherwise.
     *                       If an error occurs, a WP_Error object is returned.
     */
    public function deleteAccount( $accountId ) {
        $result = $this->model->deleteAccount( $accountId );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $files = Files::getInstance()->getFilesByAccountId( $accountId );
        if ( !is_wp_error( $files ) ) {
            foreach ( $files as $file ) {
                Files::getInstance()->deleteFile( $file['id'], $accountId );
            }
        }
        unset($this->accounts[$accountId]);
        return true;
    }

    public function lostAccount( $accountId ) {
        $result = $this->model->lostAccount( $accountId );
        return $result;
    }

}
