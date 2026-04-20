<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Models\Account as AccountModel;

use function defined;

use JsonSerializable;
use WP_Error;

// use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') || exit('No direct script access allowed');

class Account implements JsonSerializable
{
    // use Singleton;

    private $id;
    private $accountKey;
    private $lost;
    private $name;
    private $email;
    private $photo;
    private $rootId;
    private $user;
    private $storage;
    private $active;
    private $tokens;

    public function __construct($id, $name, $email, $photo, $storage, $lost, $rootId, $user, $active = false, $tokens = '')
    {
        $this->id             = $id;
        $this->accountKey     = md5("{$id}-{$rootId}");
        $this->lost           = $lost;
        $this->name           = $name;
        $this->email          = $email;
        $this->photo          = $photo;
        $this->user           = $user;
        $this->rootId         = $rootId;
        $this->storage        = $storage;
        $this->active         = $active;
        $this->tokens         = $tokens;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAccountKey()
    {
        return $this->accountKey;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPhoto()
    {
        return $this->photo;
    }

    public function getRootId()
    {
        return $this->rootId;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Get the tokens associated with this account
     *
     * @return array
     */
    public function getAccessToken()
    {
        return $this->tokens;
    }

    /**
     * Check if the account has any access tokens.
     *
     * @return bool True if there are tokens associated with the account, false otherwise.
     */
    public function hasAccessToken()
    {
        return !empty($this->tokens);
    }

    public function getLost()
    {
        return $this->lost;
    }

    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets the access tokens for this account.
     *
     * @param string $tokens The new access tokens to set for the account.
     *
     * @return bool|WP_Error True if the tokens were successfully set, false otherwise.
     */
    public function setAccessTokens(string $tokens)
    {
        $this->tokens = $tokens;
        $setToken     = AccountModel::getInstance()->setToken($this->getId(), $tokens);

        if (is_wp_error($setToken)) {
            return $setToken;
        }

        return $setToken;
    }

    public function setLost($lost)
    {
        $this->lost = $lost;
    }

    /**
     * Saves the current account to the database by updating its details.
     *
     * @return bool|WP_Error True if the account was successfully updated,
     *                       or a WP_Error object on failure.
     */

    /**
     * Save the current account to the database.
     *
     * This method updates the account details in the database by calling the
     * `updateAccount` method from the `AccountModel` instance.
     *
     * @return bool|WP_Error Returns true if the account was successfully updated,
     *                       or a WP_Error object if an error occurred.
     */
    public function save()
    {
        return AccountModel::getInstance()->updateAccount($this);
    }

    /**
     * Prepare the account for JSON serialization.
     *
     * This method is invoked when the account object is serialized using the
     * `wp_json_encode()` function. It returns an associative array of the account
     * details, which will be used to construct the JSON representation of the
     * account.
     *
     * @return array The array of account details.
     */
    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'accountKey' => $this->accountKey,
            'name'       => $this->name,
            'email'      => $this->email,
            'photo'      => $this->photo,
            'storage'    => $this->storage,
            'lost'       => $this->lost,
            'rootId'     => $this->rootId,
            'user'       => $this->user,
            'active'     => $this->active,
        ];
    }

}
