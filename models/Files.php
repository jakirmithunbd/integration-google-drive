<?php

namespace CodeConfig\IGD\Models;

use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\Singleton;
use function count;
use function in_array;
use WP_Error;
class Files extends BaseModel {
    use Singleton;
    public function __construct() {
        parent::__construct( 'integration_google_drive_files' );
    }

    /**
     * Retrieves a list of files from the specified folder and account.
     *
     * @param string $rootId The ID of the root folder to retrieve files from.
     * @param string $accountId The ID of the account associated with the files.
     * @param array $config Optional configuration settings for retrieving files.
     *
     * @return array|null|WP_Error An array of processed file data from the specified folder.
     */
    public function getFolder( $rootId, $accountId, $config = [] ) {
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $allowedOrderBy = [
            'createdAt',
            'name',
            'updatedAt',
            'size'
        ];
        $order = $this->sanitizeOrder( ( isset( $config['order'] ) ? $config['order'] : 'DESC' ) );
        $orderBy = $this->sanitizeOrderBy( ( isset( $config['orderBy'] ) ? $config['orderBy'] : 'createdAt' ), $allowedOrderBy );
        $page = ( isset( $config['page'] ) ? (int) $config['page'] : 1 );
        $perPage = ( isset( $config['perPage'] ) ? (int) $config['perPage'] : 24 );
        $extensions = ( isset( $config['extensions'] ) ? (array) $config['extensions'] : [] );
        $pagination = $this->sanitizePagination( $page, $perPage );
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM %i WHERE parentId = %s AND accountId = %s",
            $this->tableName,
            $rootId,
            $accountId
        );
        if ( !empty( $extensions ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $extensions ), '%s' ) );
            $sql .= $wpdb->prepare( " AND extension IN ({$placeholders})", $extensions );
        }
        if ( $order === 'ASC' ) {
            $sql .= $wpdb->prepare(
                " ORDER BY (CASE WHEN extension = 'folder' THEN 0 ELSE 1 END), %i ASC LIMIT %d OFFSET %d",
                $orderBy,
                $pagination['perPage'],
                $pagination['offset']
            );
        } else {
            $sql .= $wpdb->prepare(
                " ORDER BY (CASE WHEN extension = 'folder' THEN 0 ELSE 1 END), %i DESC LIMIT %d OFFSET %d",
                $orderBy,
                $pagination['perPage'],
                $pagination['offset']
            );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared - We are using $wpdb->prepare for the dynamic parts of the query, but the table name cannot be parameterized, so we have to use $wpdb->prepare for the rest of the query and then insert the table name directly.
        $files = $wpdb->get_results( $sql );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return $this->processFiles( $files );
    }

    public function getFolders( $accountId, $config = [] ) {
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $default = [
            'orderBy' => 'name',
            'order'   => 'ASC',
        ];
        $config = wp_parse_args( $config, $default );
        global $wpdb;
        $sql = $wpdb->prepare( "SELECT * FROM %i WHERE accountId = %s AND extension = 'folder'", $this->tableName, $accountId );
        if ( !empty( $config['parentId'] ) ) {
            $sql .= $wpdb->prepare( " AND parentId = %s", $config['parentId'] );
        }
        if ( !empty( $config['orderBy'] ) && !empty( $config['order'] ) ) {
            $allowedOrderBy = [
                'createdAt',
                'name',
                'updatedAt',
                'size'
            ];
            $orderBy = $this->sanitizeOrderBy( $config['orderBy'], $allowedOrderBy );
            $order = $this->sanitizeOrder( $config['order'] );
            if ( $order === 'ASC' ) {
                $sql .= $wpdb->prepare( " ORDER BY %i ASC", $orderBy );
            } else {
                $sql .= $wpdb->prepare( " ORDER BY %i DESC", $orderBy );
            }
        }
        $files = $wpdb->get_results( $sql );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return $this->processFiles( $files );
    }

    public function search( array $data ) {
        $accountId = $data['accountId'] ?? '';
        $searchQuery = $data['query'] ?? '';
        $types = $data['types'] ?? ['all'];
        $limit = ( isset( $data['limit'] ) ? (int) $data['limit'] : 100 );
        $order = $data['order'] ?? 'ASC';
        $orderBy = $data['orderBy'] ?? 'name';
        $folderId = $data['folderId'] ?? '';
        $scope = ( isset( $data['scope'] ) && in_array( $data['scope'], ['parent', 'global'] ) ? $data['scope'] : 'parent' );
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        if ( empty( $accountId ) ) {
            return new WP_Error(404, __( 'The requested file could not be found. 3', 'integration-google-drive' ));
        }
        $allowedOrderBy = [
            'name',
            'createdAt',
            'updatedAt',
            'size'
        ];
        $orderBy = $this->sanitizeOrderBy( $orderBy, $allowedOrderBy );
        $order = $this->sanitizeOrder( $order );
        $limit = max( 1, min( 1000, $limit ) );
        // Limit to prevent memory issues
        $extensions = ccpigdGetExtensionGroups( $types );
        global $wpdb;
        $queryString = $wpdb->prepare(
            "SELECT * FROM %i WHERE name LIKE %s AND accountId = %s",
            $this->tableName,
            "%{$searchQuery}%",
            $accountId
        );
        if ( !empty( $extensions ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $extensions ), '%s' ) );
            $queryString .= $wpdb->prepare( " AND extension IN ({$placeholders})", $extensions );
        }
        if ( $scope === 'parent' && !empty( $folderId ) ) {
            $queryString .= $wpdb->prepare( " AND parentId = %s", $folderId );
        }
        $queryString .= $wpdb->prepare( " ORDER BY `{$orderBy}` {$order} LIMIT %d", $limit );
        if ( 'ASC' === $order ) {
            $queryString = str_replace( "ORDER BY `{$orderBy}` {$order}", "ORDER BY (CASE WHEN extension = 'folder' THEN 0 ELSE 1 END), `{$orderBy}` ASC", $queryString );
        } else {
            $queryString = str_replace( "ORDER BY `{$orderBy}` {$order}", "ORDER BY (CASE WHEN extension = 'folder' THEN 0 ELSE 1 END), `{$orderBy}` DESC", $queryString );
        }
        $files = $wpdb->get_results( $queryString );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return $this->processFiles( $files );
    }

    /**
     * Retrieves a list of all files associated with the specified account ID.
     *
     * @param string $accountId The ID of the account associated with the files.
     * @param array $config Optional configuration settings for retrieving files.
     *
     * @return array|WP_Error An array of processed file data associated with the specified account.
     */
    public function getFilesByAccountId( $accountId, $config = [] ) {
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $allowedOrderBy = [
            'createdAt',
            'name',
            'updatedAt',
            'size'
        ];
        $order = $this->sanitizeOrder( ( isset( $config['order'] ) ? $config['order'] : 'DESC' ) );
        $orderBy = $this->sanitizeOrderBy( ( isset( $config['orderBy'] ) ? $config['orderBy'] : 'createdAt' ), $allowedOrderBy );
        $page = ( isset( $config['page'] ) ? (int) $config['page'] : 1 );
        $perPage = ( isset( $config['perPage'] ) ? (int) $config['perPage'] : 24 );
        $pagination = $this->sanitizePagination( $page, $perPage );
        $files = $this->fetchAll( "SELECT * FROM %i WHERE accountId = %s ORDER BY `{$orderBy}` {$order} LIMIT %d OFFSET %d", [
            $this->tableName,
            $accountId,
            $pagination['perPage'],
            $pagination['offset']
        ] );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return $this->processFiles( $files );
    }

    /**
     * Retrieves a file by its ID and account ID.
     *
     * This method queries the database for a file associated with the given
     * ID and account ID. If a matching file is found, it processes and returns
     * the file data. If no file is found, an error notice is added and null is
     * returned.
     *
     * @param string $id The ID of the file to retrieve.
     * @param string $accountId The ID of the account associated with the file.
     *
     * @return \CodeConfig\IGD\App\File|array|WP_Error The processed file data if found, otherwise null.
     */
    public function getFile( string $id, string $accountId, $returnType = 'object' ) {
        global $wpdb;
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $file = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM %i WHERE id = %s AND accountId = %s",
            $this->tableName,
            $id,
            $accountId
        ) );
        if ( empty( $file ) ) {
            return new WP_Error(404, __( 'The requested file could not be found. 1', 'integration-google-drive' ));
        }
        return $this->processFile( $file, $returnType );
    }

    /**
     * Retrieves a file by its unique key.
     *
     * This method queries the database for a file associated with the given key.
     * If a matching file is found, it processes and returns the file data.
     * If no file is found, an error notice is added and null is returned.
     *
     * @param string $key The unique key identifying the file.
     * @param string $returnType The type of return value, either 'object' or 'array'.
     *
     * @return \CodeConfig\IGD\App\File|array|WP_Error The processed file data if found, otherwise null.
     */
    public function getFileByKey( string $key, string $returnType = 'object' ) {
        $rootKeys = [
            'my-drive',
            'shared',
            'starred',
            'computers',
            'shared-with-me'
        ];
        if ( in_array( $key, $rootKeys ) ) {
            return [];
        }
        global $wpdb;
        if ( empty( $key ) ) {
            return new WP_Error(404, __( 'The requested file could not be found. 4', 'integration-google-drive' ));
        }
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `fileKey` = %s", $this->tableName, $key ) );
        if ( empty( $file ) ) {
            return new WP_Error(404, __( 'The requested file could not be found. 5', 'integration-google-drive' ));
        }
        $file = $this->processFile( $file, $returnType );
        return $file;
    }

    public function getFilesByKeys( array $keys, array $args = [] ) {
        if ( empty( $keys ) ) {
            return [];
        }
        $defaults = [
            'recursive'      => false,
            'returnType'     => 'array',
            'page'           => 1,
            'perPage'        => 24,
            'orderBy'        => 'updatedAt',
            'order'          => 'DESC',
            'search'         => '',
            'searchScope'    => 'folder',
            'searchLocation' => 'cache',
        ];
        $args = wp_parse_args( $args, $defaults );
        $recursive = $args['recursive'];
        $returnType = $args['returnType'];
        $moduleType = $args['moduleType'] ?? '';
        $additionalExtensions = $args['extensions'] ?? [];
        $extensionsFilterType = $args['extensionsFilterType'] ?? '';
        $search = $args['search'];
        $searchScope = $args['searchScope'];
        $namesString = $args['names'] ?? '';
        $namesFilterType = $args['namesFilterType'] ?? '';
        $applyNamesFilter = $args['applyNameFilter'] ?? [];
        $extensions = ccpigdGetAllowedModuleExtensions( $moduleType );
        $allowedExtensions = $this->processExtensions( $extensions, $additionalExtensions, $extensionsFilterType );
        $filesData = $this->getFileAttributesByKeys( $keys, [
            'id',
            'accountId',
            'name',
            'isDir'
        ] );
        if ( is_wp_error( $filesData ) || empty( $filesData ) ) {
            return ( $filesData ?: [] );
        }
        if ( empty( $filesData ) ) {
            return [];
        }
        $ids = array_map( fn( $file ) => $file['id'], $filesData );
        $params = $ids;
        $sql = '';
        if ( !empty( $search ) ) {
            $searchIds = [];
            if ( $searchScope === 'global' ) {
                foreach ( $filesData as $file ) {
                    $searchIds[] = $this->getSuccessors( $file['id'], $file['accountId'] );
                }
                $params = array_merge( ...$searchIds );
            } elseif ( $searchScope === 'folder' && !empty( $args['fileId'] ) ) {
                $params = [$args['fileId']];
            }
            if ( empty( $params ) ) {
                return [];
            }
            $placeholders = implode( ',', array_fill( 0, count( $params ), '%s' ) );
            $sql = "SELECT * FROM %i WHERE (`id` IN ({$placeholders}) OR `parentId` IN ({$placeholders})) AND `name` LIKE %s";
            $params = array_merge( $params, $params, ["%{$search}%"] );
        } elseif ( $recursive ) {
            $placeholders = implode( ',', array_fill( 0, count( $params ), '%s' ) );
            $sql = "SELECT * FROM %i WHERE 1 = 1";
            if ( $moduleType === 'file-browser' ) {
                $sql .= " AND `parentId` IN ({$placeholders})";
                // if (!empty($allowedExtensions) && !in_array('folder', $allowedExtensions)) {
                //     $allowedExtensions[] = 'folder';
                // }
            } else {
                $sql .= " AND (`id` IN ({$placeholders}) OR `parentId` IN ({$placeholders})) AND `extension` != 'folder'";
                $params = array_merge( $params, $params );
            }
        } else {
            if ( !empty( $allowedExtensions ) && !in_array( 'folder', $allowedExtensions ) ) {
                $allowedExtensions[] = 'folder';
            }
            $placeholders = implode( ',', array_fill( 0, count( $params ), '%s' ) );
            $sql = "SELECT * FROM %i WHERE `id` IN ({$placeholders})";
        }
        $filterSql = '';
        $filterParams = [];
        if ( !empty( $allowedExtensions ) ) {
            $extPlaceholders = implode( ',', array_fill( 0, count( $allowedExtensions ), '%s' ) );
            $filterSql .= " AND `extension` IN ({$extPlaceholders})";
            $filterParams = array_merge( $filterParams, $allowedExtensions );
        }
        if ( !empty( $filterSql ) && !empty( $filterParams ) ) {
            $sql .= $filterSql;
            $params = array_merge( $params, $filterParams );
        }
        if ( !empty( $args['orderBy'] ) && !empty( $args['order'] ) ) {
            $allowedOrderBy = [
                'id',
                'name',
                'size',
                'createdAt',
                'updatedAt'
            ];
            $orderBy = $this->sanitizeOrderBy( $args['orderBy'], $allowedOrderBy );
            $order = $this->sanitizeOrder( $args['order'] );
            $offset = $this->sanitizePagination( $args['page'], $args['perPage'] );
            $sql .= " ORDER BY (CASE WHEN extension = 'folder' THEN 0 ELSE 1 END), `{$orderBy}` {$order} LIMIT %d OFFSET %d";
            $params[] = $offset['perPage'];
            $params[] = $offset['offset'];
        }
        $files = $this->fetchAll( $sql, array_merge( [$this->tableName], $params ) );
        $totalParams = $params;
        $totalCountSQL = str_replace( ['SELECT *'], ['SELECT COUNT(*) as count'], $sql );
        if ( strpos( $sql, 'LIMIT %d' ) !== false ) {
            array_pop( $totalParams );
            $totalCountSQL = str_replace( ['LIMIT %d'], [''], $totalCountSQL );
        }
        if ( strpos( $sql, 'OFFSET %d' ) !== false ) {
            array_pop( $totalParams );
            $totalCountSQL = str_replace( ['OFFSET %d'], [''], $totalCountSQL );
        }
        $totalCount = $this->fetch( $totalCountSQL, array_merge( [$this->tableName], $totalParams ) );
        if ( empty( $files ) || is_wp_error( $files ) || is_wp_error( $totalCount ) ) {
            return [];
        }
        $files = $this->processFiles( $files, $returnType, [
            'filterSql'    => $filterSql,
            'filterParams' => $filterParams,
        ] );
        return [
            'files'      => $files,
            'totalCount' => ( isset( $totalCount->count ) ? (int) $totalCount->count : count( $files ) ),
        ];
    }

    /**
     * Retrieve selected attributes from files by their keys.
     * @param array $keys An array of file keys to search for.
     * @param array $attributes An array of attributes to return for each file.
     *                          Defaults to ['id'].
     *                          Example: ['fileKey', 'name'].
     *
     * @return WP_Error|array Returns:
     *                        - A flat array if one attribute is requested (e.g., ['id1', 'id2']).
     *                        - An array of associative arrays if multiple attributes are requested.
     *                        Example:
     *                        [
     *                        ['fileKey' => 'abc123', 'name' => 'File A'],
     *                        ['fileKey' => 'def456', 'name' => 'File B']
     *                        ]
     */
    public function getFileAttributesByKeys( array $keys, array $attributes = ['id'] ) {
        if ( empty( $keys ) ) {
            return [];
        }
        $placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
        $files = $this->fetchAll( "SELECT * FROM %i WHERE `fileKey` IN ({$placeholders})", array_merge( [$this->tableName], $keys ) );
        if ( empty( $files ) ) {
            return [];
        }
        $processedFiles = $this->processFiles( $files, 'object' );
        $firstFile = reset( $processedFiles );
        if ( $this->isValidAccount( $firstFile->accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        if ( count( $attributes ) === 1 ) {
            $attr = $attributes[0];
            $result = [];
            foreach ( $processedFiles as $file ) {
                $result[] = $file->{$attr} ?? null;
            }
            return $result;
        }
        $result = [];
        foreach ( $processedFiles as $file ) {
            $fileData = [];
            foreach ( $attributes as $attr ) {
                $fileData[$attr] = $file->{$attr} ?? null;
            }
            $result[] = $fileData;
        }
        return $result;
    }

    public function addFile( array $data ) {
        $accountId = ( isset( $data['accountId'] ) ? $data['accountId'] : null );
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error('error', __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $file = [
            'id'             => $data['id'] ?? null,
            'fileKey'        => $data['fileKey'] ?? null,
            'name'           => $data['name'] ?? null,
            'description'    => $data['description'] ?? null,
            'parentId'       => $data['parentId'] ?? null,
            'accountId'      => $data['accountId'] ?? null,
            'size'           => $data['size'] ?? null,
            'mimeType'       => $data['mimeType'] ?? null,
            'extension'      => $data['extension'] ?? null,
            'icon'           => $data['icon'] ?? null,
            'thumbnail'      => $data['thumbnail'] ?? null,
            'additionalData' => ( isset( $data['additionalData'] ) ? maybe_serialize( $data['additionalData'] ) : null ),
            'isDir'          => $data['isDir'] ?? null,
            'isShared'       => $data['isShared'] ?? null,
            'isStarred'      => $data['isStarred'] ?? null,
            'media'          => ( isset( $data['media'] ) ? maybe_serialize( $data['media'] ) : null ),
            'permissions'    => ( isset( $data['permissions'] ) ? maybe_serialize( $data['permissions'] ) : null ),
            'createdAt'      => current_time( 'mysql' ),
            'updatedAt'      => current_time( 'mysql' ),
        ];
        if ( empty( $file['id'] ) || empty( $file['accountId'] ) ) {
            return new WP_Error(404, __( 'The requested file could not be found. 7', 'integration-google-drive' ));
        }
        $format = [
            '%s',
            // id
            '%s',
            // fileKey
            '%s',
            // name
            '%s',
            // description
            '%s',
            // parentId
            '%s',
            // accountId
            '%d',
            // size
            '%s',
            // mimeType
            '%s',
            // extension
            '%s',
            // icon
            '%s',
            // thumbnail
            '%s',
            // additionalData
            '%d',
            // isDir
            '%d',
            // isShared
            '%d',
            // isStarred
            '%s',
            // media
            '%s',
            // permissions
            '%s',
            // createdAt
            '%s',
        ];
        if ( $this->isCachedFile( $file["id"], $file["accountId"] ) ) {
            $id = $file['id'];
            $accountId = $file['accountId'];
            unset($file["id"]);
            unset($file["fileKey"]);
            unset($file["createdAt"]);
            $updateFormat = array_slice( $format, 2 );
            // Remove id, key, and createdAt formats
            array_pop( $updateFormat );
            // Remove createdAt format
            return $this->update(
                $file,
                [
                    'id'        => $id,
                    'accountId' => $accountId,
                ],
                $updateFormat,
                ['%s', '%s']
            );
        }
        return $this->insert( $file, $format );
    }

    /**
     * Delete a file from the database
     *
     * @param string $id The ID of the file to be deleted
     *
     * @return bool|WP_Error True if the deletion was successful, false otherwise
     */
    public function deleteFile( $id, $accountId ) {
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error('error', __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $file = $this->getFile( $id, $accountId );
        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( $file->isDir ) {
            $successors = $this->getSuccessors( $id, $accountId );
            if ( is_wp_error( $successors ) ) {
                return $successors;
            }
            if ( empty( $successors ) ) {
                return 0;
            }
            global $wpdb;
            $placeholders = implode( ',', array_fill( 0, count( $successors ), '%s' ) );
            $sql = $wpdb->prepare( "DELETE FROM %i WHERE (id IN ({$placeholders}) OR parentId IN ({$placeholders})) AND accountId = %s", array_merge(
                [$this->tableName],
                $successors,
                $successors,
                [$accountId]
            ) );
            return $wpdb->query( $sql ) !== false;
        }
        return $this->delete( [
            'id'        => $id,
            'accountId' => $accountId,
        ], ['%s', '%s'] );
    }

    /**
     * Deletes all files associated with a given account ID from the database.
     *
     * @param string $accountId The ID of the account whose files are to be deleted.
     * @return bool|WP_Error True if the deletion was successful, false otherwise or an error.
     */
    public function deleteFilesByAccountId( $accountId ) {
        return $this->delete( [
            'accountId' => $accountId,
        ], ['%s'] );
    }

    /**
     * Update a file in the database
     *
     * @param string $id The ID of the file to be updated
     * @param array $data The data to be updated
     * @param array $dataFormat The format of the data
     * @return bool|WP_Error True if the update was successful, false otherwise
     */
    public function updateFile( $id, $data, $dataFormat ) {
        return $this->update(
            $data,
            [
                'id' => $id,
            ],
            $dataFormat,
            ['%s']
        );
    }

    public function isCachedFolder( $folderId, $accountId ) {
        global $wpdb;
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $folder = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM %i WHERE parentId = %s AND accountId = %s",
            $this->tableName,
            $folderId,
            $accountId
        ) );
        return !empty( $folder );
    }

    public function isCachedFile( $folderId, $accountId ) {
        if ( $this->isValidAccount( $accountId ) === false ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $folder = $this->fetch( "SELECT * FROM %i WHERE id = %s AND accountId = %s", [$this->tableName, $folderId, $accountId] );
        return !empty( $folder );
    }

    /**
     * Check if a file exists by ID and account ID
     *
     * @param string $id File ID
     * @param string $accountId Account ID
     * @return bool|WP_Error True if file exists, false otherwise
     */
    public function fileExists( $id, $accountId ) {
        if ( empty( $id ) || empty( $accountId ) ) {
            return false;
        }
        return $this->exists( [
            'id'        => $id,
            'accountId' => $accountId,
        ] );
    }

    /**
     * Get file count for an account
     *
     * @param string $accountId Account ID
     * @return int|WP_Error Number of files or WP_Error on failure
     */
    public function getFileCountByAccount( $accountId ) {
        global $wpdb;
        if ( empty( $accountId ) ) {
            return new WP_Error(400, __( 'Account ID is required.', 'integration-google-drive' ));
        }
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE accountId = %s", $this->tableName, $accountId ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return (int) $result ?? 0;
    }

    /**
     * Get files with pagination and filtering
     *
     * @param array $args Query arguments
     * @return array|WP_Error Array of files or WP_Error on failure
     */
    public function getFilesPaginated( $args = [] ) {
        $defaults = [
            'accountId' => '',
            'parentId'  => '',
            'page'      => 1,
            'perPage'   => 24,
            'orderBy'   => 'createdAt',
            'order'     => 'DESC',
            'search'    => '',
            'extension' => '',
        ];
        $args = array_merge( $defaults, $args );
        if ( !$this->isValidAccount( $args['accountId'] ) ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $allowedOrderBy = [
            'id',
            'name',
            'size',
            'createdAt',
            'updatedAt'
        ];
        $orderBy = $this->sanitizeOrderBy( $args['orderBy'], $allowedOrderBy );
        $order = $this->sanitizeOrder( $args['order'] );
        $pagination = $this->sanitizePagination( $args['page'], $args['perPage'] );
        $where = ['accountId = %s'];
        $values = [$args['accountId']];
        if ( !empty( $args['parentId'] ) ) {
            $where[] = 'parentId = %s';
            $values[] = $args['parentId'];
        }
        if ( !empty( $args['search'] ) ) {
            $where[] = 'name LIKE %s';
            $values[] = '%' . $args['search'] . '%';
        }
        if ( !empty( $args['extension'] ) ) {
            $where[] = 'extension = %s';
            $values[] = $args['extension'];
        }
        $whereClause = implode( ' AND ', $where );
        $values[] = $pagination['perPage'];
        $values[] = $pagination['offset'];
        $files = $this->fetchAll( "SELECT * FROM %i WHERE {$whereClause} ORDER BY `{$orderBy}` {$order} LIMIT %d OFFSET %d", array_merge( [$this->tableName], $values ) );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return [
            'files'      => $this->processFiles( $files ),
            'pagination' => $pagination,
            'total'      => $this->getFileCountByAccount( $args['accountId'] ),
        ];
    }

    /**
     * Get files by extension
     *
     * @param string $accountId Account ID
     * @param string $extension File extension
     * @param int $limit Limit number of results
     * @return array|WP_Error Array of files or WP_Error on failure
     */
    public function getFilesByExtension( $accountId, $extension, $limit = 100 ) {
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $limit = max( 1, min( 1000, (int) $limit ) );
        $files = $this->fetchAll( "SELECT * FROM %i WHERE accountId = %s AND extension = %s LIMIT %d", [
            $this->tableName,
            $accountId,
            $extension,
            $limit
        ] );
        if ( is_wp_error( $files ) ) {
            return $files;
        }
        return $this->processFiles( $files );
    }

    /**
     * Batch delete files by IDs and account ID
     *
     * @param array $fileIds Array of file IDs
     * @param string $accountId Account ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function batchDeleteFiles( $fileIds, $accountId ) {
        if ( empty( $fileIds ) || !is_array( $fileIds ) ) {
            return new WP_Error(400, __( 'File IDs are required.', 'integration-google-drive' ));
        }
        if ( !$this->isValidAccount( $accountId ) ) {
            return new WP_Error(403, __( 'This account is lost or does not exist. Please re-authorize it.', 'integration-google-drive' ));
        }
        $success_count = 0;
        $total_count = count( $fileIds );
        foreach ( $fileIds as $fileId ) {
            if ( empty( $fileId ) ) {
                continue;
            }
            $result = $this->delete( [
                'id'        => $fileId,
                'accountId' => $accountId,
            ], ['%s', '%s'] );
            if ( !is_wp_error( $result ) && $result ) {
                $success_count++;
            }
        }
        if ( $success_count === 0 ) {
            return new WP_Error(500, __( 'Failed to delete any files in batch operation.', 'integration-google-drive' ));
        }
        return $success_count === $total_count;
    }

    /**
     * Counts the number of files in a folder
     *
     * @param string $folderId The ID of the folder
     * @param string $accountId The ID of the account
     *
     * @return int|WP_Error The number of files in the folder, or a WP_Error if the query fails
     */
    public function childrenCount( $folderId, $accountId, $filter = null ) {
        global $wpdb;
        $sql = $wpdb->prepare( "SELECT COUNT(*) as count FROM %i WHERE parentId = %s AND accountId = %s", [$this->tableName, $folderId, $accountId] );
        if ( !empty( $filter['filterParams'] ) && !empty( $filter['filterSql'] ) ) {
            $sql .= $wpdb->prepare( $filter['filterSql'], $filter['filterParams'] );
        }
        $count = $this->fetch( $sql );
        if ( is_wp_error( $count ) ) {
            return $count;
        }
        if ( !isset( $count->count ) ) {
            return 0;
        }
        return $count->count;
    }

    public function getSuccessors( $parentId, $accountId ) {
        $successor = [];
        $folders = $this->getChildFolderIds( $parentId, $accountId );
        foreach ( $folders as $folderRow ) {
            $folderId = $folderRow['id'];
            $successor[] = $folderId;
            $childFolders = $this->getChildFolderIds( $folderId, $accountId );
            if ( !empty( $childFolders ) ) {
                $successor = array_merge( $successor, $this->getSuccessors( $folderId, $accountId ) );
            }
        }
        $successor[] = $parentId;
        return array_unique( $successor );
    }

    public function getSharedKey( $fileKey, $options = [] ) {
        $defaults = [
            'expireIn' => 3600,
            'password' => null,
        ];
        $options = wp_parse_args( $options, $defaults );
        $expireIn = intval( $options['expireIn'] );
        $password = sanitize_text_field( $options['password'] ?? null );
        $expiry = ( $expireIn > 0 ? time() + $expireIn : 0 );
        $passwordHash = ( !empty( $password ) ? md5( $password ) : '' );
        $sharedData = $this->getSharedData( $fileKey );
        $key = md5( "{$fileKey}|{$expiry}|{$passwordHash}" );
        if ( !empty( $sharedData[$key] ) && $sharedData[$key]['expiry'] >= time() ) {
            return "{$fileKey}-{$key}";
        }
        $sharedData[$key] = [
            'expiry'     => $expiry,
            'password'   => $passwordHash,
            'viewCount'  => 0,
            'lastViewed' => null,
        ];
        // Save entire sharedData list
        $this->saveSharedData( $fileKey, $sharedData );
        return "{$fileKey}-{$key}";
    }

    public function validateSharedLink( $combinedKey, $password = '' ) {
        [$fileKey, $linkKey] = $this->parseCombinedKey( $combinedKey );
        if ( !$fileKey || !$linkKey ) {
            return false;
        }
        $sharedData = $this->getSharedData( $fileKey );
        if ( empty( $sharedData[$linkKey] ) ) {
            return false;
        }
        $shareInfo = $sharedData[$linkKey];
        if ( $shareInfo['expiry'] < time() && $shareInfo['expiry'] != 0 ) {
            $this->deleteSharedEntry( $fileKey, $linkKey );
            return false;
        }
        $hashedPassword = md5( sanitize_text_field( $password ) );
        if ( !empty( $shareInfo['password'] ) ) {
            if ( empty( $password ) ) {
                return new WP_Error('password_required', __( 'This shared link is protected by a password. Please provide the password to access the file.', 'integration-google-drive' ));
            }
            if ( $shareInfo['password'] !== $hashedPassword ) {
                return new WP_Error('invalid_password', __( 'The provided password is incorrect.', 'integration-google-drive' ));
            }
        }
        $shareInfo['viewCount'] = intval( $shareInfo['viewCount'] ?? 0 ) + 1;
        $shareInfo['lastViewed'] = current_time( 'mysql' );
        $result = $this->updateSharedData( $combinedKey, $shareInfo );
        if ( is_wp_error( $result ) ) {
            return false;
        }
        return $sharedData[$linkKey];
    }

    public function validateDownloadLink( $combinedKey, $password = '' ) {
        [$fileKey, $linkKey] = $this->parseCombinedKey( $combinedKey );
        if ( !$fileKey || !$linkKey ) {
            return false;
        }
        $downloadData = $this->getDownloadData( $fileKey );
        if ( empty( $downloadData[$linkKey] ) ) {
            return false;
        }
        $downloadInfo = $downloadData[$linkKey];
        if ( $downloadInfo['expiry'] < time() && $downloadInfo['expiry'] != 0 ) {
            $this->deleteDownloadEntry( $fileKey, $linkKey );
            return false;
        }
        $downloadLimit = intval( $downloadInfo['limit'] ?? 0 );
        if ( $downloadLimit > 0 && intval( $downloadInfo['downloadCount'] ?? 0 ) >= $downloadLimit ) {
            $this->deleteDownloadEntry( $fileKey, $linkKey );
            return new WP_Error('download_limit_exceeded', __( 'The download limit for this link has been exceeded.', 'integration-google-drive' ));
        }
        $hashedPassword = md5( sanitize_text_field( $password ) );
        if ( !empty( $downloadInfo['password'] ) ) {
            if ( empty( $password ) ) {
                return new WP_Error('password_required', __( 'This shared link is protected by a password. Please provide the password to access the file.', 'integration-google-drive' ));
            }
            if ( $downloadInfo['password'] !== $hashedPassword ) {
                return new WP_Error('invalid_password', __( 'The provided password is incorrect.', 'integration-google-drive' ));
            }
        }
        $downloadInfo['downloadCount'] = intval( $downloadInfo['downloadCount'] ?? 0 ) + 1;
        $downloadInfo['lastViewed'] = current_time( 'mysql' );
        $result = $this->updateDownloadData( $combinedKey, $downloadInfo );
        if ( is_wp_error( $result ) ) {
            return false;
        }
        return $downloadData[$linkKey];
    }

    public function getDownloadKey( $fileKey, $options = [] ) {
        $defaults = [
            'expireIn' => 3600,
            'password' => null,
            'limit'    => 0,
        ];
        $options = wp_parse_args( $options, $defaults );
        $expireIn = intval( $options['expireIn'] );
        $password = sanitize_text_field( $options['password'] ?? null );
        $limit = intval( $options['limit'] );
        $expiry = ( $expireIn > 0 ? time() + $expireIn : 0 );
        $passwordHash = ( !empty( $password ) ? md5( $password ) : '' );
        $downloadData = $this->getDownloadData( $fileKey );
        $key = md5( "{$fileKey}|{$expiry}|{$passwordHash}|{$limit}" );
        if ( !empty( $downloadData[$key] ) && $downloadData[$key]['expiry'] >= time() ) {
            return "{$fileKey}-{$key}";
        }
        $downloadData[$key] = [
            'expiry'     => $expiry,
            'password'   => $passwordHash,
            'limit'      => $limit,
            'viewCount'  => 0,
            'lastViewed' => null,
        ];
        // Save entire sharedData list
        $this->saveDownloadData( $fileKey, $downloadData );
        return "{$fileKey}-{$key}";
    }

    // =============================== PRIVATE METHODS =============================== //
    private function parseCombinedKey( $sharedKey ) {
        $parts = explode( '-', $sharedKey, 2 );
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function getSharedData( $fileKey ) {
        global $wpdb;
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT metaData FROM %i WHERE fileKey = %s", $this->tableName, $fileKey ) );
        if ( !$file ) {
            return [];
        }
        $metaData = maybe_unserialize( $file->metaData );
        return $metaData['sharedData'] ?? [];
    }

    private function saveSharedData( $fileKey, $sharedData ) {
        global $wpdb;
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT metaData FROM %i WHERE fileKey = %s", $this->tableName, $fileKey ) );
        if ( !$file ) {
            return false;
        }
        $metaData = maybe_unserialize( $file->metaData ) ?? [];
        $metaData['sharedData'] = $sharedData;
        return $wpdb->update(
            $this->tableName,
            [
                'metaData' => maybe_serialize( $metaData ),
            ],
            [
                'fileKey' => $fileKey,
            ],
            ['%s'],
            ['%s']
        );
    }

    private function deleteSharedEntry( $fileKey, $linkKey ) {
        $sharedData = $this->getSharedData( $fileKey );
        if ( isset( $sharedData[$linkKey] ) ) {
            unset($sharedData[$linkKey]);
            return $this->saveSharedData( $fileKey, $sharedData );
        }
        return false;
    }

    public function updateSharedData( $combinedKey, $updates = [] ) {
        [$fileKey, $linkKey] = $this->parseCombinedKey( $combinedKey );
        if ( !$fileKey || !$linkKey ) {
            return false;
        }
        $sharedData = $this->getSharedData( $fileKey );
        if ( empty( $sharedData[$linkKey] ) ) {
            return false;
        }
        // Only update provided fields
        $sharedData[$linkKey] = array_merge( $sharedData[$linkKey], array_filter( $updates, function ( $v ) {
            return $v !== null;
        } ) );
        return $this->saveSharedData( $fileKey, $sharedData );
    }

    private function getDownloadData( $fileKey ) {
        global $wpdb;
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT metaData, extension FROM %i WHERE fileKey = %s", $this->tableName, $fileKey ) );
        if ( !$file ) {
            return [];
        }
        if ( $file->extension === 'folder' ) {
            return [];
        }
        $metaData = maybe_unserialize( $file->metaData );
        return $metaData['downloadData'] ?? [];
    }

    private function saveDownloadData( $fileKey, $downloadData ) {
        global $wpdb;
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT metaData FROM %i WHERE fileKey = %s", $this->tableName, $fileKey ) );
        if ( !$file ) {
            return false;
        }
        $metaData = maybe_unserialize( $file->metaData ) ?? [];
        $metaData['downloadData'] = $downloadData;
        return $wpdb->update(
            $this->tableName,
            [
                'metaData' => maybe_serialize( $metaData ),
            ],
            [
                'fileKey' => $fileKey,
            ],
            ['%s'],
            ['%s']
        );
    }

    public function updateDownloadData( $combinedKey, $updates = [] ) {
        [$fileKey, $linkKey] = $this->parseCombinedKey( $combinedKey );
        if ( !$fileKey || !$linkKey ) {
            return false;
        }
        $downloadData = $this->getDownloadData( $fileKey );
        if ( empty( $downloadData[$linkKey] ) ) {
            return false;
        }
        // Only update provided fields
        $downloadData[$linkKey] = array_merge( $downloadData[$linkKey], array_filter( $updates, function ( $v ) {
            return $v !== null;
        } ) );
        return $this->saveDownloadData( $fileKey, $downloadData );
    }

    private function deleteDownloadEntry( $fileKey, $linkKey ) {
        $downloadData = $this->getDownloadData( $fileKey );
        if ( isset( $downloadData[$linkKey] ) ) {
            unset($downloadData[$linkKey]);
            return $this->saveDownloadData( $fileKey, $downloadData );
        }
        return false;
    }

    private function processFiles( $files, $returnType = 'array', $filter = null ) {
        $processedFiles = [];
        foreach ( $files as $file ) {
            $processedFiles[] = $this->processFile( $file, $returnType, $filter );
        }
        return $processedFiles;
    }

    /**
     * Process a file object and return an enriched array representation.
     *
     * @param object|null $file
     * @return array|\CodeConfig\IGD\App\File
     */
    private function processFile( $file, $returnType = 'object', $filter = null ) {
        if ( empty( $file ) ) {
            return [];
        }
        $fileData = [
            'id'             => $file->id,
            'fileKey'        => $file->fileKey,
            'name'           => $file->name,
            'description'    => $file->description,
            'parentId'       => $file->parentId,
            'accountId'      => $file->accountId,
            'size'           => $file->size,
            'mimeType'       => $file->mimeType,
            'extension'      => $file->extension,
            'icon'           => $file->icon,
            'additionalData' => maybe_unserialize( $file->additionalData ),
            'isDir'          => $file->isDir,
            'isShared'       => $file->isShared,
            'isStarred'      => $file->isStarred,
            'media'          => maybe_unserialize( $file->media ),
            'permissions'    => maybe_unserialize( $file->permissions ),
            'createdAt'      => $file->createdAt,
            'updatedAt'      => $file->updatedAt,
        ];
        $fileData['thumbnail'] = ( Helpers::checkLifeTime( $file->updatedAt ) > 0 ? $file->thumbnail : null );
        if ( $returnType === 'object' ) {
            $file = new \CodeConfig\IGD\App\File($fileData);
            return $file;
        } elseif ( $returnType === 'array' ) {
            return $fileData;
        }
        return [
            'id'       => $file->id,
            'name'     => $file->name,
            'fileKey'  => $file->fileKey,
            'mimeType' => $file->mimeType,
            'size'     => $file->size,
        ];
    }

    private function processExtensions( array $extensions, array $additionalExtensions, string $filterType ) : array {
        if ( empty( $additionalExtensions ) ) {
            return $extensions;
        }
        if ( empty( $extensions ) ) {
            return $additionalExtensions;
        }
        if ( $filterType === 'include' ) {
            $filterExtensions = array_filter( $extensions, function ( $ext ) use($additionalExtensions) {
                return in_array( $ext, $additionalExtensions );
            } );
            return array_values( $filterExtensions );
        } elseif ( $filterType === 'exclude' ) {
            $filterExtensions = array_filter( $extensions, function ( $ext ) use($additionalExtensions) {
                return !in_array( $ext, $additionalExtensions );
            } );
            return array_values( $filterExtensions );
        }
        return $extensions;
    }

    private function getChildFolderIds( $parentId, $accountId ) {
        return $this->fetchAll( "SELECT `id` FROM %i WHERE `parentId` = %s AND `accountId` = %s AND `extension` = 'folder'", [$this->tableName, $parentId, $accountId], ARRAY_A );
    }

}
