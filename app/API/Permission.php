<?php

namespace CodeConfig\IGD\App\API;

use CodeConfig\IGD\App\API;
use CodeConfig\IGD\App\Client;
use CodeConfig\IGD\Google\Http\HttpRequest;
use CodeConfig\IGD\Google\Service\ServiceDrivePermission;
use WP_Error;

class Permission extends API
{
    /**
     * Constructor
     *
     * @param Client $client The client instance with the account ID
     *
     * @since 1.0.0
     */
    public function __construct(Client $client)
    {
        $this->client = $client->getClient();
        parent::__construct($this->client);
    }

    /**
     * Set a permission on a file
     *
     * @param string $fileId The ID of the file
     * @param array $permission The permission to be set
     * @return ServiceDrivePermission|WP_Error The created permission
     *
     * @since 1.0.0
     */
    public function cratePermission($fileId, $permission = [])
    {
        $defaultPermission = [
            'type' => 'anyone',
            'role' => 'reader',
        ];

        $permission = wp_parse_args($permission, $defaultPermission);

        $_permission = new ServiceDrivePermission();
        $_permission->setType($permission['type']);
        $_permission->setRole($permission['role']);
        $_permission->setAllowFileDiscovery(false);

        if ($permission['type'] === 'domain' && !empty($permission['domain'])) {
            $_permission->setDomain($permission['domain']);
        }

        $params = [
            'fields'            => 'id,role,type,domain',
            'supportsAllDrives' => true,
        ];

        try {
            return $this->service->permissions->create($fileId, $_permission, $params);
        } catch (\Throwable $th) {

            return new WP_Error('error', $th->getMessage());
        }
    }

    /**
     * Checks if a file is shared.
     *
     * Checks if a file is shared with the current user.
     *
     * @param \CodeConfig\IGD\App\File $file The file to be checked.
     * @return bool|WP_Error True if the file is shared, false if not.
     */
    public function isShared($file)
    {
        $fileId      = $file->getId();
        $resourceKey = $file->getResourceKey();
        $isDir = $file->isDir();

        $users = $file->getPermission('users');

        if (isset($users['anyoneWithLink']['type']) && $users['anyoneWithLink']['type'] === 'anyone') {
            return true;
        }

        $url = "https://drive.google.com/file/d/{$fileId}/view";

        if ($isDir) {
            $url = "https://drive.google.com/drive/folders/{$fileId}";
        }

        if (!empty($resourceKey)) {
            $url .= '?resourcekey=' . urlencode($resourceKey);
        }

        try {
            $request = new HttpRequest($url, 'GET');

            $io = $this->client->getIo();
            $io->setOptions([
                CURLOPT_FOLLOWLOCATION => false, // 0 is okay, but clearer to use false
            ]);

            $httpResponse = $io->makeRequest($request);

            return $httpResponse->getResponseHttpCode() === 200;

        } catch (\Throwable $th) {
            return new WP_Error('error', $th->getMessage()) ;
        }
    }

}
