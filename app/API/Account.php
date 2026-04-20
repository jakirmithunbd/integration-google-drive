<?php

namespace CodeConfig\IGD\App\API;

use CodeConfig\IGD\App\API;
use CodeConfig\IGD\App\Client;
use Exception;
use WP_Error;

defined('ABSPATH') || exit('No direct script access allowed');

class Account extends API
{
    /**
     * Constructor to initialize the Account with a Client instance.
     *
     * @param Client $client The Client instance used for API requests.
     */

    public function __construct(Client $client)
    {
        $this->client    = $client->getClient();

        parent::__construct($this->client);
    }

    /**
     * Retrieves account information including user details and storage quota.
     *
     * @return array|WP_Error Returns an associative array with account details such as user ID, name, email, photo,
     *                        storage usage, and root ID, or a WP_Error on failure.
     */
    public function get()
    {

        try {
            $about = $this->about->get(['fields' => 'storageQuota,user']);

            $data = [
                'id'      => $about->getUser()->getPermissionId(),
                'name'    => $about->getUser()->getDisplayName(),
                'email'   => $about->getUser()->getEmailAddress(),
                'photo'   => $about->getUser()->getPhotoLink(),
                'storage' => [
                    'usage' => $about->getStorageQuota()->getUsage(),
                    'limit' => $about->getStorageQuota()->getLimit(),
                ],
                'lost'    => false,
                'root_id' => $this->service->files->get('root')->getId(),
            ];

            return $data;
        } catch (Exception $exception) {
            return new WP_Error(500, $exception->getMessage());
        }
    }
}
