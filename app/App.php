<?php

namespace CodeConfig\IGD\App;

use function array_slice;
use CodeConfig\IGD\App\API\Files as APIFiles;
use CodeConfig\IGD\App\API\Permission;
use CodeConfig\IGD\App\API\Upload;
use CodeConfig\IGD\Google\Service\ServiceDriveDriveFile;
use CodeConfig\IGD\Models\BaseModel;
use CodeConfig\IGD\Models\Files;
use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Models\Shortcode;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\MimeTypeManager;
use CodeConfig\IGD\Utils\Singleton;
use function defined;
use Exception;
use function in_array;
use function intval;
use function is_array;
use function sprintf;
use WP_Error;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class App {
    use Singleton;
    public $accountId;

    private $files;

    private $client;

    public function __construct( ?string $accountId = null ) {
        if ( null == $accountId ) {
            $activeAccount = Accounts::getInstance()->getAccount();
            if ( is_wp_error( $activeAccount ) || empty( $activeAccount ) ) {
                return;
            }
            $accountId = $activeAccount->getId();
        }
        $this->prepareApiFiles( $accountId );
    }

    /**
     * Retrieves a file by its id.
     *
     * If the file is cached, this function will attempt to retrieve it from the cache.
     * Otherwise, it will fetch the file from the Google Drive API.
     *
     * @param string $id The id of the file to retrieve.
     * @param string $accountId The id of the account associated with the file.
     * @param bool $force Whether to force the request to the API and save the result to the database.
     *
     * @return array|WP_Error The file object or false if the file does not exist.
     */
    public function getFile( $id, $accountId, $force = false ) {
        if ( empty( $id ) || empty( $accountId ) ) {
            return new WP_Error(400, 'Missing file id or account id.');
        }
        if ( empty( $force ) ) {
            $file = Files::getInstance()->getFile( $id, $accountId, 'array' );
            if ( !empty( $file ) && !is_wp_error( $file ) ) {
                return $file;
            }
        }
        $client = Client::getInstance( $accountId );
        $fileApi = new APIFiles($client);
        $file = $fileApi->getFileById( $id );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( empty( $file ) ) {
            return new WP_Error(400, 'Something went wrong while fetching the file. Please try again.');
        }
        return $file->save();
    }

    /**
     * Retrieves a file by its key.
     *
     * If the key matches an entry in the database, the function will return the file object from the database.
     * If the key does not match an entry in the database, the function will return false.
     *
     * If the $force parameter is set to true, the function will request the file from the API and save it to the
     * database, even if the file is already cached.
     *
     * @param string $key The key of the file to retrieve.
     * @param bool $force Whether to force the request to the API and save the result to the database.
     * @param string $output The output format of the file object. Can be either 'array' or 'object'. Default is 'array'.
     *
     * @return array|false|WP_Error The file object or false if the file does not exist.
     */
    public function getFileByKey( $key, $force = false, $output = 'array' ) {
        if ( empty( $key ) ) {
            return false;
        }
        $file = Files::getInstance()->getFileByKey( $key );
        if ( empty( $file ) || is_wp_error( $file ) ) {
            return false;
        }
        if ( empty( $force ) ) {
            return ( $output === 'array' ? $file->toArray() : $file );
        }
        $accountId = $file->getAccountId() ?? null;
        $id = $file->getId() ?? null;
        if ( !$accountId || !$id ) {
            return false;
        }
        $client = Client::getInstance( $accountId );
        $fileApi = new APIFiles($client);
        $file = $fileApi->getFileById( $id );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        $filDate = $file->save();
        return ( $output === 'array' ? $filDate : $file );
    }

    /**
     * Retrieves a folder by its key.
     *
     * This function fetches a folder's details using the provided key and
     * returns the associated files if the folder is found.
     * @param string $fileKey The key of the folder to retrieve.
     * @param array $args An associative array containing:
     *                    - 'fileKey': The key of the folder to retrieve.
     *                    - 'type': The type of the key (e.g., 'my-drive' or 'folder').
     *
     * @return array|false|WP_Error False if the folder is not found or the key is empty,
     *                              otherwise returns the files associated with the folder.
     */
    public function getFolderByKey( $fileKey, $args = [] ) {
        if ( empty( $fileKey ) ) {
            return new WP_Error(400, 'Missing folder key.');
        }
        if ( in_array( $fileKey, [
            'my-drive',
            'shared',
            'starred',
            'computers',
            'shared-drives'
        ] ) ) {
            $userAccess = ccpigdGetCurrentUserAccess();
            if ( empty( $userAccess ) ) {
                return new WP_Error(403, 'You do not have permission to access this resource.', [
                    'status' => 403,
                ]);
            }
            $folders = $userAccess['folders'] ?? [];
            if ( !empty( $folders ) ) {
                $response = Files::getInstance()->getFilesByKeys( $folders, [
                    'returnType' => 'array',
                    'perPage'    => BaseModel::MAX_ITEMS_PER_PAGE,
                    'recursive'  => false,
                    'orderBy'    => $args['orderBy'] ?? 'name',
                    'order'      => $args['order'] ?? 'ASC',
                    'accountId'  => $this->accountId,
                ] );
                if ( is_wp_error( $response ) ) {
                    return $response;
                }
                return $response;
            }
        }
        $data = $this->getDataByKey( $fileKey );
        if ( empty( $data ) || is_wp_error( $data ) ) {
            return false;
        }
        $folderId = $data['folderId'] ?? null;
        $accountId = $data['accountId'] ?? null;
        if ( !$folderId || !$accountId ) {
            return false;
        }
        // Detect search mode
        $hasSearch = !empty( $args['search'] ) || !empty( $args['types'] );
        if ( !empty( $hasSearch ) ) {
            $types = ( is_array( $args['types'] ) ? $args['types'] : explode( ',', $args['types'] ?? '' ) );
            $searchResult = $this->search( [
                'query'     => $args['search'] ?? '',
                'types'     => $types,
                'from'      => $args['from'] ?? 'cache',
                'orderBy'   => $args['orderBy'] ?? 'name',
                'order'     => $args['order'] ?? 'ASC',
                'folderId'  => $folderId,
                'accountId' => $accountId,
                'scope'     => 'parent',
                'limit'     => $args['perPage'] ?? 10,
            ] );
            if ( is_wp_error( $searchResult ) ) {
                return $searchResult;
            }
            // Format search results to match the structure of getFiles() response
            $page = $args['page'] ?? 1;
            $perPage = $args['perPage'] ?? 10;
            // Apply pagination to search results
            $totalFiles = count( $searchResult );
            $totalPages = ceil( $totalFiles / $perPage );
            $offset = ($page - 1) * $perPage;
            $paginatedFiles = array_slice( $searchResult, $offset, $perPage );
            return [
                'files'       => array_values( $paginatedFiles ),
                'hasMore'     => $page < $totalPages,
                'totalFiles'  => intval( $totalFiles ),
                'totalPages'  => intval( $totalPages ),
                'currentPage' => intval( $page ),
            ];
        }
        // For non-search mode, get files from the folder with proper structure
        return $this->getFiles( [
            'id'         => $folderId,
            'accountId'  => $accountId,
            'from'       => $args['from'] ?? 'cache',
            'orderBy'    => $args['orderBy'] ?? 'folder,name',
            'order'      => $args['order'] ?? 'ASC',
            'page'       => $args['page'] ?? 1,
            'perPage'    => $args['perPage'] ?? 20,
            'search'     => '',
            'extensions' => $args['extensions'] ?? [],
        ] );
    }

    public function getFolderTree( $folderKey, $args = [] ) {
        $args = wp_parse_args( $args, [
            'shortcodeId' => null,
            'orderBy'     => 'name',
            'order'       => 'ASC',
        ] );
        $queryArgs = [
            'returnType' => 'array',
            'perPage'    => BaseModel::MAX_ITEMS_PER_PAGE,
            'recursive'  => false,
            'orderBy'    => $args['orderBy'],
            'order'      => $args['order'],
        ];
        if ( !empty( $args['shortcodeId'] ) ) {
            $validateFolderKey = Helpers::validateShortcodeKey( $args['shortcodeId'], $folderKey );
            if ( empty( $validateFolderKey ) || is_wp_error( $validateFolderKey ) ) {
                return new WP_Error('forbidden', 'You do not have permission to access this resource.', [
                    'status' => 403,
                ]);
            }
            if ( $folderKey === 'my-drive' || is_array( $validateFolderKey ) ) {
                return $this->getFilesByKeys( $validateFolderKey, $queryArgs );
            }
            $folderKey = ( is_array( $validateFolderKey ) ? $validateFolderKey[0] : $validateFolderKey );
        }
        /**
         * User access validation
         */
        if ( empty( $args['shortcodeId'] ) ) {
            $userAccess = ccpigdGetCurrentUserAccess();
            if ( empty( $userAccess ) ) {
                return new WP_Error('forbidden', 'You do not have permission to access this resource.', [
                    'status' => 403,
                ]);
            }
            if ( !empty( $userAccess['folders'] ) ) {
                $folderKeys = $userAccess['folders'];
                if ( $folderKey === 'my-drive' ) {
                    return $this->getFilesByKeys( $folderKeys, $queryArgs );
                }
                if ( Helpers::validateFileKey( $folderKey, $folderKeys ) === false ) {
                    return new WP_Error('forbidden', 'You do not have permission to access this resource.', [
                        'status' => 403,
                    ]);
                }
            }
        }
        /**
         * Root folder
         */
        if ( $folderKey === 'my-drive' ) {
            $currentAccount = Accounts::getInstance()->getAccount();
            if ( is_wp_error( $currentAccount ) || empty( $currentAccount ) ) {
                return new WP_Error('forbidden', 'You do not have permission to access this resource.', [
                    'status' => 403,
                ]);
            }
            $parentId = $currentAccount->getRootId();
            $accountId = $currentAccount->getId();
        } else {
            $folder = $this->getFileByKey( $folderKey );
            if ( is_wp_error( $folder ) ) {
                return $folder;
            }
            if ( empty( $folder ) || empty( $folder['id'] ) || empty( $folder['accountId'] ) ) {
                return new WP_Error(404, 'Folder not found.');
            }
            $parentId = $folder['id'];
            $accountId = $folder['accountId'];
        }
        return [
            'files' => $this->getFolders( $accountId, [
                'orderBy'  => $args['orderBy'],
                'order'    => $args['order'],
                'parentId' => $parentId,
            ] ),
        ];
    }

    public function getFolders( ?string $accountId = null, array $config = [] ) {
        if ( $accountId === null ) {
            $accountId = $this->accountId;
        }
        return Files::getInstance()->getFolders( $accountId, $config );
    }

    private function separateFilesAndFolders( array $files ) : array {
        $result = [
            'files'   => [],
            'folders' => [],
        ];
        foreach ( $files as $file ) {
            if ( !empty( $file['isDir'] ) ) {
                $result['folders'][] = $file;
            } else {
                $result['files'][] = $file;
            }
        }
        return $result;
    }

    /**
     * Retrieves a breadcrumb array by its key.
     *
     * This function fetches a breadcrumb array using the provided key and
     * returns the associated breadcrumb if the folder is found.
     *
     * @param string $fileKey The key of the folder to retrieve.
     * @param array $args An associative array containing:
     *                    - 'rootFileKey': The file key of the root folder (optional).
     *                    - 'rootFolderKey': The folder key of the root folder (default: 'my-drive').
     *                    - 'rootFolderName': The display name of the root folder (default: 'My Drive').
     *
     *
     * @return array|WP_Error False if the folder is not found or the key is empty,
     *                        otherwise returns the breadcrumb associated with the folder.
     */
    public function getBreadcrumbByKey( $fileKey, $args = [] ) {
        $defaults = [
            'rootFileKey'    => null,
            'rootFolderKey'  => 'my-drive',
            'rootFolderName' => __( 'My Drive', 'integration-google-drive' ),
        ];
        $args = wp_parse_args( $args, $defaults );
        $rootFileKey = $args['rootFileKey'];
        $rootFolderKey = ( $args['rootFolderKey'] !== '/' ? $args['rootFolderKey'] : 'my-drive' );
        $rootFolderName = $args['rootFolderName'];
        $home = [[
            'fileKey' => $rootFolderKey,
            'name'    => $rootFolderName,
        ]];
        if ( empty( $fileKey ) && $rootFolderKey === 'my-drive' ) {
            return [[
                'fileKey' => '/',
                'name'    => $rootFolderName,
            ]];
        }
        if ( $rootFileKey !== null && $rootFolderKey === 'my-drive' ) {
            $rootFile = $this->getFileByKey( $rootFileKey );
            if ( is_wp_error( $rootFile ) ) {
                return $home;
            }
            $parentId = $rootFile['parentId'] ?? null;
            if ( $parentId ) {
                $rootFolder = $this->getFile( $parentId, $rootFile['accountId'] ?? '' );
                if ( !is_wp_error( $rootFolder ) ) {
                    $rootFolderKey = $rootFolder['fileKey'] ?? '/';
                }
            }
        }
        if ( $fileKey === $rootFolderKey ) {
            return $home;
        }
        // Handle special types
        if ( in_array( $fileKey, [
            'my-drive',
            'shared',
            'starred',
            'computers',
            'shared-drives'
        ] ) ) {
            $labels = [
                'my-drive'      => __( 'My Drive', 'integration-google-drive' ),
                'shared'        => __( 'Shared with me', 'integration-google-drive' ),
                'starred'       => __( 'Starred', 'integration-google-drive' ),
                'computers'     => __( 'Computers', 'integration-google-drive' ),
                'shared-drives' => __( 'Shared Drives', 'integration-google-drive' ),
            ];
            return [[
                'fileKey' => $fileKey,
                'name'    => $labels[$fileKey] ?? ucfirst( $fileKey ),
            ]];
        }
        $folderData = $this->getDataByKey( $fileKey );
        $accountId = $folderData['accountId'] ?? null;
        $folder = $folderData['folder'] ?? [];
        // Default to folder name
        $breadcrumb = [[
            'fileKey' => $fileKey,
            'name'    => $folder['name'] ?? __( 'Home', 'integration-google-drive' ),
        ]];
        // Handle special parents
        $specialParents = [
            'shared-drives' => __( 'Shared Drives', 'integration-google-drive' ),
            'computers'     => __( 'Computers', 'integration-google-drive' ),
            'shared'        => __( 'Shared with me', 'integration-google-drive' ),
        ];
        if ( $rootFolderKey ) {
            $specialParents[$rootFolderKey] = $rootFolderName;
        }
        $parentId = $folder['parentId'] ?? null;
        if ( !empty( $parentId ) ) {
            $parentKey = ccpigdGenerateKey( $parentId, $accountId );
            if ( isset( $specialParents[$parentKey] ) ) {
                $breadcrumb[] = [
                    'fileKey' => '/',
                    'name'    => $specialParents[$parentKey],
                ];
                return $breadcrumb;
            }
            $account = Accounts::getInstance()->getAccount( $accountId );
            if ( is_wp_error( $account ) ) {
                return $account;
            }
            if ( $account && $account->getRootId() === $parentId ) {
                $breadcrumb[] = [
                    'fileKey' => 'my-drive',
                    'name'    => __( 'My Drive', 'integration-google-drive' ),
                ];
                return $breadcrumb;
            }
            // Recursively get parent breadcrumb
            $parentFolder = $this->getFile( $parentId, $accountId );
            if ( is_wp_error( $parentFolder ) ) {
                return $breadcrumb;
            }
            if ( !empty( $parentFolder['fileKey'] ) && !is_wp_error( $parentFolder ) ) {
                $_breadcrumb = $this->getBreadcrumbByKey( $parentFolder['fileKey'], [
                    'rootFolderKey'  => $rootFolderKey,
                    'rootFolderName' => $rootFolderName,
                ] );
                if ( is_wp_error( $_breadcrumb ) ) {
                    return $_breadcrumb;
                }
                return array_merge( $breadcrumb, $_breadcrumb );
            }
        }
        return $breadcrumb;
    }

    /**
     * Retrieve a list of files from the specified folder and account.
     *
     * If the files are cached, this function will attempt to retrieve them from the cache.
     * Otherwise, it will fetch the files from the Google Drive API.
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
    public function getFiles( array $args = [] ) {
        $args = $this->prepareArgs( $args );
        $folderId = $args['id'] ?? null;
        $accountId = $args['accountId'] ?? null;
        if ( empty( $folderId ) || empty( $accountId ) ) {
            return [];
        }
        $isCached = Files::getInstance()->isCachedFolder( $folderId, $accountId );
        $files = [];
        $isNewFolder = false;
        if ( $isCached && $args['from'] !== 'server' ) {
            $files = $this->fetchFilesFromCache( $args );
        } else {
            $files = $this->fetchFilesFromServer( $args );
            if ( is_wp_error( $files ) ) {
                return $files;
            }
            $isNewFolder = !empty( array_filter( $files, fn( $file ) => $file['isDir'] ?? false ) );
            Accounts::getInstance()->syncAccount( $accountId );
        }
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        $response = $this->prepareResponse( $files, $args );
        $response['isNewFolder'] = $isNewFolder;
        return $response;
    }

    /**
     * Create a new folder
     *
     * @param string $name
     * @param string $parentKey
     *
     * @return array|WP_Error
     */
    public function newFolder( $name, $parentKey ) {
        if ( empty( $name ) ) {
            return new WP_Error(400, 'Folder name or parent folder not found for new folder creation');
        }
        if ( empty( $parentKey ) ) {
            return new WP_Error(400, 'Parent folder not found key for new folder creation');
        }
        $folder = $this->getFileByKey( $parentKey );
        if ( is_wp_error( $folder ) ) {
            return $folder;
        }
        $folder = $this->files->createNewFolder( $name, $folder['id'] );
        if ( empty( $folder ) ) {
            return new WP_Error(500, 'Failed to create folder');
        }
        return $folder;
    }

    /**
     * Upload a file
     *
     * @option name string
     * @option description string
     * @option type string
     * @option folderId string
     * @option size int
     *
     * @return array|null|WP_Error The URL to upload the file to
     */
    public function upload(
        $name,
        $type,
        $folderKey,
        $content = '',
        $description = '',
        $size = 0
    ) {
        $folderId = null;
        $accountId = $this->accountId;
        if ( $folderKey !== 'my-drive' ) {
            $folder = $this->getFileByKey( $folderKey );
            if ( is_wp_error( $folder ) ) {
                return $folder;
            }
            $folderId = $folder['id'] ?? 'my-drive';
            $accountId = $folder['accountId'] ?? $this->accountId;
        }
        $this->prepareApiFiles( $accountId );
        $upload = new Upload($this->client);
        $result = $upload->upload(
            $name,
            $type,
            $folderId,
            $content,
            $description,
            $size
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( $result instanceof ServiceDriveDriveFile ) {
            $result->setAccountId( $accountId );
            $file = new File($result);
            wp_cache_flush_group( 'ccpigd_files' );
            return $file->save();
        }
        if ( empty( $result['url'] ) ) {
            return new WP_Error(500, __( 'Failed to get upload URL', 'integration-google-drive' ));
        }
        return $result;
    }

    public function getUploadedFile( $fileId, $uploadId, $folderKey ) {
        if ( empty( $fileId ) || empty( $uploadId ) || empty( $folderKey ) ) {
            return new WP_Error(400, 'Invalid parameters for retrieving uploaded file');
        }
        $transientKey = "ccpigd-upload-id-{$uploadId}";
        $transientId = get_transient( $transientKey );
        // Resolve folder
        if ( $transientId !== 'my-drive' ) {
            $folder = $this->getFileByKey( $folderKey );
            if ( is_wp_error( $folder ) ) {
                return $folder;
            }
            $folderId = $folder['id'] ?? null;
        } else {
            $folderId = $folderKey;
        }
        // Permission check
        if ( $folderId !== $transientId ) {
            return new WP_Error(403, 'You do not have permission to access this file');
        }
        $accountId = $folder['accountId'] ?? $this->accountId;
        // Get file
        $file = $this->getFile( $fileId, $accountId, true );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( empty( $file['id'] ) ) {
            return new WP_Error(404, 'Uploaded file not found');
        }
        return $file;
    }

    /**
     * Rename a file
     * @param string $fileKey The key of the file to rename
     * @param string $name The new name of the file
     * @option fileId string The ID of the file to rename
     * @option name string The new name of the file
     *
     * @return string|null|WP_Error The new name of the file
     */
    public function rename( $fileKey, $name ) {
        if ( empty( $fileKey ) || empty( $name ) ) {
            return;
        }
        $file = $this->getFileByKey( $fileKey );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        $fileId = $file['id'] ?? null;
        if ( empty( $fileId ) ) {
            return new WP_Error(400, 'Invalid file ID');
        }
        $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $name = rtrim( sanitize_text_field( "{$name}.{$extension}" ), '.' );
        return $this->files->rename( $fileId, $name );
    }

    /**
     * Deletes files based on the provided file IDs.
     *
     * @param array|string $fileKeys The Keys of the files to be deleted.
     *
     * @return mixed|WP_Error Returns the result of the delete operation if successful,
     *                        otherwise null if there was an error or if $fileIds is empty.
     */
    public function delete( $fileKeys ) {
        if ( empty( $fileKeys ) ) {
            return new WP_Error(400, __( 'File Keys are required to delete files.', 'integration-google-drive' ));
        }
        $fileIds = ccpigdGetFileIdsByKeys( $fileKeys );
        if ( is_wp_error( $fileIds ) ) {
            return $fileIds;
        }
        if ( empty( $fileIds ) ) {
            return new WP_Error(400, __( 'No valid file IDs found for the provided file keys.', 'integration-google-drive' ));
        }
        $delete = $this->files->deleteFile( $fileIds );
        if ( is_wp_error( $delete ) ) {
            return $delete;
        }
        if ( !empty( $delete['error'] ) || empty( $delete ) ) {
            return new WP_Error(500, __( 'Failed to delete file.', 'integration-google-drive' ));
        }
        Accounts::getInstance()->syncAccount( $this->accountId );
        return $delete;
    }

    /**
     * Generate a preview link for a file.
     *
     * @param $fileKey string The key of the file to generate a preview link for.
     * @param $mode string The mode of the preview link. Can be either 'preview', 'editable', or 'full-editable'.
     * @option fileId string The ID of the file to generate a preview link for.
     *
     * @return string|WP_Error The preview link, or null on failure.
     */
    public function preview( $fileKey, $mode = 'preview' ) {
        if ( empty( $fileKey ) ) {
            return new WP_Error(400, __( 'A file key is required to generate a preview link. Please provide a valid key and try again.', 'integration-google-drive' ));
        }
        $file = Files::getInstance()->getFileByKey( $fileKey );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( !$file instanceof File ) {
            return new WP_Error(404, __( 'Unable to load file data. Please verify the file key and try again.', 'integration-google-drive' ));
        }
        $fileId = $file->getId();
        $shortcutDetails = $file->getShortcutDetails();
        $targetId = $shortcutDetails['targetId'] ?? null;
        if ( $targetId ) {
            $fileId = $targetId;
        } elseif ( !$file->hasPermission( ['reader'] ) ) {
            $generatePermission = $this->generatePermission( $file );
            if ( is_wp_error( $generatePermission ) ) {
                return $generatePermission;
            }
            if ( !$generatePermission ) {
                return new WP_Error(403, __( 'Unable to generate preview link due to insufficient permissions.', 'integration-google-drive' ));
            }
        }
        if ( empty( $fileId ) ) {
            return new WP_Error(400, __( 'Invalid file ID. Please verify the file key and try again.', 'integration-google-drive' ));
        }
        if ( $file->isDir() ) {
            return "https://drive.google.com/drive/folders/{$fileId}/";
        }
        return $this->generateEmbedUrl( $fileId, $file->getMimeType(), $mode );
    }

    private function generateEmbedUrl( $fileId, $mimeType, $mode = 'preview' ) {
        $editorMimes = MimeTypeManager::EDITOR_MIME_TYPE_MAP;
        $service = $editorMimes[$mimeType] ?? null;
        if ( empty( $service ) ) {
            return "https://drive.google.com/file/d/{$fileId}/preview?rm=minimal";
        }
        if ( $service === 'forms' ) {
            return "https://docs.google.com/forms/d/{$fileId}/viewform?embedded=true";
        }
        if ( $mode === 'editable' ) {
            return "https://docs.google.com/{$service}/d/{$fileId}/edit?usp=drivesdk&rm=minimal&embedded=true";
        } elseif ( $mode === 'full-editable' ) {
            return "https://docs.google.com/{$service}/d/{$fileId}/edit?usp=drivesdk&rm=embedded&embedded=true";
        } else {
            return "https://drive.google.com/file/d/{$fileId}/preview?rm=minimal";
        }
    }

    private function getDownloadUrl( $fileId, $type, $format = null ) {
        if ( empty( $fileId ) || empty( $type ) ) {
            return null;
        }
        $fileId = trim( $fileId );
        $type = strtolower( trim( $type ) );
        $format = ( $format ? ltrim( strtolower( trim( $format ) ), '.' ) : null );
        $nonExportable = MimeTypeManager::NON_DOWNLOADABLE_TYPES;
        $endpoints = [
            'document'     => 'https://docs.google.com/feeds/download/documents/export/Export',
            'spreadsheet'  => 'https://docs.google.com/spreadsheets/export',
            'presentation' => 'https://docs.google.com/feeds/download/presentations/Export',
            'drawing'      => 'https://docs.google.com/feeds/download/drawings/Export',
            'script'       => 'https://script.google.com/feeds/download/export',
        ];
        // If no export format → regular Drive file download
        if ( !$format && !isset( $endpoints[$type] ) ) {
            if ( in_array( $type, $nonExportable, true ) ) {
                return null;
            }
            return sprintf( 'https://drive.google.com/uc?export=download&id=%s', urlencode( $fileId ) );
        }
        if ( !isset( $endpoints[$type] ) ) {
            return null;
        }
        if ( !$format ) {
            $format = MimeTypeManager::DEFAULT_EXPORT_FORMAT[$type] ?? null;
        }
        $formats = MimeTypeManager::CATEGORY_EXPORT_TYPE;
        $mimeMap = MimeTypeManager::EXPORT_MIME_TYPE_MAP;
        // Convert MIME type to extension if necessary
        if ( isset( $mimeMap[$format] ) ) {
            $format = $mimeMap[$format];
        }
        if ( !isset( $formats[$type] ) || !in_array( $format, $formats[$type], true ) ) {
            return null;
        }
        $fileId = urlencode( $fileId );
        if ( $type === 'site' ) {
            return sprintf( 'https://sites.google.com/d/%s/export?exportFormat=%s', $fileId, $format );
        }
        if ( $type === 'script' ) {
            return sprintf(
                '%s?id=%s&format=%s',
                $endpoints[$type],
                $fileId,
                $format
            );
        }
        return sprintf(
            '%s?id=%s&exportFormat=%s',
            $endpoints[$type],
            $fileId,
            $format
        );
    }

    /**
     * Downloads a file from Google Drive by key.
     *
     * @param string $fileKey The key of the file to download.
     *
     * @return string|WP_Error The download link or false if the file is not found or not a regular file.
     */
    public function download( $fileKey, $format = null ) {
        if ( empty( $fileKey ) ) {
            return new WP_Error(400, __( 'A file key is required to download a file. Please provide a valid key and try again.', 'integration-google-drive' ));
        }
        $file = Files::getInstance()->getFileByKey( $fileKey );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( empty( $file ) || !$file instanceof File ) {
            return new WP_Error(404, __( 'Unable to load file data. Please verify the file key and try again.', 'integration-google-drive' ));
        }
        $fileId = $file->getId() ?? null;
        if ( empty( $fileId ) ) {
            return new WP_Error(400, __( 'Invalid file key. Please verify the file key and try again.', 'integration-google-drive' ));
        }
        $hasPermission = $file->hasPermission( ['reader'] );
        if ( !$hasPermission ) {
            $generatePermission = $this->generatePermission( $file );
            if ( !$generatePermission ) {
                return new WP_Error(403, __( 'Unable to download file due to insufficient permissions.', 'integration-google-drive' ));
            }
        }
        if ( !empty( $file->isDir() ) ) {
            Notices::getInstance()->add( [
                'title'       => __( 'This file is a directory', 'integration-google-drive' ),
                'description' => __( 'This file is a directory. Please download the file as a zip file.', 'integration-google-drive' ),
                'type'        => 'warning',
                'fileKey'     => $fileKey,
            ] );
            return "https://drive.google.com/drive/folders/{$fileId}/?usp=sharing";
        }
        if ( !empty( $format ) && $format !== $file->getExtension() ) {
            $isExportAble = MimeTypeManager::isExportAble( $file->getExtension(), $format );
            if ( !$isExportAble ) {
                return new WP_Error(400, __( 'The requested export format is not available for this file.', 'integration-google-drive' ));
            }
        }
        return $this->getDownloadUrl( $fileId, $file->getExtension(), ( $format === $file->getExtension() ? null : $format ) );
    }

    public function generateDownloadLink( $fileKey, $options = [] ) {
        $fileModel = Files::getInstance();
        $file = $fileModel->getFileByKey( $fileKey );
        $isExported = !empty( $options['exactFormat'] ) && $options['exactFormat'] !== $file->getExtension();
        if ( $isExported ) {
            $isExportAble = MimeTypeManager::isExportAble( $file->getExtension(), $options['exactFormat'] );
            if ( !$isExportAble ) {
                return new WP_Error(400, __( 'The requested export format is not available for this file.', 'integration-google-drive' ));
            }
        }
        if ( empty( $file ) || is_wp_error( $file ) ) {
            return new WP_Error('file_not_found', __( 'File not found', 'integration-google-drive' ));
        }
        $downloadKey = $fileModel->getDownloadKey( $fileKey, [
            'expireIn' => $options['expireIn'] ?? 3600,
            'password' => $options['password'] ?? null,
            'limit'    => $options['limit'] ?? 0,
        ] );
        if ( is_wp_error( $downloadKey ) ) {
            return $downloadKey;
        }
        if ( empty( $downloadKey ) ) {
            return new WP_Error('download_link_error', __( 'Unable to generate download link.', 'integration-google-drive' ));
        }
        $extension = ( !empty( $options['exactFormat'] ) ? $options['exactFormat'] : $file->getExtension() );
        $link = ccpigdGetUrl(
            'download',
            $downloadKey,
            $file->getName(),
            null,
            $extension
        );
        return $link;
    }

    /**
     * Search files in Google Drive.
     *
     * @param array $data {
     *                    An array of arguments.
     *
     * @type string $from            From where to search, either 'cache' or 'server'.
     * @type string $scope           Scope of the search, either 'parent' or 'global'.
     * @type string $query           Search query.
     * @type string $orderBy         Field to order by.
     * @type string $order           Order direction, either 'ASC' or 'DESC'.
     * @type array $types           Types of files to search.
     * @type int $limit           The number of files to return.
     * @type bool $fullText        Whether to search full text or not.
     * @type bool $trashed         Whether to include trashed files or not.
     * @type string $folderId        Folder ID to search in.
     * @type string $accountId       Account ID to search in.
     * @type string $modifiedAfter   Date after which to search.
     *              }
     *
     * @return array|WP_Error An array of file objects.
     */
    public function search( $data ) {
        $data = wp_parse_args( $data, [
            'from'          => 'cache',
            'scope'         => 'parent',
            'types'         => [],
            'limit'         => 100,
            'query'         => '',
            'orderBy'       => 'name',
            'order'         => 'ASC',
            'fullText'      => false,
            'trashed'       => false,
            'folderId'      => null,
            'accountId'     => null,
            'modifiedAfter' => null,
        ] );
        $query = trim( $data['query'] );
        $data['scope'] = ( in_array( $data['scope'], ['parent', 'global'] ) ? $data['scope'] : 'parent' );
        $data['from'] = ( in_array( $data['from'], ['cache', 'server'] ) ? $data['from'] : 'cache' );
        if ( $data['from'] === 'cache' ) {
            return Files::getInstance()->search( $data );
        } elseif ( $data['from'] === 'server' ) {
            $fullText = filter_var( $data['fullText'], FILTER_VALIDATE_BOOLEAN );
            $mimeTypes = ccpigdGetMimeTypesByGroup( $data['types'] );
            $params = [
                'query'         => $query,
                'fullText'      => $fullText,
                'mimeTypes'     => $mimeTypes,
                'parent'        => $data['folderId'],
                'trashed'       => false,
                'modifiedAfter' => $data['modifiedAfter'],
            ];
            if ( $data['scope'] === 'global' && ccpigdGetCurrentUserAccess() ) {
                $params['parent'] = null;
            }
            $searchQuery = $this->buildSearchQuery( $params );
            $files = $this->fetchFilesFromServer( [
                'accountId' => $data['accountId'],
                'id'        => $data['folderId'],
                'query'     => $searchQuery,
            ] );
            if ( is_wp_error( $files ) ) {
                return $files;
            }
            return Files::getInstance()->search( $data );
        }
        return [];
    }

    public function getFilesByKeys( $fileKeys, $config = [] ) {
        if ( empty( $fileKeys ) ) {
            return new WP_Error(404, __( 'No file keys provided.', 'integration-google-drive' ));
        }
        if ( !is_array( $fileKeys ) ) {
            return new WP_Error(400, __( 'File keys must be an array.', 'integration-google-drive' ));
        }
        if ( !empty( $fileKeys[0]['fileKey'] ) ) {
            $fileKeys = array_map( function ( $key ) {
                return $key['fileKey'];
            }, $fileKeys );
        }
        $queryConfig = wp_parse_args( $config, [
            'returnType'  => 'array',
            'recursive'   => true,
            'page'        => 1,
            'perPage'     => 20,
            'orderBy'     => 'name',
            'order'       => 'ASC',
            'search'      => '',
            'searchScope' => 'folder',
            'from'        => 'cache',
        ] );
        $filesModel = Files::getInstance();
        if ( isset( $queryConfig['from'] ) && $queryConfig['from'] === 'server' ) {
            $filesData = $filesModel->getFileAttributesByKeys( $fileKeys, ['id', 'accountId', 'extension'] );
            if ( is_wp_error( $filesData ) ) {
                return $filesData;
            }
            if ( empty( $filesData ) ) {
                return [];
            }
            $filterFolderIds = array_filter( $filesData, fn( $file ) => !empty( $file['extension'] ) && $file['extension'] === 'folder' );
            $searchQuery = '';
            if ( !empty( $queryConfig['search'] ) ) {
                $params = [
                    'query'    => $queryConfig['search'],
                    'fullText' => false,
                    'trashed'  => false,
                ];
                $searchQuery = $this->buildSearchQuery( $params );
            }
            foreach ( $filterFolderIds as $file ) {
                $this->fetchFilesFromServer( [
                    'accountId' => $file['accountId'],
                    'id'        => $file['id'],
                    'query'     => $searchQuery,
                ] );
            }
        }
        $filesData = $filesModel->getFilesByKeys( $fileKeys, $queryConfig );
        // TODO: Need to handle thumbnails if required
        // foreach ($fileKeys as $key) {
        //     $thumbnailKey = $key['thumbnailKey'] ?? '';
        //     if (!empty($thumbnailKey)) {
        //         $thumbnails = $filesModel->getFileByKey($thumbnailKey, 'array');
        //         if (!is_wp_error($thumbnails) && !empty($thumbnails)) {
        //             $file['thumbnail'] = $thumbnails;
        //         }
        //     }
        //     $files[] = $file;
        // }
        return $filesData;
    }

    // ================================== PRIVATE METHODS ==================================
    /**
     * Prepares the arguments for the App methods by merging the input arguments with the default arguments.
     *
     * @param array $args The input arguments.
     *
     * @return array The prepared arguments.
     */
    private function prepareArgs( $args ) {
        $defaultArgs = [
            'from'        => 'cache',
            'order'       => 'ASC',
            'orderBy'     => "folder,name",
            'filters'     => [],
            'limit'       => 0,
            'fileNumbers' => 0,
        ];
        $args = wp_parse_args( $args, $defaultArgs );
        return $args;
    }

    /**
     * Fetches files from the server using the provided arguments.
     *
     * This function retrieves files from the Google Drive API based on the specified
     * folder ID and account ID. If the folder ID is 'shared-drives', it lists the shared
     * drives instead. It also removes stale files from the database after fetching.
     *
     * @param array $args An associative array of arguments, including:
     *                    - 'id': The folder ID to fetch files from.
     *                    - 'accountId': The account ID associated with the files.
     *
     * @return array|WP_Error The list of files retrieved from the server or an empty array if
     *                        the folder ID or account ID is not provided, or if no files are found.
     */
    private function fetchFilesFromServer( array $args ) {
        $folderId = $args['id'] ?? null;
        $accountId = $args['accountId'] ?? null;
        if ( empty( $folderId ) || empty( $accountId ) ) {
            return [];
        }
        $files = [];
        if ( $folderId === 'shared-drives' ) {
            $files = $this->files->listDrives();
            return $files;
        } else {
            $params = $this->buildServerParams( $args, $folderId );
            $files = $this->files->listFiles( $params );
        }
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        if ( empty( $files ) ) {
            return [];
        }
        if ( empty( $args['query'] ) ) {
            $this->removeStaleFilesFromDatabase( $files, $args );
        }
        return $this->fetchFilesFromCache( $args );
    }

    private function buildServerParams( array $args, string $folderId ) {
        $query = "trashed=false";
        switch ( true ) {
            case !empty( $args['query'] ):
                $query = $args['query'];
                break;
            case $folderId === 'computers':
                $query = "'me' in owners and mimeType='application/vnd.google-apps.folder' and trashed=false";
                break;
            case $folderId === 'shared':
                $query = "sharedWithMe=true and trashed=false";
                break;
            case $folderId === 'starred':
                $query = "starred=true and trashed=false";
                break;
            default:
                $query .= " and '{$folderId}' in parents";
                break;
        }
        $replaceOrderBy = [
            'name'      => 'folder,name',
            'size'      => 'folder,quotaBytesUsed',
            'createdAt' => 'folder,createdTime',
            'updatedAt' => 'folder,modifiedTime',
        ];
        $requestedField = $args['orderBy'] ?? 'name';
        $sortDirection = ( strtolower( $args['order'] ?? 'asc' ) === 'desc' ? 'desc' : 'asc' );
        $mappedField = $replaceOrderBy[$requestedField] ?? $requestedField;
        $mappedFields = explode( ',', $mappedField );
        $orderByParts = [];
        foreach ( $mappedFields as $field ) {
            $field = trim( $field );
            if ( in_array( $field, ['folder'] ) ) {
                $orderByParts[] = $field;
            } elseif ( in_array( $field, [
                'name',
                'name_natural',
                'createdTime',
                'modifiedTime',
                'quotaBytesUsed'
            ] ) ) {
                $orderByParts[] = "{$field} {$sortDirection}";
            }
        }
        $orderBy = implode( ',', $orderByParts );
        $params = [
            'fields'                    => CCPIGD_LIST_FIELDS,
            'pageSize'                  => 300,
            'orderBy'                   => ( $orderBy ?: 'folder,name' ),
            'pageToken'                 => '',
            'supportsAllDrives'         => true,
            'includeItemsFromAllDrives' => true,
            'corpora'                   => 'allDrives',
            'q'                         => $query,
        ];
        return $params;
    }

    private function fetchFilesFromCache( $args ) {
        return Files::getInstance()->getFolder( $args['id'], $args['accountId'], $args );
    }

    private function prepareResponse( $files, $args ) {
        $folderId = $args['id'] ?? null;
        $accountId = $args['accountId'] ?? null;
        $page = $args['page'] ?? 1;
        $perPage = $args['perPage'] ?? 20;
        if ( empty( $folderId ) || empty( $accountId ) ) {
            return [];
        }
        if ( $args['from'] == 'server' ) {
            $files = array_slice( $files, ($page - 1) * $perPage, $perPage );
        }
        $filter = [];
        if ( !empty( $args['extensions'] ) && is_array( $args['extensions'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $args['extensions'] ), '%s' ) );
            $filter['filterSql'] = " AND extension in ({$placeholders})";
            $filter['filterParams'] = $args['extensions'];
        }
        $totalFiles = Files::getInstance()->childrenCount( $folderId, $accountId, $filter );
        $totalPages = ceil( intval( $totalFiles ) / intval( $perPage ) );
        $hasMore = $page < $totalPages;
        $filteredFiles = array_filter( $files, fn( $file ) => $file['parentId'] === $folderId || $folderId === 'starred' );
        $response = [
            'files'       => array_values( $filteredFiles ),
            'hasMore'     => (bool) $hasMore,
            'totalFiles'  => intval( $totalFiles ),
            'totalPages'  => intval( $totalPages ),
            'currentPage' => intval( $page ),
        ];
        if ( $hasMore ) {
            $response['nextPage'] = $page + 1;
        }
        return $response;
    }

    private function prepareApiFiles( $accountId ) {
        $this->accountId = $accountId;
        $this->client = Client::getInstance( $accountId );
        $this->files = new APIFiles($this->client);
    }

    private function getDataByKey( $key ) {
        $folderId = null;
        $accountId = null;
        $folder = null;
        if ( in_array( $key, [
            'my-drive',
            'shared',
            'starred',
            'computers',
            'shared-drives'
        ] ) ) {
            $account = Accounts::getInstance()->getAccount();
            if ( empty( $account ) || is_wp_error( $account ) ) {
                return false;
            }
            $accountId = $account->getId();
            $folderId = ( $key === 'my-drive' ? $account->getRootId() : $key );
        } else {
            $folder = $this->getFileByKey( $key );
            if ( empty( $folder ) || is_wp_error( $folder ) ) {
                return false;
            }
            $folderId = $folder['id'] ?? null;
            $accountId = $folder['accountId'] ?? null;
        }
        return [
            'folderId'  => $folderId,
            'accountId' => $accountId,
            'folder'    => $folder,
        ];
    }

    /**
     * Generates a permission for the given file, if the file is not already shared.
     *
     * @param File $file The file to generate a permission for.
     *
     * @return bool|WP_Error True if the permission was generated successfully, false if the file is already shared,
     *                       or a WP_Error if the permission generation failed.
     */
    private function generatePermission( File $file ) {
        $users = $file->getPermission( 'users' ) ?? [];
        if ( isset( $users['anyoneWithLink']['type'] ) && $users['anyoneWithLink']['type'] === 'anyone' ) {
            return true;
        }
        $permission = new Permission($this->client);
        $isShared = $permission->isShared( $file );
        if ( is_wp_error( $isShared ) ) {
            return $isShared;
        }
        $domain = Helpers::getSetting( 'advanced.googleWorkspaceDomain', false );
        $isSharingPermission = Helpers::getSetting( 'advanced.sharingPermission', true );
        if ( $isShared ) {
            $users['anyoneWithLink'] = [
                'domain' => $domain,
                'role'   => "reader",
                'type'   => "anyone",
            ];
            $file->setPermission( 'users', $users );
            $file->save();
            return true;
        } elseif ( $isSharingPermission ) {
            $getPermission = $permission->cratePermission( $file->getId(), [
                'domain' => $domain,
                'type'   => ( $domain ? 'domain' : 'anyone' ),
                'role'   => 'reader',
            ] );
            if ( empty( $getPermission ) || is_wp_error( $getPermission ) ) {
                return new WP_Error(401, __( 'Unable to create a permission for this file. Please try again.', 'integration-google-drive' ));
            }
            $users[$getPermission->getId()] = [
                'type'   => $getPermission->getType(),
                'role'   => $getPermission->getRole(),
                'domain' => $getPermission->getDomain(),
            ];
            $file->setPermission( 'users', $users );
            $file->save();
            return true;
        }
        return new WP_Error(401, __( 'Failed to create preview/share due to insufficient permissions. Please enable the required access in Google Drive or in the plugin by going to Settings → Advanced → Manage Sharing Permissions.', 'integration-google-drive' ));
    }

    /**
     * Builds a Google Drive search query from the given parameters.
     *
     * @param array $params The parameters to build the search query from.
     *                      The following keys are supported:
     *                      - query: The search query string.
     *                      - fullText: Whether to search the full text of the file.
     *                      - mimeTypes: An array of MIME types to filter by.
     *                      - parent: The ID of the parent folder to search in.
     *                      - trashed: Whether to include trashed files.
     *                      - modifiedAfter: A timestamp (in RFC 3339 format) to filter by.
     *                      - sharedWithMe: Whether to only return files shared with the current user.
     * @return string The search query string.
     */
    private function buildSearchQuery( array $params ) : string {
        $queryParts = [];
        if ( !empty( $params['query'] ) ) {
            $search = addslashes( $params['query'] );
            $queryParts[] = ( !empty( $params['fullText'] ) ? "fullText contains '{$search}'" : "name contains '{$search}'" );
        }
        if ( !empty( $params['mimeTypes'] ) && is_array( $params['mimeTypes'] ) ) {
            $mimeQueries = array_map( fn( $type ) => "mimeType = '{$type}'", $params['mimeTypes'] );
            $queryParts[] = '(' . implode( ' or ', $mimeQueries ) . ')';
        }
        if ( !empty( $params['parent'] ) ) {
            $queryParts[] = "'{$params['parent']}' in parents";
        }
        $queryParts[] = ( isset( $params['trashed'] ) && $params['trashed'] === true ? "trashed = true" : "trashed = false" );
        if ( !empty( $params['modifiedAfter'] ) ) {
            $queryParts[] = "modifiedTime > '{$params['modifiedAfter']}'";
        }
        if ( !empty( $params['sharedWithMe'] ) ) {
            $queryParts[] = "sharedWithMe";
        }
        return implode( ' and ', $queryParts );
    }

    /**
     * Removes stale files from the database.
     *
     * Given a list of files retrieved from the server and a set of query arguments,
     * this function will remove any files from the database that do not exist in the
     * provided list of files or do not match the query arguments.
     *
     * @param array $currentFiles The list of files retrieved from the server.
     * @param array $queryArgs The query arguments used to retrieve the files.
     */
    private function removeStaleFilesFromDatabase( array $currentFiles, array $queryArgs ) : void {
        $cachedFiles = $this->fetchFilesFromCache( $queryArgs );
        $currentFileIds = array_column( $currentFiles, 'id' );
        foreach ( $cachedFiles as $file ) {
            if ( !in_array( $file['id'], $currentFileIds, true ) ) {
                $files = Files::getInstance()->deleteFile( $file['id'], $file['accountId'] );
                if ( is_wp_error( $files ) ) {
                    continue;
                }
            }
        }
    }

    public function generateSharedLink( $fileKey, $options = [] ) {
        $file = Files::getInstance()->getFileByKey( $fileKey );
        if ( empty( $file ) || is_wp_error( $file ) ) {
            return new WP_Error('file_not_found', __( 'File not found', 'integration-google-drive' ));
        }
        $sharedKey = Files::getInstance()->getSharedKey( $fileKey, [
            'expireIn' => $options['expireIn'] ?? 3600,
            'password' => $options['password'] ?? null,
        ] );
        if ( empty( $sharedKey ) || is_wp_error( $sharedKey ) ) {
            return new WP_Error('shared_link_error', __( 'Unable to generate shared link.', 'integration-google-drive' ));
        }
        $link = ccpigdGetUrl(
            'share',
            $sharedKey,
            $file->getName(),
            null,
            $file->getExtension()
        );
        return $link;
    }

}
