<?php

namespace CodeConfig\IGD\Models;

use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Shortcode as IGDShortcode;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\Singleton;
use function count;
use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function is_string;
use WP_Error;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Shortcode extends BaseModel {
    use Singleton;
    private $breadcrumbs;

    public function __construct() {
        parent::__construct( 'integration_google_drive_shortcodes' );
    }

    /**
     * Retrieve a shortcode by its ID.
     *
     * @param int $id The ID of the shortcode to retrieve.
     * @return array|WP_Error Array containing shortcode data or WP_Error if the ID is invalid or an error occurs.
     */
    public function get( $id, array $config = [] ) {
        if ( empty( $id ) ) {
            return new WP_Error(404, __( 'Shortcode ID is required.', 'integration-google-drive' ));
        }
        $shortcode = $this->fetchShortcode( $id );
        if ( is_wp_error( $shortcode ) ) {
            return $shortcode;
        }
        return $this->processData( $shortcode, $config );
    }

    public function getAll( array $config ) {
        $defaults = [
            'type'    => 'all',
            'search'  => '',
            'status'  => 'all',
            'order'   => 'DESC',
            'orderBy' => 'updatedAt',
            'page'    => 1,
            'perPage' => 10,
        ];
        $config = wp_parse_args( $config, $defaults );
        $allowedOrderBy = [
            'title',
            'type',
            'status',
            'id',
            'createdAt',
            'updatedAt'
        ];
        $orderBy = $this->sanitizeOrderBy( $config['orderBy'], $allowedOrderBy );
        $order = $this->sanitizeOrder( $config['order'] );
        $pagination = $this->sanitizePagination( $config['page'], $config['perPage'] );
        $sqlParts = $this->wpdb->prepare( "SELECT * FROM %i WHERE 1=1", $this->tableName );
        if ( $config['type'] !== 'all' ) {
            $sqlParts .= $this->wpdb->prepare( " AND type = %s", $config['type'] );
        }
        if ( $config['status'] !== 'all' ) {
            $sqlParts .= $this->wpdb->prepare( " AND status = %s", $config['status'] );
        }
        if ( !empty( $config['search'] ) ) {
            $sqlParts .= $this->wpdb->prepare( " AND title LIKE %s", '%' . $config['search'] . '%' );
        }
        if ( $order === 'DESC' ) {
            $sqlParts .= $this->wpdb->prepare( " ORDER BY %i DESC", $orderBy );
        } else {
            $sqlParts .= $this->wpdb->prepare( " ORDER BY %i ASC", $orderBy );
        }
        if ( $pagination['perPage'] > 0 ) {
            $sqlParts .= $this->wpdb->prepare( " LIMIT %d OFFSET %d", $pagination['perPage'], $pagination['offset'] );
        }
        $results = $this->fetchAll( $sqlParts, [], ARRAY_A );
        if ( is_wp_error( $results ) ) {
            return $results;
        }
        $processData = [];
        foreach ( $results as $result ) {
            $processData[] = $this->processData( $result, [
                'dataProcess' => false,
            ] );
        }
        return $processData;
    }

    public function add( array $data, $force = false ) {
        if ( !in_array( $data['type'], IGDShortcode::getModulesList() ) ) {
            return new WP_Error('invalid_type', __( 'Invalid shortcode type.', 'integration-google-drive' ), [
                'status' => 400,
            ]);
        }
        $now = current_time( 'mysql' );
        $is_update = !empty( $data['id'] ) && is_numeric( $data['id'] );
        if ( $force && $is_update ) {
            $exists = $this->shortcodeExists( (int) $data['id'] );
            if ( !$exists ) {
                $is_update = false;
            }
        }
        if ( !empty( $data['data'] ) && is_array( $data['data'] ) ) {
            $data['data'] = $this->processAndSerializeModuleData( $data['type'], $data['data'] );
        }
        if ( isset( $data['locations'] ) ) {
            unset($data['locations']);
        }
        if ( $is_update ) {
            $id = $data['id'];
            unset($data['id'], $data['createdAt']);
            $data['updatedAt'] = $now;
            $format = $this->generateFormat( $data );
            $where_format = ['%d'];
            $result = $this->update(
                $data,
                [
                    'id' => $id,
                ],
                $format,
                $where_format,
                ARRAY_A
            );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            return $this->processData( $result );
        } else {
            if ( empty( $data['type'] ) ) {
                return new WP_Error(404, __( 'Shortcode type is required.', 'integration-google-drive' ));
            }
            if ( empty( $data['data'] ) ) {
                return new WP_Error(404, __( 'Shortcode data is required.', 'integration-google-drive' ));
            }
            // Apply defaults
            $data['title'] ??= 'Untitled';
            $data['status'] ??= 'on';
            $data['createdAt'] = $now;
            $data['updatedAt'] = $now;
            $format = $this->generateFormat( $data );
            $inserted = $this->insert( $data, $format, ARRAY_A );
            if ( is_wp_error( $inserted ) ) {
                return $inserted;
            }
            return $this->processData( $inserted );
        }
    }

    public function getShortcode( $id, $key = null ) {
        if ( empty( $id ) ) {
            return new WP_Error('invalid_id', __( 'Invalid ID provided.', 'integration-google-drive' ), [
                'status' => 404,
            ]);
        }
        $shortcode = $this->fetchShortcode( $id );
        if ( is_wp_error( $shortcode ) ) {
            return $shortcode;
        }
        if ( isset( $shortcode['data'] ) && is_serialized( $shortcode['data'] ) ) {
            $shortcode['data'] = maybe_unserialize( $shortcode['data'] );
        }
        if ( empty( $key ) ) {
            return $shortcode;
        }
        if ( strpos( $key, '.' ) !== false ) {
            $keys = explode( '.', $key );
            $value = $shortcode;
            foreach ( $keys as $innerKey ) {
                if ( !is_array( $value ) || !array_key_exists( $innerKey, $value ) ) {
                    return null;
                }
                $value = $value[$innerKey];
            }
            return $value;
        }
        return $shortcode[$key] ?? null;
    }

    /**
     * Delete shortcodes from the database.
     *
     * @param int|array $ids The ID or IDs of the shortcodes to delete.
     * @return int|WP_Error The number of rows affected or a WP_Error object if an error occurs.
     */
    public function remove( $ids ) {
        if ( !is_array( $ids ) ) {
            $ids = [$ids];
        }
        if ( empty( $ids ) ) {
            return 0;
        }
        foreach ( $ids as $id ) {
            if ( !is_numeric( $id ) ) {
                return new WP_Error(404, __( 'Invalid ID provided.', 'integration-google-drive' ));
            }
        }
        $success_count = 0;
        $total_count = count( $ids );
        foreach ( $ids as $id ) {
            $result = $this->delete( [
                'id' => (int) $id,
            ], ['%d'] );
            if ( !is_wp_error( $result ) && $result ) {
                $success_count++;
            }
        }
        if ( $success_count === 0 ) {
            return new WP_Error(500, __( 'Failed to delete any shortcodes.', 'integration-google-drive' ));
        }
        return $success_count;
    }

    public function duplicate( $ids ) {
        global $wpdb;
        if ( !is_array( $ids ) ) {
            $ids = [$ids];
        }
        if ( empty( $ids ) ) {
            return new WP_Error(404, __( 'Invalid ID provided.', 'integration-google-drive' ));
        }
        foreach ( $ids as $id ) {
            if ( !is_numeric( $id ) ) {
                return new WP_Error(404, __( 'Invalid ID provided.', 'integration-google-drive' ));
            }
        }
        $shortcodes = [];
        foreach ( $ids as $id ) {
            $shortcode = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $this->tableName, $id ), ARRAY_A );
            if ( is_wp_error( $shortcode ) ) {
                return $shortcode;
            }
            if ( !empty( $shortcode ) ) {
                $shortcodes[] = $shortcode;
            }
        }
        if ( empty( $shortcodes ) ) {
            return new WP_Error(404, __( 'Invalid ID provided.', 'integration-google-drive' ));
        }
        $results = 0;
        foreach ( $shortcodes as $shortcode ) {
            $shortcode['title'] .= ' - Copy';
            $shortcode['status'] = 'off';
            unset($shortcode['id']);
            $shortcode['createdAt'] = current_time( 'mysql' );
            $shortcode['updatedAt'] = current_time( 'mysql' );
            $result = $this->insert( $shortcode, $this->generateFormat( $shortcode ) );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $results++;
        }
        return $results;
    }

    public function totalCount( $config = [] ) {
        $defaultConfig = [
            'type'   => 'all',
            'search' => '',
            'status' => 'all',
        ];
        $config = wp_parse_args( $config, $defaultConfig );
        $type = $config['type'];
        $search = $config['search'];
        $status = $config['status'];
        $sql = $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE 1=1", $this->tableName );
        if ( $type !== 'all' ) {
            $sql .= $this->wpdb->prepare( " AND type = %s", $type );
        }
        if ( $status !== 'all' ) {
            $sql .= $this->wpdb->prepare( " AND status = %s", $status );
        }
        if ( !empty( $search ) ) {
            $search = '%' . $this->wpdb->esc_like( $search ) . '%';
            $sql .= $this->wpdb->prepare( " AND title LIKE %s", $search );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var( $sql );
        if ( $this->wpdb->last_error ) {
            return new WP_Error(400, __( 'A database error occurred: ', 'integration-google-drive' ) . $this->wpdb->last_error);
        }
        return (int) $count;
    }

    // ========================= Utility methods =========================
    /**
     * Check if a shortcode exists by ID.
     *
     * @param int $id The shortcode ID.
     * @return bool True if exists, false otherwise.
     */
    public function shortcodeExists( $id ) {
        return $this->exists( [
            'id' => (string) $id,
        ] );
    }

    /**
     * Get a specific column value for a shortcode.
     *
     * @param string $column The column title.
     * @param int $id The shortcode ID.
     * @return mixed|null The column value or null if not found.
     */
    public function getShortcodeColumn( $column, $id ) {
        return $this->getColumn( $column, [
            'id' => (string) $id,
        ] );
    }

    /**
     * Get shortcode title by ID.
     *
     * @param int $id The shortcode ID.
     * @return string|null The shortcode title or null if not found.
     */
    public function getShortcodeTitle( $id ) {
        return $this->getColumn( 'title', [
            'id' => (string) $id,
        ] );
    }

    /**
     * Update shortcode status.
     *
     * @param int $id The shortcode ID.
     * @param string $status The new status.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function updateStatus( $id, $status ) {
        return $this->update(
            [
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'id' => (string) $id,
            ],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function insertFile( $id, $fileKey ) {
        $shortcode = $this->getShortcode( $id );
        if ( is_wp_error( $shortcode ) ) {
            return $shortcode;
        }
        if ( empty( $shortcode['data']['source']['fileKeys'] ) || !is_array( $shortcode['data']['source']['fileKeys'] ) ) {
            $shortcode['data']['source']['fileKeys'] = [];
        }
        foreach ( $shortcode['data']['source']['fileKeys'] as $existingFile ) {
            if ( isset( $existingFile['fileKey'] ) && $existingFile['fileKey'] === $fileKey ) {
                return true;
            }
        }
        $shortcode['data']['source']['fileKeys'][] = [
            'fileKey'      => $fileKey,
            'thumbnailKey' => '',
        ];
        return $this->add( $shortcode );
    }

    public function import( $shortcodesData ) {
        $importedCount = 0;
        foreach ( $shortcodesData as $shortcodeData ) {
            $data = [
                'id'          => $shortcodeData['id'] ?? null,
                'title'       => $shortcodeData['title'] ?? 'Untitled',
                'type'        => $shortcodeData['type'] ?? '',
                'status'      => $shortcodeData['status'] ?? 'inactive',
                'integration' => $shortcodeData['integration'] ?? '',
                'locations'   => maybe_unserialize( $shortcodeData['locations'] ?? [] ) ?? [],
                'data'        => maybe_unserialize( $shortcodeData['data'] ?? [] ) ?? [],
            ];
            $validatedData = $this->validateShortcodeData( $data );
            if ( empty( $validatedData ) ) {
                continue;
            }
            $result = $this->add( $validatedData, true );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $importedCount++;
        }
        return $importedCount;
    }

    // ========================= Private methods =========================
    private function processAndSerializeModuleData( $type, $data ) {
        $processedData = [];
        foreach ( $data as $key => $value ) {
            if ( $key === 'source' ) {
                $fileKyeAndThumbnailKeys = $value['fileKeys'] ?? [];
                $processedData['source']['fileKeys'] = $fileKyeAndThumbnailKeys;
                $processedData['source']['privateFolder'] = filter_var( $value['privateFolder'] ?? false, FILTER_VALIDATE_BOOLEAN );
            } else {
                $processedData[$key] = $value;
            }
        }
        return maybe_serialize( $processedData );
    }

    private function validateShortcodeData( $data ) {
        if ( empty( $data ) || !is_array( $data ) || empty( $data['type'] ) || !in_array( $data['type'], IGDShortcode::getModulesList() ) ) {
            return [];
        }
        $sanitizedData = [];
        if ( is_string( $data ) && is_serialized( $data ) ) {
            $data = maybe_unserialize( $data );
        }
        $defaultModuleData = ccpigdGetModuleDefaultData( $data['type'] );
        if ( is_wp_error( $defaultModuleData ) && empty( $defaultModuleData ) ) {
            return [];
        }
        foreach ( $defaultModuleData as $key => $value ) {
            if ( !empty( $data[$key] ) ) {
                if ( is_array( $value ) ) {
                    if ( is_array( $data[$key] ) ) {
                        $sanitizedData[$key] = ( is_array( $data[$key] ) ? $data[$key] : $value );
                    } else {
                        $sanitizedData[$key] = $value;
                    }
                } else {
                    $sanitizedData[$key] = $data[$key];
                }
            } else {
                $sanitizedData[$key] = $value;
            }
        }
        return $sanitizedData;
    }

    private function generateFormat( $data ) {
        $format = [];
        foreach ( $data as $key => $value ) {
            $format[] = ( is_numeric( $value ) && (int) $value == $value ? '%d' : '%s' );
        }
        return $format;
    }

    /**
     * Processes the input data for a shortcode, handling serialization, file retrieval,
     * and optional schema validation and sanitization.
     *
     * @param array $data The data array containing 'type' and serialized 'data'.
     * @param bool $validateSchema Whether to validate the data against a schema.
     *
     * @return array|WP_Error Processed and optionally validated data.
     */
    private function processData( $data, $config = [] ) {
        if ( empty( $data['type'] ) || empty( $data['data'] ) ) {
            return [];
        }
        $moduleType = $data['type'] ?? '';
        $id = $data['id'] ?? 0;
        if ( empty( $id ) ) {
            return [];
        }
        $default = [
            'validateSchema' => true,
            'returnType'     => 'array',
            'recursive'      => !in_array( $moduleType, ['file-browser', 'file-uploader'] ),
            'page'           => 1,
            'fileKey'        => null,
            'order'          => null,
            'orderBy'        => null,
            'search'         => null,
            'searchScope'    => 'folder',
            'from'           => 'cache',
            'password'       => null,
            'moduleType'     => $moduleType,
            'dataProcess'    => true,
            'shortcodeId'    => $id,
        ];
        $queryConfig = wp_parse_args( $config, $default );
        $isAdmin = ccpigdHasUserAccessPage( 'module_builder' ) && ($queryConfig['isAdmin'] ?? false);
        $validateSchema = $queryConfig['validateSchema'] ?? true;
        $fileKey = $queryConfig['fileKey'] ?? null;
        $order = $queryConfig['order'] ?? null;
        $orderBy = $queryConfig['orderBy'] ?? null;
        $password = $queryConfig['password'] ?? null;
        $processedData = [];
        foreach ( $data as $key => $value ) {
            if ( is_serialized( $value ) ) {
                $value = unserialize( $value );
                if ( $key === 'data' && $queryConfig['dataProcess'] ) {
                    if ( !in_array( $data['type'], IGDShortcode::getModulesList() ) ) {
                        $processedData[$key] = [];
                        continue;
                    }
                    $permissions = $value['permissions'] ?? [];
                    if ( !empty( $permissions ) && !ccpigdHasUserAccessPage( 'module_builder' ) ) {
                        $passwordProtect = $permissions['passwordProtect'] ?? '';
                        if ( isset( $passwordProtect['enable'] ) && $passwordProtect['enable'] && isset( $passwordProtect['password'] ) && !empty( $passwordProtect['password'] ) ) {
                            $storedPassword = $passwordProtect['password'];
                            $cookieKey = "ccpigd_token_{$id}";
                            $secure_hash = hash( 'sha256', $storedPassword );
                            if ( isset( $_COOKIE[$cookieKey] ) && sanitize_text_field( wp_unslash( $_COOKIE[$cookieKey] ) ) !== $secure_hash || empty( $_COOKIE[$cookieKey] ) ) {
                                if ( empty( $password ) ) {
                                    $value['source']['files'] = new WP_Error('password_required', __( 'Password is required', 'integration-google-drive' ), [
                                        'status' => 401,
                                    ]);
                                    $processedData[$key] = $value;
                                    return $processedData;
                                }
                                $new_hash = hash( 'sha256', $password );
                                if ( $secure_hash !== $new_hash ) {
                                    $value['source']['files'] = new WP_Error('password_incorrect', __( 'Password is incorrect', 'integration-google-drive' ), [
                                        'status' => 401,
                                    ]);
                                    $processedData[$key] = $value;
                                    Notices::getInstance()->add( [
                                        'type'        => 'error',
                                        'title'       => __( 'Password Error', 'integration-google-drive' ),
                                        'description' => sprintf(
                                            "A User '%s' tried to access #%d: %s module with an incorrect password.",
                                            wp_get_current_user()->user_login ?? 'Guest',
                                            $id,
                                            $moduleType
                                        ),
                                    ] );
                                    return $processedData;
                                } else {
                                    setcookie(
                                        $cookieKey,
                                        $secure_hash,
                                        time() + DAY_IN_SECONDS,
                                        COOKIEPATH,
                                        COOKIE_DOMAIN,
                                        is_ssl(),
                                        true
                                    );
                                }
                            }
                        }
                    }
                    $sourceFileKeys = $value['source']['fileKeys'] ?? [];
                    if ( !empty( $value['source']['privateFolder'] ) ) {
                        $userAccess = ccpigdGetCurrentUserAccess();
                        if ( !empty( $userAccess['folders'] ) && is_array( $userAccess['folders'] ) ) {
                            $allowedFolderKeys = $userAccess['folders'];
                            $sourceFileKeys = array_map( fn( $_fileKey ) => [
                                'fileKey'      => $_fileKey,
                                'thumbnailKey' => '',
                            ], $allowedFolderKeys );
                        }
                    }
                    $fileKeys = $sourceFileKeys;
                    if ( empty( $fileKeys ) ) {
                        return new WP_Error('no_file_keys', __( 'No file keys specified in the shortcode data.', 'integration-google-drive' ), [
                            'status' => 400,
                        ]);
                    }
                    if ( !empty( $fileKey ) && $fileKey !== '/' && $fileKey !== '' && 'my-drive' !== $fileKey ) {
                        $fileKeys = array_column( $fileKeys, 'fileKey' );
                        if ( Helpers::validateFileKey( $fileKey, $fileKeys ) ) {
                            $fileKeys = [[
                                'fileKey'      => $fileKey,
                                'thumbnailKey' => '',
                            ]];
                            $queryConfig['recursive'] = true;
                        } else {
                            return new WP_Error('file_key_not_allowed', __( 'The specified file key is not allowed for this shortcode.', 'integration-google-drive' ), [
                                'status' => 403,
                            ]);
                        }
                    } elseif ( $moduleType === 'file-uploader' && !$isAdmin ) {
                        $uploadKeys = json_decode( sanitize_text_field( wp_unslash( $_COOKIE["ccpigd_file_uploader_files_{$id}"] ?? '' ) ), true );
                        if ( empty( $uploadKeys ) || !is_array( $uploadKeys ) || count( $fileKeys ) > 1 ) {
                            $processedData[$key] = $value;
                            continue;
                        }
                        $uploadRootFileKey = $sourceFileKeys[0]['fileKey'] ?? '';
                        if ( empty( $uploadRootFileKey ) ) {
                            $processedData[$key] = $value;
                            continue;
                        }
                        $uploadRootFile = Files::getInstance()->getFileByKey( $uploadRootFileKey );
                        if ( is_wp_error( $uploadRootFile ) || empty( $uploadRootFile ) ) {
                            $processedData[$key] = $value;
                            continue;
                        }
                        $files = Files::getInstance()->getFileAttributesByKeys( $uploadKeys, ['parentId', 'fileKey'] );
                        if ( is_wp_error( $files ) || empty( $files ) ) {
                            $processedData[$key] = $value;
                            continue;
                        }
                        $filterUploadKeys = [];
                        foreach ( $files as $uploadFile ) {
                            if ( $uploadFile['parentId'] === $uploadRootFile->id ) {
                                $filterUploadKeys[] = [
                                    'fileKey'      => $uploadFile['fileKey'],
                                    'thumbnailKey' => '',
                                ];
                            }
                        }
                        $fileKeys = ( !empty( $filterUploadKeys ) ? $filterUploadKeys : $fileKeys );
                    } elseif ( $moduleType === 'search-box' && empty( $queryConfig['search'] ) ) {
                        $value['source']['files'] = [];
                        if ( $isAdmin ) {
                            $selectedFiles = $this->getSelectedFiles( $fileKeys, $queryConfig );
                            if ( !is_wp_error( $selectedFiles ) ) {
                                if ( $moduleType === 'media-player' ) {
                                    $selectedFiles = $this->attachThumbnailsToFiles( $selectedFiles, $sourceFileKeys );
                                }
                                $value['source']['selectedFiles'] = $selectedFiles;
                            }
                        }
                        $processedData[$key] = $value;
                        continue;
                    }
                    $advancedTab = $value['advanced'] ?? false;
                    if ( $advancedTab ) {
                        $queryConfig['perPage'] ??= $advancedTab['files']['perPage'] ?? 20;
                        if ( isset( $advancedTab['fileBrowser'] ) && !empty( $advancedTab['fileBrowser'] ) ) {
                            $queryConfig['orderBy'] = $advancedTab['sort']['orderBy'] ?? 'name';
                            $queryConfig['order'] = strtoupper( $advancedTab['sort']['order'] ?? 'ASC' );
                            $queryConfig['from'] = 'cache';
                        } else {
                            if ( empty( $this->isModuleAutoFetch( $id, $advancedTab ?? [] ) ) ) {
                                $queryConfig['from'] = 'cache';
                            }
                            $queryConfig['orderBy'] = $orderBy ?? $advancedTab['sort']['orderBy'] ?? 'name';
                            $queryConfig['order'] = strtoupper( $order ?? $advancedTab['sort']['order'] ?? 'ASC' );
                        }
                    }
                    if ( !empty( $value['filter'] ) ) {
                        // Extensions filter
                        $extensionsFilter = $value['filter']['extension'] ?? [];
                        $allowAllExtensions = $extensionsFilter['all'] ?? true;
                        $include = $extensionsFilter['include'] ?? [];
                        $exclude = $extensionsFilter['exclude'] ?? [];
                        $extensions = ( $allowAllExtensions ? $exclude : $include );
                        $extensionsFilterType = ( $allowAllExtensions ? 'exclude' : 'include' );
                        $queryConfig['extensions'] = $extensions;
                        $queryConfig['extensionsFilterType'] = $extensionsFilterType;
                        $queryConfig['applyNameFilter'] = [];
                        $queryConfig['names'] = '';
                    }
                    $app = App::getInstance();
                    $filesData = $app->getFilesByKeys( $fileKeys, $queryConfig );
                    if ( empty( $filesData ) && empty( $queryConfig['search'] ) ) {
                        $queryConfig['from'] = 'server';
                        $filesData = $app->getFilesByKeys( $fileKeys, $queryConfig );
                    }
                    if ( is_wp_error( $filesData ) ) {
                        $processedData['error'] = [
                            'code'    => $filesData->get_error_code(),
                            'message' => $filesData->get_error_message(),
                        ];
                        continue;
                    }
                    $files = $filesData['files'] ?? [];
                    $perPage = ( isset( $queryConfig['perPage'] ) ? (int) $queryConfig['perPage'] : 20 );
                    $totalCount = ( isset( $filesData['totalCount'] ) ? (int) $filesData['totalCount'] : count( $filesData['files'] ?? [] ) );
                    $currentPage = ( isset( $queryConfig['page'] ) ? (int) $queryConfig['page'] : 1 );
                    $totalPages = ceil( $totalCount / $perPage );
                    $hasMore = $currentPage < $totalPages;
                    $value['source']['totalCount'] = $totalCount;
                    $value['source']['perPage'] = $perPage;
                    $value['source']['currentPage'] = $currentPage;
                    $value['source']['totalPages'] = $totalPages;
                    $value['source']['hasMore'] = $hasMore;
                    if ( $moduleType === 'file-browser' || $moduleType === 'file-uploader' || $moduleType === 'search-box' ) {
                        $breadcrumbKey = $sourceFileKeys[0]['fileKey'] ?? null;
                        $breadcrumbsArgs = [
                            'rootFileKey'    => $breadcrumbKey,
                            'rootFolderName' => 'Home',
                        ];
                        if ( ($moduleType === 'file-uploader' || $moduleType === 'search-box') && !empty( $breadcrumbKey ) ) {
                            $breadcrumbsArgs = [
                                'rootFolderKey'  => $breadcrumbKey,
                                'rootFolderName' => 'Home',
                            ];
                        }
                        $breadcrumbs = App::getInstance()->getBreadcrumbByKey( $fileKey, $breadcrumbsArgs );
                        if ( is_array( $breadcrumbs ) && !empty( $breadcrumbs ) && !is_wp_error( $breadcrumbs ) ) {
                            $value['source']['breadcrumbs'] = array_reverse( $breadcrumbs );
                        }
                    } elseif ( $moduleType === 'media-player' ) {
                        $files = $this->attachThumbnailsToFiles( $files, $sourceFileKeys );
                    }
                    $value['source']['files'] = $files;
                    $value['source']['nextPage'] = ( $hasMore ? $currentPage + 1 : null );
                    if ( $isAdmin ) {
                        $selectedFiles = $this->getSelectedFiles( $fileKeys, $queryConfig );
                        if ( !is_wp_error( $selectedFiles ) ) {
                            if ( $moduleType === 'media-player' ) {
                                $selectedFiles = $this->attachThumbnailsToFiles( $selectedFiles, $sourceFileKeys );
                            }
                            $value['source']['selectedFiles'] = $selectedFiles;
                        }
                    }
                }
                $processedData[$key] = $value;
            } else {
                $processedData[$key] = ( $key === 'id' ? intval( $value ) : $value );
            }
        }
        if ( $validateSchema || !ccpigdHasUserAccessPage( 'module_builder' ) ) {
            $type = $processedData['type'] ?? '';
            if ( empty( $type ) ) {
                return new WP_Error('invalid_type', __( 'Invalid shortcode type.', 'integration-google-drive' ), [
                    'status' => 400,
                ]);
            }
            $schema = ccpigdGetShortcodeTypesSchema( $type );
            if ( empty( $schema ) ) {
                return new WP_Error('unsupported_type', __( 'Unsupported shortcode type for schema validation.', 'integration-google-drive' ), [
                    'status' => 400,
                ]);
            }
            $processedData = $this->validateAndSanitize( $processedData, $schema );
        }
        return $processedData;
    }

    private function validateAndSanitize( array $data, array $schema ) {
        $result = [];
        $schema['data']['source']['selectedFiles[]'] = $schema['data']['source']['files[]'] ?? 'null';
        foreach ( $schema as $key => $expectedType ) {
            $filteredKey = str_replace( '[]', '', $key );
            if ( !isset( $data[$filteredKey] ) ) {
                continue;
            }
            $value = $data[$filteredKey];
            if ( is_array( $expectedType ) ) {
                if ( is_array( $value ) ) {
                    if ( empty( $value ) ) {
                        $result[$filteredKey] = [];
                        continue;
                    }
                    $isNestedArray = strpos( $key, '[]' ) !== false;
                    if ( $isNestedArray ) {
                        foreach ( $value as $index => $item ) {
                            $nested = $this->validateAndSanitize( $item, $expectedType );
                            if ( !empty( $nested ) ) {
                                $result[$filteredKey][$index] = $nested;
                            }
                        }
                    } else {
                        $nested = $this->validateAndSanitize( $value, $expectedType );
                        if ( !empty( $nested ) ) {
                            $result[$filteredKey] = $nested;
                        }
                    }
                }
            } else {
                if ( $this->isTypeMatch( $value, $expectedType ) ) {
                    $result[$filteredKey] = $value;
                }
            }
        }
        return $result;
    }

    private function isTypeMatch( $value, $type ) {
        $types = explode( '|', $type );
        foreach ( $types as $t ) {
            switch ( $t ) {
                case 'integer':
                    if ( is_int( $value ) || is_numeric( $value ) ) {
                        return true;
                    }
                    break;
                case 'string':
                    if ( is_string( $value ) ) {
                        return true;
                    }
                    break;
                case 'boolean':
                    if ( is_bool( $value ) ) {
                        return true;
                    }
                    break;
                case 'array':
                    if ( is_array( $value ) ) {
                        return true;
                    }
                    break;
                case 'object':
                    if ( is_object( $value ) ) {
                        return true;
                    }
                    break;
                case 'NULL':
                    if ( $value === null ) {
                        return true;
                    }
                    break;
                case 'any':
                    return true;
                default:
                    if ( gettype( $value ) === $t ) {
                        return true;
                    }
            }
        }
        return false;
    }

    private function fetchShortcode( $id ) {
        if ( empty( $id ) ) {
            return new WP_Error(404, __( 'Shortcode ID is required.', 'integration-google-drive' ));
        }
        $result = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $this->tableName, $id ), ARRAY_A );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( empty( $result ) ) {
            return new WP_Error(404, __( 'Shortcode not found.', 'integration-google-drive' ));
        }
        return $result;
    }

    private function isModuleAutoFetch( $id, $moduleConfig ) {
        if ( empty( $moduleConfig ) ) {
            return false;
        }
        if ( empty( $moduleConfig['autoFetch'] ) ) {
            return false;
        }
        $transientKey = "ccpigd_module_auto_fetch_{$id}";
        $autoFetch = get_transient( $transientKey );
        if ( empty( $autoFetch ) ) {
            $autoFetchInterval = $moduleConfig['autoFetchInterval'] ?? 60;
            set_transient( $transientKey, true, $autoFetchInterval );
            return true;
        }
        return false;
    }

    private function getSelectedFiles( $fileKeys, $args ) {
        $config = [
            'recursive'  => false,
            'returnType' => 'array',
            'page'       => 1,
            'perPage'    => 1000,
            'from'       => 'cache',
        ];
        $config = wp_parse_args( $config, $args );
        $app = App::getInstance();
        $recursiveFiles = $app->getFilesByKeys( $fileKeys, $config );
        if ( is_wp_error( $recursiveFiles ) ) {
            return $recursiveFiles;
        }
        $selectedFiles = $recursiveFiles['files'] ?? [];
        return $selectedFiles;
    }

    /**
     * Attach thumbnail data to files using thumbnail keys.
     *
     * @param array $files Files list (each item must contain fileKey)
     * @param array $fileKeys Source file keys with optional thumbnailKey
     *
     * @return array
     */
    private function attachThumbnailsToFiles( array $files, array $fileKeys ) : array {
        $availableThumbnail = array_filter( $fileKeys, static fn( $item ) => !empty( $item['thumbnailKey'] ) );
        if ( !$availableThumbnail ) {
            return $files;
        }
        /**
         * Map: thumbnailKey => originalFileKey
         */
        $thumbnailToOriginal = [];
        $thumbnailKeys = [];
        foreach ( $availableThumbnail as $item ) {
            $thumbnailKeys[] = $item['thumbnailKey'];
            $thumbnailToOriginal[$item['thumbnailKey']] = $item['fileKey'];
        }
        $thumbnails = Files::getInstance()->getFileAttributesByKeys( $thumbnailKeys, [
            'fileKey',
            'name',
            'additionalData',
            'extension'
        ] );
        if ( is_wp_error( $thumbnails ) || !$thumbnails ) {
            return $files;
        }
        /**
         * Map: originalFileKey => thumbnail data
         */
        $thumbnailMap = [];
        foreach ( $thumbnails as $thumbnail ) {
            $originalFileKey = $thumbnailToOriginal[$thumbnail['fileKey']] ?? null;
            if ( $originalFileKey ) {
                $thumbnail['basename'] = $thumbnail['additionalData']['baseName'] ?? '';
                $thumbnail['thumbnail'] = ccpigdGetUrl(
                    'thumbnail',
                    $thumbnail['fileKey'],
                    $thumbnail['basename'],
                    'lg',
                    $thumbnail['extension']
                );
                unset($thumbnail['additionalData']);
                $thumbnailMap[$originalFileKey] = $thumbnail;
            }
        }
        /**
         * Attach thumbnails in a single pass
         */
        foreach ( $files as &$file ) {
            if ( !empty( $thumbnailMap[$file['fileKey']] ) ) {
                $file['thumbnailData'] = $thumbnailMap[$file['fileKey']];
            }
        }
        unset($file);
        // prevent reference leak
        return $files;
    }

}
