<?php

namespace CodeConfig\IGD\App\API;

use CodeConfig\IGD\Google\Service\ServiceDriveDrive;
use Exception;
use WP_Error;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
use CodeConfig\IGD\App\API;
use CodeConfig\IGD\App\Client;
use CodeConfig\IGD\App\File;
use CodeConfig\IGD\Google\Http\HttpBatch;
use CodeConfig\IGD\Google\Service\ServiceDriveDriveFile;
use CodeConfig\IGD\Models\Files as ModelsFiles;
class Files extends API {
    private $accountId;

    /**
     * Constructor
     *
     * @param Client $client The client instance with the account ID
     */
    public function __construct( Client $client ) {
        $this->client = $client->getClient();
        $this->accountId = $client->accountId;
        parent::__construct( $this->client );
    }

    /**
     * Retrieves a file by its ID.
     *
     * @param string $id The ID of the file to retrieve.
     *
     * @return File|WP_Error The file object or false if the file is not found or not a regular file.
     */
    public function getFileById( string $id ) {
        if ( empty( $id ) ) {
            return new WP_Error(400, 'Missing file id.');
        }
        try {
            $response = $this->files->get( $id, [
                'supportsAllDrives' => true,
                'fields'            => CCPIGD_FILE_FIELDS,
            ] );
            $response->setAccountId( $this->accountId );
            if ( !$response instanceof ServiceDriveDriveFile ) {
                return new WP_Error(404, 'File not found.');
            }
            return new File($response);
        } catch ( Exception $exception ) {
            return new WP_Error(500, $exception->getMessage());
        }
    }

    /**
     * Creates a new folder in Google Drive.
     *
     * This function creates a new folder with the specified name and parent folder
     * in Google Drive. If the parent folder is not provided, it defaults to 'root'.
     * It returns the newly created folder object or an error if the creation fails.
     *
     * @param string $folderName The name of the new folder to create.
     * @param string|null $parentFolder The ID of the parent folder. Defaults to 'root'.
     *
     * @return array|WP_Error The file object of the newly created folder or an error.
     */
    public function createNewFolder( $folderName, $parentFolder ) {
        if ( empty( $parentFolder ) ) {
            $parentFolder = 'root';
        }
        try {
            $fileMetadata = new ServiceDriveDriveFile([
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$parentFolder],
            ]);
            $response = $this->files->create( $fileMetadata, [
                'fields'            => CCPIGD_FILE_FIELDS,
                'supportsAllDrives' => true,
            ] );
            if ( !$response instanceof ServiceDriveDriveFile ) {
                return new WP_Error(404, 'File not found.');
            }
            $response->setAccountId( $this->accountId );
            $file = new File($response);
            return $file->save();
        } catch ( Exception $exception ) {
            return new WP_Error(500, $exception->getMessage());
        }
    }

    /**
     * Renames a file in Google Drive.
     *
     * @param string $fileId The ID of the file to rename.
     * @param string $name The new name for the file.
     *
     * @return array|WP_Error The saved file object if successful, or a WP_Error on failure.
     *
     * @throws WP_Error If the file ID or name is empty, or if the update fails.
     */
    public function rename( $fileId, $name ) {
        if ( empty( $fileId ) || empty( $name ) ) {
            return new WP_Error(404, 'File not found.');
        }
        $fileMetadata = new ServiceDriveDriveFile();
        $fileMetadata->setName( $name );
        $response = $this->files->update( $fileId, $fileMetadata, [
            'fields' => CCPIGD_FILE_FIELDS,
        ] );
        if ( !$response instanceof ServiceDriveDriveFile ) {
            return new WP_Error(404, 'File not found.');
        }
        $response->setAccountId( $this->accountId );
        $file = new File($response);
        return $file->save();
    }

    /**
     * Updates the description of a file in Google Drive.
     *
     * @param string $fileId The ID of the file to update.
     * @param string $description The new description for the file.
     *
     * @return array|WP_Error The saved file object if successful, or a WP_Error on failure.
     */
    public function updateDescription( $fileId, $description ) {
        if ( empty( $fileId ) || empty( $description ) ) {
            return new WP_Error(404, 'File not found.');
        }
        $fileMetadata = new ServiceDriveDriveFile();
        $fileMetadata->setDescription( $description );
        $response = $this->files->update( $fileId, $fileMetadata, [
            'fields' => CCPIGD_FILE_FIELDS,
        ] );
        if ( !$response instanceof ServiceDriveDriveFile ) {
            return new WP_Error(404, 'File not found.');
        }
        $response->setAccountId( $this->accountId );
        $file = new File($response);
        return $file->save();
    }

    /**
     * Retrieves a list of files from the specified folder and account.
     *
     * @param array $args {
     *                    An associative array of arguments.
     *
     * @type string $id The ID of the folder to retrieve files from.
     * @type string $accountId The ID of the account associated with the files.
     * @type string $from Where to retrieve files from. Can be either 'cache' or 'server'.
     * @type int $limit The maximum number of files to retrieve.
     * @type int $fileNumbers The number of files to show in the response. If this is less than the total number of files,
     *           the function will return a subset of the files.
     *           }
     *
     * @return array|WP_Error The list of files, sorted by name and limited to the specified number of items.
     */
    public function listFiles( array $args ) {
        $files = [];
        $pageToken = null;
        do {
            try {
                if ( $pageToken ) {
                    $args['pageToken'] = $pageToken;
                }
                $response = $this->files->listFiles( $args );
                $pageToken = $response->getNextPageToken();
                foreach ( $response->getFiles() as $apiFile ) {
                    if ( $apiFile instanceof ServiceDriveDriveFile ) {
                        $apiFile->setAccountId( $this->accountId );
                        $file = new File($apiFile);
                        $savedFiles = $file->save();
                        if ( $savedFiles ) {
                            $files[] = $savedFiles;
                        }
                    }
                }
            } catch ( Exception $exception ) {
                return new WP_Error(500, $exception->getMessage());
            }
        } while ( !empty( $pageToken ) );
        return $files;
    }

    /**
     * Retrieves a list of Google Drive shared drives available to the user.
     *
     * This function fetches shared drives using the Google Drive API and returns
     * them as an array of saved file data. The function continues to fetch drives
     * in a paginated manner until all drives are retrieved.
     *
     * @param array $args Optional parameters for fetching the drives. Defaults
     *                    include 'fields', 'pageSize', and 'pageToken'.
     *
     * @return array|WP_Error An array of saved drive data or a WP_Error if an
     *                        exception occurs during the API call.
     */
    public function listDrives( $args = [] ) {
        $defaultParams = [
            'fields'    => 'kind,nextPageToken,drives(kind,id,name,capabilities,backgroundImageFile,backgroundImageLink,createdTime,hidden)',
            'pageSize'  => 100,
            'pageToken' => '',
        ];
        $params = wp_parse_args( $args, $defaultParams );
        $files = [];
        do {
            try {
                $response = $this->service->drives->listDrives( $params );
                $drives = $response->getDrives();
                $pageToken = ( !empty( $response->getNextPageToken() ) ? $response->getNextPageToken() : '' );
                $params['pageToken'] = $pageToken;
                if ( !empty( $drives ) ) {
                    foreach ( $drives as $drive ) {
                        if ( $drive instanceof ServiceDriveDrive ) {
                            $apiFile = new ServiceDriveDriveFile();
                            $apiFile->setAccountId( $this->accountId );
                            $apiFile->setId( $drive->getId() );
                            $apiFile->setName( $drive->getName() );
                            $apiFile->setMimeType( 'application/vnd.google-apps.folder' );
                            $apiFile->setCreatedTime( $drive->getCreatedTime()->format( DATE_ATOM ) );
                            $apiFile->setModifiedTime( $drive->getCreatedTime()->format( DATE_ATOM ) );
                            $apiFile->setThumbnailLink( $drive->getBackgroundImageLink() );
                            $apiFile->setIconLink( $drive->getBackgroundImageLink() );
                            $apiFile->setParents( 'shared-drives' );
                            $file = new File($apiFile, true);
                            $savedFiles = $file->save();
                            if ( $savedFiles ) {
                                $files[] = $savedFiles;
                            }
                        }
                    }
                }
            } catch ( Exception $ex ) {
                return new WP_Error(500, $ex->getMessage());
            }
        } while ( !empty( $pageToken ) );
        return $files;
    }

    public function deleteFile( $fileIds ) {
        try {
            $this->client->setUseBatch( true );
            $batch = new HttpBatch($this->client);
            foreach ( $fileIds as $fileId ) {
                $fileMetadata = new ServiceDriveDriveFile([
                    'trashed' => true,
                ]);
                ModelsFiles::getInstance()->deleteFile( $fileId, $this->accountId );
                $batch->add( $this->files->update( $fileId, $fileMetadata ) );
            }
            $batch->execute();
            $this->client->setUseBatch( false );
            return true;
        } catch ( Exception $exception ) {
            return new WP_Error(500, $exception->getMessage());
        }
    }

}
