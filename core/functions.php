<?php

use CodeConfig\IGD\Models\UserAccess;
use CodeConfig\IGD\Shortcode;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Utils\MimeTypeManager;
defined( "ABSPATH" ) || exit( "No direct script access allowed" );
function ccpigd_fs() {
    return CodeConfig\ccpigd_fs();
}

if ( !function_exists( "ccpigdGetAccountByKey" ) ) {
    /**
     * Retrieve an account by its key.
     *
     * @param string $key The key of the account to retrieve.
     * @return \CodeConfig\IGD\App\Account|WP_Error
     */
    function ccpigdGetAccountByKey(  $key  ) {
        $account = \CodeConfig\IGD\App\Accounts::getInstance()->getAccountByKey( $key );
        return $account;
    }

}
if ( !function_exists( "ccpigdGetFileByKey" ) ) {
    /**
     * Retrieve a file by its key.
     *
     * @param string $key The key of the file to retrieve.
     * @return array|WP_Error The file data if found, or null if not found.
     */
    function ccpigdGetFileByKey(  $key  ) {
        $file = \CodeConfig\IGD\Models\Files::getInstance()->getFileByKey( $key, 'array' );
        return $file;
    }

}
if ( !function_exists( "ccpigdGetFileIdsByKeys" ) ) {
    /**
     * Retrieve file IDs from an array of file keys.
     *
     * @param array $keys An array of file keys to search for.
     *
     * @return array|WP_Error An array of file IDs if found, or null if not found.
     */
    function ccpigdGetFileIdsByKeys(  array $keys  ) {
        return \CodeConfig\IGD\Models\Files::getInstance()->getFileAttributesByKeys( $keys );
    }

}
if ( !function_exists( "ccpigdGetFileAttributesByKeys" ) ) {
    /**
     * Retrieve selected attributes from files by their keys.
     *
     * @param array $keys An array of file keys to search for.
     * @param array $attributes An array of attributes to return for each file.
     *                          Defaults to ['id'].
     *                          Example: ['id', 'name'].
     *
     * @return WP_Error|array Returns:
     *                        - A flat array if one attribute is requested (e.g., ['id1', 'id2']).
     *                        - An array of associative arrays if multiple attributes are requested.
     *                        Example:
     *                        [
     *                        ['id' => 'abc123', 'name' => 'File A'],
     *                        ['id' => 'def456', 'name' => 'File B']
     *                        ]
     */
    function ccpigdGetFileAttributesByKeys(  array $keys, array $attributes  ) {
        return \CodeConfig\IGD\Models\Files::getInstance()->getFileAttributesByKeys( $keys, $attributes );
    }

}
if ( !function_exists( "ccpigdGetExtensionGroups" ) ) {
    /**
     * Retrieves an array of file extensions by the given types.
     *
     * @param array|string|null $keys An array, string, or null if single type of types to filter by. Valid types are:
     *                                - 'folder'
     *                                - 'document'
     *                                - 'code'
     *                                - 'image'
     *                                - 'audio'
     *                                - 'video'
     *                                - 'archive'
     *                                - 'binary_executable'
     *                                - 'all'
     *
     * @return array An array of file extensions if found, or an empty array if not found.
     */
    function ccpigdGetExtensionGroups(  $keys = null  ) : array {
        if ( is_string( $keys ) ) {
            $keys = [$keys];
        }
        $groups = [
            'folder'            => ['folder'],
            'document'          => [
                'spreadsheet',
                'document',
                'presentation',
                'script',
                'form',
                'drawing',
                'xls',
                'xlsx',
                'doc',
                'docx',
                'ppt',
                'pptx',
                'pdf',
                'txt',
                'csv',
                'rtf',
                'odt',
                'ods',
                'odp',
                'epub',
                'md'
            ],
            'code'              => [
                'js',
                'php',
                'py',
                'java',
                'cs',
                'cpp',
                'c',
                'rb',
                'go',
                'ts',
                'xml',
                'json',
                'yaml',
                'sh'
            ],
            'image'             => [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'webp',
                'svg',
                'bmp',
                'tiff',
                'ico'
            ],
            'audio'             => [
                'mp3',
                'wav',
                'ogg',
                'flac',
                'aac',
                'm4a'
            ],
            'video'             => [
                'mp4',
                'avi',
                'mov',
                'wmv',
                'flv',
                'mkv',
                'webm'
            ],
            'archive'           => [
                'zip',
                'rar',
                'tar',
                'gz',
                '7z',
                'bz2',
                'xz'
            ],
            'binary_executable' => [
                'exe',
                'dll',
                'iso',
                'bin',
                'apk',
                'msi'
            ],
        ];
        $downloadable = array_filter( array_merge( ...array_values( $groups ) ), fn( $ext ) => !in_array( $ext, MimeTypeManager::NON_DOWNLOADABLE_TYPES, true ) );
        $groups['downloadable'] = array_values( $downloadable );
        if ( null === $keys ) {
            return $groups;
        }
        if ( in_array( 'all', $keys, true ) || empty( $keys ) ) {
            return array_merge( ...array_values( $groups ) );
        }
        // Keep only requested keys
        $filtered = array_intersect_key( $groups, array_flip( $keys ) );
        // Flatten into a single array
        return ( $filtered ? array_merge( ...array_values( $filtered ) ) : [] );
    }

}
if ( !function_exists( "ccpigdGetExtensionByMimeType" ) ) {
    /**
     * Retrieves the file extension associated with a given MIME type.
     *
     * @param string $mimeType The MIME type to retrieve the extension for.
     *
     * @return string The file extension associated with the given MIME type,
     *                or 'unknown' if no extension can be determined.
     */
    function ccpigdGetExtensionByMimeType(  string $mimeType  ) {
        $map = ccpigdGetMimeTypeMap( 'mime2ext' );
        return $map[$mimeType] ?? 'unknown';
    }

}
if ( !function_exists( "ccpigdGetMimeTypeByExtension" ) ) {
    /**
     * Retrieves the MIME type associated with a given file extension.
     *
     * @param string $extension The file extension to retrieve the MIME type for.
     *
     * @return string The MIME type associated with the given extension,
     *                or 'application/octet-stream' if no association can be determined.
     */
    function ccpigdGetMimeTypeByExtension(  string $extension  ) {
        $map = ccpigdGetMimeTypeMap( 'ext2mime' );
        return $map[$extension] ?? 'application/octet-stream';
    }

}
if ( !function_exists( "ccpigdGetMimeTypesByGroup" ) ) {
    /**
     * Retrieves the MIME types associated with a given set of file types.
     *
     * @param array $types The set of file types to retrieve the MIME types for.
     *
     * @return array The array of MIME types associated with the given set of file types.
     */
    function ccpigdGetMimeTypesByGroup(  array $types  ) {
        $extensions = ccpigdGetExtensionGroups( $types );
        $map = ccpigdGetMimeTypeMap( 'ext2mime' );
        $mimeTypes = array_filter( array_map( fn( $ext ) => $map[$ext] ?? null, $extensions ) );
        return array_values( array_unique( $mimeTypes ) );
    }

}
if ( !function_exists( "ccpigdGetMimeTypeMap" ) ) {
    /**
     * Retrieves the MIME type mapping array.
     *
     * The returned array has either MIME types as keys and their associated
     * file extensions as values, or the reverse depending on the value of
     * the $type parameter. If $type is 'mime2ext', the array is flipped
     * so that file extensions are keys and MIME types are values.
     *
     * @param string $type The type of mapping to retrieve. Either 'mime2ext'
     *                     or 'ext2mime'.
     *
     * @return array The MIME type mapping array.
     */
    function ccpigdGetMimeTypeMap(  string $type = 'mime2ext'  ) {
        static $mimeMap = [
            'application/vnd.google-apps.folder'                                        => 'folder',
            'application/vnd.google-apps.spreadsheet'                                   => 'spreadsheet',
            'application/vnd.google-apps.document'                                      => 'document',
            'application/vnd.google-apps.presentation'                                  => 'presentation',
            'application/vnd.google-apps.form'                                          => 'form',
            'application/vnd.google-apps.drawing'                                       => 'drawing',
            'application/vnd.google-apps.vid'                                           => 'vid',
            'application/vnd.google-apps.site'                                          => 'site',
            'application/vnd.google-apps.map'                                           => 'map',
            'application/vnd.google-apps.jam'                                           => 'jam',
            'application/vnd.google-apps.script+json'                                   => 'script',
            'application/vnd.google-apps.script+webapp'                                 => 'script',
            'application/vnd.google-apps.script'                                        => 'script',
            'application/vnd.google-apps.addon'                                         => 'addon',
            'application/vnd.google-apps.shortcut'                                      => 'shortcut',
            'application/vnd.ms-excel'                                                  => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/pdf'                                                           => 'pdf',
            'text/plain'                                                                => 'txt',
            'text/csv'                                                                  => 'csv',
            'image/jpeg'                                                                => 'jpg',
            'image/png'                                                                 => 'png',
            'image/gif'                                                                 => 'gif',
            'image/webp'                                                                => 'webp',
            'image/svg+xml'                                                             => 'svg',
            'application/zip'                                                           => 'zip',
            'application/x-rar-compressed'                                              => 'rar',
            'application/x-tar'                                                         => 'tar',
            'application/gzip'                                                          => 'gz',
            'audio/mpeg'                                                                => 'mp3',
            'audio/wav'                                                                 => 'wav',
            'video/mp4'                                                                 => 'mp4',
            'video/x-msvideo'                                                           => 'avi',
        ];
        return ( $type === 'ext2mime' ? array_flip( $mimeMap ) : $mimeMap );
    }

}
if ( !function_exists( "ccpigdGetDefaultSettings" ) ) {
    function ccpigdGetDefaultSettings() : array {
        $settings = [
            "accounts"                      => [
                "connectionType"  => "manual",
                "appClientId"     => "",
                "appClientSecret" => "",
                "redirectUri"     => CCPIGD_MANUAL_REDIRECT_URI,
            ],
            'advanced'                      => [
                "googleWorkspaceDomain" => "",
                "sharingPermission"     => true,
                'allowDotExtension'     => true,
                'secureVideoPlayback'   => false,
                "deleteDataOnUninstall" => false,
            ],
            'appearance'                    => [
                "preloader"    => 1,
                "primaryColor" => "#00ac47",
                "customCSS"    => "",
            ],
            'integrations'                  => [
                "activeIntegrations" => [
                    'classicEditor',
                    'gutenberg',
                    // gutenbergModules
                    'elementor',
                ],
                'mediaLibrary'       => [
                    'folders'         => [],
                    "redirection"     => true,
                    'deleteCloudFile' => false,
                    "mlHoverPreview"  => false,
                ],
            ],
            'userAccess'                    => [[
                'id'      => '0',
                'type'    => "role",
                'value'   => "",
                'folders' => [],
                'pages'   => [],
            ]],
            'synchronization'               => [
                'enableSync'  => false,
                'folders'     => [],
                'timer'       => "custom",
                'customTimer' => 120,
            ],
            'tools'                         => [
                "autoSave" => false,
            ],
            "createFolderOnRegistration"    => false,
            "privateFolderInAdminDashboard" => false,
            "excludeIncludeFolder"          => false,
            "isEditing"                     => false,
            "draft"                         => null,
            "menu"                          => "Accounts",
        ];
        return $settings;
    }

}
if ( !function_exists( "ccpigdGetModuleDefaultData" ) ) {
    function ccpigdGetModuleDefaultData(  string $type  ) : array {
        if ( !in_array( $type, Shortcode::getModulesList(), true ) ) {
            return [];
        }
        $data = [
            'id'          => 'new',
            'status'      => 'active',
            'integration' => null,
            'data'        => [
                'source'      => [
                    'fileKeys'      => [],
                    'selectedFiles' => [],
                ],
                'filter'      => [
                    'extension' => [
                        'include' => [],
                        'exclude' => [],
                        'all'     => false,
                    ],
                    'name'      => [
                        'include' => '',
                        'exclude' => '',
                        'all'     => false,
                        'applyTo' => [
                            'files'   => true,
                            'folders' => true,
                        ],
                    ],
                ],
                'advanced'    => [],
                'permissions' => [
                    'passwordProtect' => [
                        'enable'   => false,
                        'password' => '',
                    ],
                    'displayFor'      => [
                        'whoCanViewModule'        => 'everyone',
                        'loggedInUserType'        => 'users',
                        'displayFor'              => [],
                        'showAccessDeniedMessage' => true,
                        'accessDeniedMessage'     => 'You do not have access to this module.',
                    ],
                ],
            ],
        ];
        $advancedDefaults = [
            'width'               => [
                'value' => 100,
                'unit'  => '%',
            ],
            'height'              => [
                'value' => 100,
                'unit'  => 'auto',
            ],
            'theme'               => 'light',
            'borderBoxVisibility' => false,
            'files'               => [
                'loadingType' => 'load_more',
                'perPage'     => 20,
            ],
            'autoFetch'           => [
                'status'   => false,
                'interval' => 60,
            ],
            'sort'                => [
                'orderBy' => 'name',
                'order'   => 'ASC',
            ],
        ];
        $permissionBase = [
            'userAccess'       => 'everyone',
            'loggedInUserType' => 'users',
            'displayFor'       => [],
        ];
        $permissions = [
            'newFolder' => $permissionBase + [
                'enable' => false,
            ],
            'upload'    => $permissionBase + [
                'enable'       => false,
                'folderUpload' => false,
            ],
            'preview'   => $permissionBase + [
                'enable'           => false,
                'inline'           => true,
                'popOut'           => false,
                'previewThumbnail' => true,
            ],
            'rename'    => $permissionBase + [
                'enable' => false,
            ],
            'download'  => $permissionBase + [
                'enable'           => false,
                'folderDownload'   => false,
                'multipleDownload' => false,
            ],
            'copy'      => $permissionBase + [
                'enable' => false,
            ],
            'move'      => $permissionBase + [
                'enable' => false,
            ],
            'share'     => $permissionBase + [
                'enable' => false,
            ],
            'search'    => $permissionBase + [
                'enable'         => false,
                'searchLocation' => [
                    'cache'  => true,
                    'server' => true,
                ],
                'searchScope'    => [
                    'current' => true,
                    'global'  => true,
                ],
            ],
            'delete'    => $permissionBase + [
                'enable' => false,
            ],
        ];
        $permissionList = [
            'newFolder',
            'upload',
            'preview',
            'rename',
            'download',
            'copy',
            'move',
            'share',
            'viewShareLink',
            'delete'
        ];
        $notifications = [
            'enable'          => [],
            'emailRecipients' => '',
            'skipCurrentUser' => false,
        ];
        foreach ( $permissionList as $action ) {
            $notifications[$action] = false;
        }
        $uploadFilter = [
            'maxSize'  => 0,
            'minSize'  => 0,
            'maxFiles' => 0,
        ];
        $modules = [
            'file-browser' => [
                'title'             => 'File Browser',
                'advancedKey'       => 'fileBrowser',
                'fileBrowser'       => [
                    'folderView'          => 'grid',
                    'headerOptions'       => [
                        'breadcrumb' => false,
                        'refresh'    => false,
                        'sorting'    => false,
                        'rootUpload' => false,
                    ],
                    'listViewTableHead'   => [
                        'enable'  => false,
                        'name'    => 'Name',
                        'type'    => 'Type',
                        'size'    => 'Size',
                        'updated' => 'Updated',
                        'action'  => 'Action',
                    ],
                    'secureVideoPlayback' => false,
                ],
                'filters'           => ['upload'],
                'permissions'       => [
                    'newFolder',
                    'upload',
                    'preview',
                    'rename',
                    'download',
                    'copy',
                    'move',
                    'delete',
                    'search',
                    'share'
                ],
                'notifications'     => array_keys( $notifications ),
                'advancedOverrides' => [
                    'borderBoxVisibility' => false,
                ],
            ],
            'gallery'      => [
                'title'         => 'Gallery',
                'advancedKey'   => 'gallery',
                'gallery'       => [
                    'layout'                    => "grid",
                    "columnsDevice"             => "desktop",
                    'columns'                   => [
                        'desktop' => 4,
                        'laptop'  => 3,
                        'tablet'  => 2,
                        'mobile'  => 1,
                    ],
                    'thumbnailSpacing'          => [
                        'value' => 1,
                        'unit'  => 'rem',
                    ],
                    'thumbnailRadius'           => [
                        'value' => 1,
                        'unit'  => 'rem',
                    ],
                    'thumbnailQuality'          => "thumbnail",
                    'showOverlay'               => false,
                    'overlayDisplayNumber'      => true,
                    'overlayDisplayTitle'       => true,
                    'overlayDisplayDescription' => true,
                ],
                'permissions'   => ['download', 'preview'],
                'notifications' => ['download', 'preview'],
            ],
        ];
        $module_list = ['file-browser', 'gallery'];
        if ( !in_array( $type, $module_list, true ) ) {
            return $data;
        }
        if ( !isset( $modules[$type] ) ) {
            return $data;
        }
        $module = $modules[$type];
        $data['type'] = $type;
        $data['title'] = $module['title'];
        if ( !empty( $module['filters'] ) ) {
            foreach ( $module['filters'] as $filter ) {
                $data['data']['filter'][$filter] = $uploadFilter;
            }
        }
        if ( !empty( $module['overridePermissions'] ) ) {
            foreach ( $module['overridePermissions'] as $permKey => $permValues ) {
                if ( isset( $permissions[$permKey] ) ) {
                    $permissions[$permKey] = $permValues;
                }
            }
        }
        if ( !empty( $module['permissions'] ) ) {
            foreach ( $module['permissions'] as $perm ) {
                $data['data']['permissions'][$perm] = $permissions[$perm];
            }
        }
        if ( !empty( $module['notifications'] ) ) {
            foreach ( $module['notifications'] as $notify ) {
                $data['data']['notifications'][$notify] = $notifications[$notify];
            }
        }
        $advanced = $advancedDefaults;
        if ( !empty( $module['excludeAdvanced'] ) ) {
            foreach ( $module['excludeAdvanced'] as $advKey ) {
                unset($advanced[$advKey]);
            }
        }
        if ( !empty( $module['advancedOverrides'] ) ) {
            $advanced = ccpigdDeepMergeWithUnset( $advanced, $module['advancedOverrides'] );
        }
        $data['data']['advanced'] = array_merge( $advanced, ( $module['advancedKey'] && isset( $module[$module['advancedKey']] ) ? [
            $module['advancedKey'] => $module[$module['advancedKey']],
        ] : [] ) );
        return $data;
    }

}
if ( !function_exists( "ccpigdDeepMergeWithUnset" ) ) {
    /**
     * Recursively merges two arrays.
     *
     * @param array $base The base array.
     * @param array $override The array with overriding values.
     *
     * @return array The merged array.
     */
    function ccpigdDeepMergeWithUnset(  array $base, array $override  ) : array {
        foreach ( $override as $key => $value ) {
            if ( $value === CCPIGD_UNSET ) {
                unset($base[$key]);
                continue;
            }
            if ( is_array( $value ) && isset( $base[$key] ) && is_array( $base[$key] ) ) {
                $base[$key] = ccpigdDeepMergeWithUnset( $base[$key], $value );
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

}
if ( !function_exists( "ccpigdGetShortcodeTypesSchema" ) ) {
    /**
     * Retrieve the schema for the shortcode types.
     *
     * @param string|null $key The key of the shortcode type to retrieve.
     *
     * @return array The schema for the shortcode types.
     */
    function ccpigdGetShortcodeTypesSchema(  $key = null  ) {
        $defaultSchema = [
            'id'          => 'integer',
            'title'       => 'string',
            'status'      => 'string',
            'type'        => 'string',
            'integration' => 'string|null',
            'createdAt'   => 'string',
            'data'        => [
                'source'        => [
                    'fileKeys'      => 'array',
                    'hasMore'       => 'boolean',
                    'totalCount'    => 'integer',
                    'currentPage'   => 'integer',
                    'perPage'       => 'integer',
                    'nextPage'      => 'integer|null',
                    'totalPages'    => 'integer',
                    'privateFolder' => 'boolean',
                ],
                'filter'        => 'array',
                'notifications' => 'array',
                'permissions'   => 'array',
            ],
        ];
        if ( current_user_can( 'manage_options' ) ) {
            $defaultSchema['locations'] = 'array';
        }
        $defaultAdvanced = [
            'width'               => "array",
            'height'              => 'array',
            'theme'               => 'string',
            'files'               => 'array',
            'borderBoxVisibility' => 'boolean',
            'autoFetch'           => 'array',
            'sort'                => 'array',
        ];
        $gallery = $defaultSchema;
        $gallery['data']['source']['files[]'] = [
            'fileKey'     => 'string',
            'name'        => 'string',
            'description' => 'string',
            'baseName'    => 'string',
            'extension'   => 'string',
            'mimeType'    => 'string',
            'size'        => 'integer',
            'updatedAt'   => 'string',
        ];
        $gallery['data']['advanced'] = $defaultAdvanced;
        $gallery['data']['advanced']['gallery'] = 'array';
        $fileBrowser = $defaultSchema;
        $fileBrowser['data']['source']['breadcrumbs[]'] = [
            'fileKey' => 'string',
            'name'    => 'string',
        ];
        $fileBrowser['data']['source']['files[]'] = [
            'fileKey'        => 'string',
            'name'           => 'string',
            'icon'           => 'string',
            'extension'      => 'string',
            'mimeType'       => 'string',
            'count'          => 'string',
            'size'           => 'integer',
            'updatedAt'      => 'string',
            'additionalData' => [
                'baseName'   => 'string',
                'lastEdited' => 'string',
            ],
            'saveAs'         => 'array',
            'exportLinks'    => 'array',
        ];
        $fileBrowser['data']['advanced'] = $defaultAdvanced;
        $fileBrowser['data']['advanced']['fileBrowser'] = 'array';
        $schema = [
            'gallery'      => $gallery,
            'file-browser' => $fileBrowser,
        ];
        if ( !empty( $key ) ) {
            if ( !isset( $schema[$key] ) ) {
                return $gallery;
            }
            return $schema[$key];
        }
        return $schema;
    }

}
if ( !function_exists( "ccpigdGetTablesDefinitions" ) ) {
    function ccpigdGetTablesDefinitions(  $key = null  ) {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        $tables = [
            'shortcodes'  => "CREATE TABLE IF NOT EXISTS `{$prefix}integration_google_drive_shortcodes` (\n                `id` INT AUTO_INCREMENT,\n                `title` VARCHAR(120) DEFAULT NULL,\n                `type` VARCHAR(20) NOT NULL,\n                `integration` VARCHAR(60) DEFAULT NULL,\n                `status` VARCHAR(10) DEFAULT 'active',\n                `data` LONGTEXT DEFAULT NULL,\n                `locations` LONGTEXT DEFAULT NULL,\n                `createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                `updatedAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                PRIMARY KEY (`id`)\n            ) {$charsetCollate};",
            'user_access' => "CREATE TABLE IF NOT EXISTS `{$prefix}integration_google_drive_user_access` (\n                `id` INT AUTO_INCREMENT,\n                `type` TEXT NOT NULL,\n                `value` TEXT NOT NULL,\n                `folders` LONGTEXT DEFAULT NULL,\n                `pages` LONGTEXT DEFAULT NULL,\n                `createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                `updatedAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                PRIMARY KEY (`id`)\n            ) {$charsetCollate};",
            'files'       => "CREATE TABLE IF NOT EXISTS `{$prefix}integration_google_drive_files` (\n                `id` VARCHAR(120) NOT NULL,\n                `fileKey` VARCHAR(120) NOT NULL,\n                `name` TEXT DEFAULT NULL,\n                `description` LONGTEXT DEFAULT NULL,\n                `parentId` VARCHAR(120) DEFAULT NULL,\n                `accountId` VARCHAR(120) NOT NULL,\n                `size` BIGINT UNSIGNED DEFAULT NULL,\n                `mimeType` VARCHAR(255) NOT NULL,\n                `extension` VARCHAR(60) DEFAULT NULL,\n                `icon` VARCHAR(255) DEFAULT NULL,\n                `thumbnail` VARCHAR(255) DEFAULT NULL,\n                `additionalData` LONGTEXT DEFAULT NULL,\n                `metaData` LONGTEXT DEFAULT NULL,\n                `isDir` TINYINT(1) DEFAULT 0,\n                `isStarred` TINYINT(1) DEFAULT 0,\n                `isShared` TINYINT(1) DEFAULT 0,\n                `media` LONGTEXT DEFAULT NULL,\n                `permissions` LONGTEXT DEFAULT NULL,\n                `createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                `updatedAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                PRIMARY KEY (`fileKey`)\n            ) {$charsetCollate};",
            'accounts'    => "CREATE TABLE IF NOT EXISTS `{$prefix}integration_google_drive_accounts` (\n                `id` VARCHAR(120) NOT NULL,\n                `accountKey` TEXT NOT NULL,\n                `name` TEXT NOT NULL,\n                `email` TEXT NOT NULL,\n                `photo` TEXT NOT NULL,\n                `storage` TEXT NOT NULL,\n                `lost` TINYINT(1) DEFAULT 1,\n                `rootId` TEXT NOT NULL,\n                `userId` INT NOT NULL,\n                `active` TINYINT(1) DEFAULT 0,\n                `tokens` LONGTEXT NOT NULL,\n                `createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                `updatedAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                PRIMARY KEY (`id`),\n                UNIQUE KEY `unique_key` (`accountKey`(191))\n            ) {$charsetCollate};",
            'logs'        => "CREATE TABLE IF NOT EXISTS `{$prefix}integration_google_drive_logs` (\n                `id` INT AUTO_INCREMENT,\n                `moduleId` INT DEFAULT NULL,\n                `userId` INT DEFAULT NULL,\n                `fileKey` TEXT DEFAULT NULL,\n                `fileName` TEXT DEFAULT NULL,\n                `page` TEXT DEFAULT NULL,\n                `data` LONGTEXT DEFAULT NULL,\n                `type` TEXT NOT NULL,\n                `title` TEXT NOT NULL,\n                `status` TEXT NOT NULL,\n                `description` TEXT DEFAULT NULL,\n                `createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                `updatedAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',\n                PRIMARY KEY (`id`) \n            ) {$charsetCollate};",
        ];
        if ( $key !== null && isset( $tables[$key] ) ) {
            return $tables[$key];
        }
        return array_values( $tables );
    }

}
if ( !function_exists( "ccpigdGetAllowedModuleExtensions" ) ) {
    function ccpigdGetAllowedModuleExtensions(  string $type  ) {
        $gallery = ['image'];
        $mediaPlayer = ['audio', 'video'];
        $typeGroups = [
            'gallery'         => ccpigdGetExtensionGroups( $gallery ),
            'media-player'    => ccpigdGetExtensionGroups( $mediaPlayer ),
            'all'             => ccpigdGetExtensionGroups( 'all' ),
            'embed-documents' => ccpigdGetExtensionGroups( 'document' ),
        ];
        return $typeGroups[$type] ?? $typeGroups['all'];
    }

}
if ( !function_exists( "ccpigdDeleteAllAttachments" ) ) {
    function ccpigdDeleteAllAttachments() {
        $page = 1;
        do {
            $attachments = get_posts( [
                'post_type'      => 'attachment',
                'posts_per_page' => 100,
                'paged'          => $page,
                'meta_query'     => [[
                    'key'     => '_ccpigd_media_folder_key',
                    'compare' => 'EXISTS',
                ]],
            ] );
            foreach ( $attachments as $attachment ) {
                wp_delete_attachment( $attachment->ID, true );
            }
            $page++;
        } while ( count( $attachments ) > 0 );
        return true;
    }

}
if ( !function_exists( "ccpigdGetTemplate" ) ) {
    function ccpigdGetTemplate(  $slug, $args = [], $name = null  ) {
        $template = locate_template( "{$slug}-{$name}.php" );
        if ( !$template ) {
            $template = CCPIGD_PATH . "templates/{$slug}.php";
            if ( $name ) {
                $template = CCPIGD_PATH . "templates/{$slug}-{$name}.php";
            }
        }
        if ( file_exists( $template ) ) {
            if ( !empty( $args ) && is_array( $args ) ) {
                extract( $args );
            }
            include $template;
        }
    }

}
if ( !function_exists( "ccpigdFormatBytes" ) ) {
    function ccpigdFormatBytes(  int $bytes, int $decimals = 2  ) : string {
        if ( $bytes < 0 ) {
            return "0 B";
        }
        $units = [
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
            'PB'
        ];
        $factor = floor( (strlen( (string) $bytes ) - 1) / 3 );
        return sprintf( "%.{$decimals}f %s", $bytes / pow( 1024, $factor ), $units[$factor] );
    }

}
if ( !function_exists( "ccpigdParseSizeToBytes" ) ) {
    function ccpigdParseSizeToBytes(  string $size  ) : int {
        $units = [
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
            'PB'
        ];
        // Trim and normalize input
        $size = trim( $size );
        $pattern = '/^([\\d.]+)\\s*([KMGTPE]?B)$/i';
        if ( !preg_match( $pattern, $size, $matches ) ) {
            return 0;
            // invalid format
        }
        $value = (float) $matches[1];
        $unit = strtoupper( $matches[2] );
        $factor = array_search( $unit, $units, true );
        return (int) round( $value * pow( 1024, $factor ) );
    }

}
if ( !function_exists( "ccpigdGenerateKey" ) ) {
    function ccpigdGenerateKey(  $fileId, $accountId  ) {
        return md5( "{$fileId}-{$accountId}" );
    }

}
if ( !function_exists( "ccpigdSizeToString" ) ) {
    /**
     * Convert a size identifier to a Google Drive thumbnail size string.
     *
     * @param string $size The size identifier. Valid values are:
     *                     - 'full': Original size (no resizing).
     *                     - 'thumbnail': 150x150 pixels.
     *                     - 'medium': 300x300 pixels.
     *                     - 'large': 1024x1024 pixels.
     *                     - Custom size in the format 'WIDTHxHEIGHT' (e.g., '400x300').
     *
     * @return string The corresponding Google Drive thumbnail size string.
     *                Returns an empty string for 'full' or invalid inputs.
     */
    function ccpigdSizeToString(  $size  ) {
        $map = [
            'xs'  => 'w32-h32-c-nu',
            'sm'  => 'w64-h64-c-nu',
            'md'  => 'w128-h128-c-nu',
            'lg'  => 'w150-h150-c-nu',
            'xl'  => 'w300-h300-c-nu',
            '2xl' => 'w960-h640-c-nu',
            '3xl' => 'w1024-h1024-c-nu',
            '4xl' => 'w1280-h960-c-nu',
            '5xl' => '',
        ];
        if ( isset( $map[$size] ) ) {
            return $map[$size];
        }
        if ( preg_match( '/^(\\d+)x(\\d+)$/', $size, $m ) ) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            return "w{$w}-h{$h}-c-nu";
        }
        return '';
    }

}
if ( !function_exists( "getDuplicateItems" ) ) {
    /**
     * Get duplicate items from an array.
     *
     * This function takes an array as input and returns an array of items
     * that appear more than once in the input array.
     *
     * @param array $array The input array to check for duplicate items.
     *
     * @return array An array of duplicate items found in the input array.
     */
    function ccpigdGetDuplicateItems(  array $array  ) : array {
        return array_keys( array_filter( array_count_values( $array ), fn( $count ) => $count > 1 ) );
    }

}
if ( !function_exists( "ccpigdTitleToUrlSlug" ) ) {
    function ccpigdTitleToUrlSlug(  $filename  ) {
        if ( $filename === '' ) {
            return 'unknown-file';
        }
        if ( class_exists( 'Normalizer' ) ) {
            $filename = Normalizer::normalize( $filename, Normalizer::FORM_C );
        }
        $filename = preg_replace( '/[\\/\\\\\\?\\<\\>\\:\\*\\|"\\`~!@#$%^&()+={}\\[\\];\',]+/u', '', $filename );
        $filename = preg_replace( '/\\s+/u', '-', $filename );
        $filename = preg_replace( '/-+/u', '-', $filename );
        $filename = preg_replace( '/\\.{2,}/u', '.', $filename );
        $filename = trim( $filename, ".-_" );
        $filename = mb_strtolower( $filename, 'UTF-8' );
        return $filename;
    }

}
/**
 * Generate a secure and optimized attachment URL for CCPIGD.
 *
 * @param string $key Unique file key.
 * @param string $name File name (without extension).
 * @param string $size Image size (default: full).
 * @param string $extension File extension (default: jpg).
 *
 * @return string Sanitized attachment URL.
 */
function ccpigdGetUrl(
    $action,
    $key,
    $name = 'unknown',
    $size = 'lg',
    $ext = 'webp',
    $referer = null
) {
    if ( empty( $key ) ) {
        return '';
    }
    $ext = ( empty( $ext ) ? 'webp' : strtolower( sanitize_text_field( $ext ) ) );
    $name = str_replace( ".{$ext}", '', $name );
    $allowed_actions = [
        'attachment',
        'thumbnail',
        'stream',
        'preview',
        'download',
        'share'
    ];
    if ( !in_array( $action, $allowed_actions, true ) ) {
        return '';
    }
    $action = sanitize_key( $action );
    $referer = ( $referer !== null ? $referer : null );
    $key = sanitize_key( $key );
    $name = ccpigdTitleToUrlSlug( $name );
    $size = strtolower( sanitize_text_field( $size ?? '' ) );
    $allowSizes = array_keys( ccpigdGetAvailableThumbnailSizes() );
    $allowed_sizes = apply_filters( 'ccpigd_allowed_sizes', $allowSizes );
    if ( !in_array( $size, $allowed_sizes, true ) ) {
        $size = null;
    }
    if ( $referer !== null ) {
        $action .= "-{$referer}";
    }
    if ( $size ) {
        $name .= "-{$size}";
    }
    $ext = ( empty( $ext ) ? 'webp' : strtolower( sanitize_text_field( $ext ) ) );
    $allowDotExtension = Helpers::getSetting( 'advanced.allowDotExtension', false );
    if ( !$allowDotExtension ) {
        return home_url( sprintf(
            '/ccpigd/%s/%s/%s/%s/',
            $action,
            $key,
            $name,
            $ext
        ) );
    }
    if ( $action === 'attachment' ) {
        return home_url( sprintf(
            '/ccpigd/%s/%s/%s.%s/',
            $action,
            $key,
            $name,
            $ext
        ) );
    } else {
        return home_url( sprintf(
            '/ccpigd/%s/%s/%s/%s/',
            $action,
            $key,
            $name,
            $ext
        ) );
    }
}

function ccpigdGetFreeMemoryAvailable() {
    if ( function_exists( 'memory_get_usage' ) && function_exists( 'memory_get_peak_usage' ) ) {
        $memory_limit = ini_get( 'memory_limit' );
        if ( $memory_limit === false ) {
            return null;
        }
        $memory_limit = trim( $memory_limit );
        $last = strtolower( $memory_limit[strlen( $memory_limit ) - 1] );
        $memory_limit = (int) $memory_limit;
        switch ( $last ) {
            case 'g':
                $memory_limit *= 1024;
            // no break
            case 'm':
                $memory_limit *= 1024;
            // no break
            case 'k':
                $memory_limit *= 1024;
        }
        $used_memory = memory_get_usage( true );
        $free_memory = $memory_limit - $used_memory;
        return ( $free_memory > 0 ? $free_memory : 0 );
    }
    return null;
}

function ccpigdGetModules(  $type = null  ) {
    $commonJsDependencies = [];
    $modules = [
        [
            "id"          => "file-browser",
            "title"       => "File Browser",
            "description" => "Allow users to browse selected Google Drive files and folders directly on your site.",
            "icon"        => "folder",
            "dependency"  => [
                'js' => array_merge( ['wp-plupload'], $commonJsDependencies ),
            ],
        ],
        [
            "id"          => "file-uploader",
            "title"       => "File Uploader",
            "description" => "Allow users to upload files directly from their Google Drive.",
            "icon"        => "cloud_upload",
            "isPro"       => true,
            "isNew"       => true,
            "dependency"  => [
                'js' => array_merge( ['wp-plupload'], $commonJsDependencies ),
            ],
        ],
        [
            "id"          => "media-player",
            "title"       => "Media Player",
            "description" => "Allow users to play audio and video files from their Google Drive.",
            "icon"        => "stock_media",
            "isNew"       => true,
            "isPro"       => true,
            "dependency"  => [
                'js' => array_merge( ['wp-mediaelement', 'mediaelement'], $commonJsDependencies ),
            ],
        ],
        [
            "id"          => "gallery",
            "title"       => "Gallery",
            "description" => "Showcase images and Video from Google Drive in a visually appealing gallery format.",
            "icon"        => "imagesmode",
            "dependency"  => [
                'js' => $commonJsDependencies,
            ],
        ],
        [
            "id"          => "slider-carousel",
            "title"       => "Slider Carousel",
            "description" => "Display Google Drive images in an interactive slider or carousel format.",
            "icon"        => "slideshow",
            "isPro"       => true,
            "isNew"       => true,
            "dependency"  => [
                'js' => $commonJsDependencies,
            ],
        ],
        [
            "id"          => "embed-documents",
            "title"       => "Embed Documents",
            "description" => "Easily embed Google Docs, Sheets, and Slides into your website securely.",
            "icon"        => "text_compare",
            "isNew"       => true,
            "isPro"       => true,
            "dependency"  => [
                'js' => $commonJsDependencies,
            ],
        ],
        [
            "id"          => "search-box",
            "title"       => "Search Box",
            "description" => "Enable users to search and find specific files in your Google Drive.",
            "icon"        => "feature_search",
            "isNew"       => true,
            "isPro"       => true,
            "dependency"  => [
                'js' => $commonJsDependencies,
            ],
        ],
        [
            "id"          => "file-list",
            "title"       => "File List",
            "description" => "Display a simple list of files from your Google Drive with download links.",
            "icon"        => "event_list",
            "isNew"       => true,
            "isPro"       => true,
            "dependency"  => [
                'js' => $commonJsDependencies,
            ],
        ]
    ];
    if ( empty( $type ) ) {
        return $modules;
    }
    if ( $type === 'free' ) {
        return array_filter( $modules, function ( $module ) {
            return empty( $module['isPro'] );
        } );
    } elseif ( $type === 'pro' ) {
        return array_filter( $modules, function ( $module ) {
            return !empty( $module['isPro'] );
        } );
    } elseif ( $type === 'new' ) {
        return array_filter( $modules, function ( $module ) {
            return !empty( $module['isNew'] );
        } );
    } elseif ( $type === 'hot' ) {
        return array_filter( $modules, function ( $module ) {
            return !empty( $module['isHot'] );
        } );
    } else {
        return $modules;
    }
}

function ccpigdGetCurrentUserAccess() {
    if ( !function_exists( 'is_user_logged_in' ) || !function_exists( 'wp_get_current_user' ) || !class_exists( 'WP_User' ) ) {
        return false;
    }
    if ( !is_user_logged_in() ) {
        return false;
    }
    $currentUser = wp_get_current_user();
    if ( !$currentUser instanceof WP_User ) {
        return false;
    }
    $userName = $currentUser->user_login;
    $userRoles = $currentUser->roles;
    $accessSettings = UserAccess::getInstance()->getAccessData( $userName, $userRoles );
    if ( empty( $accessSettings ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        } else {
            return false;
        }
    }
    $accessSettingsPages = $accessSettings['pages'] ?? [];
    if ( !is_array( $accessSettingsPages ) || empty( $accessSettingsPages ) || is_wp_error( $accessSettingsPages ) ) {
        return false;
    }
    $accessFolders = $accessSettings['folders'] ?? [];
    if ( !is_array( $accessFolders ) || is_wp_error( $accessFolders ) || empty( $accessFolders ) ) {
        return false;
    }
    return $accessSettings;
}

function ccpigdHasUserAccessPage(  $pages = [], $relation = "AND"  ) {
    $pages = (array) $pages;
    $accessData = ccpigdGetCurrentUserAccess();
    if ( $accessData === true ) {
        return true;
    }
    if ( $accessData === false ) {
        return false;
    }
    $allowedPages = $accessData['pages'] ?? [];
    if ( empty( $allowedPages ) || !is_array( $allowedPages ) ) {
        return false;
    }
    if ( empty( $pages ) ) {
        return true;
    }
    if ( $relation === "AND" ) {
        return empty( array_diff( $pages, $allowedPages ) );
    } elseif ( $relation === "OR" ) {
        return !empty( array_intersect( $pages, $allowedPages ) );
    }
    return false;
}

if ( !function_exists( "ccpigdGetAvailableThumbnailSizes" ) ) {
    function ccpigdGetAvailableThumbnailSizes() {
        return [
            'xs'  => '32x32',
            'sm'  => '64x64',
            'md'  => '128x128',
            'lg'  => '256x256',
            'xl'  => '480x320',
            '2xl' => '640x480',
            '3xl' => '960x640',
            '4xl' => '1024x768',
            '5xl' => '',
        ];
    }

}
/**
 * Convert a string from snake_case, kebab-case, or space separated to camelCase.
 *
 * @param string $input The input string to convert.
 * @return string The camelCase formatted string.
 */
function ccpigdToCamelCase(  $input  ) {
    $normalized = preg_replace( '/[^a-zA-Z0-9]+/', ' ', $input );
    $normalized = strtolower( $normalized );
    $normalized = ucwords( $normalized );
    $normalized = str_replace( ' ', '', $normalized );
    return lcfirst( $normalized );
}
