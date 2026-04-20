<?php

namespace CodeConfig\IGD\Updates;

use function intval;
use function is_array;
use function is_object;

defined('ABSPATH') || exit;

use WP_Error;

class Update_1_4_0 extends Updater
{
    public const VERSION = '1.4.0';

    /**
     * Run update.
     *
     * @return string|WP_Error
     */
    public function run_update()
    {
        try {
            $result = $this->alterModuleTable();

            if (is_wp_error($result)) {
                return $result;
            }

            $migrateFiles = $this->migrateFiles();

            if (is_wp_error($migrateFiles)) {
                return $migrateFiles;
            }

            $migrateSettings = $this->migrateSettings();

            if (is_wp_error($migrateSettings)) {
                return $migrateSettings;
            }

            $migrateModules = $this->migrateModules();
            if (is_wp_error($migrateModules)) {
                return $migrateModules;
            }

            add_action('admin_init', [$this, 'setRewriteRules']);
            $this->setRewriteRules();

            return self::VERSION;

        } catch (\Throwable $e) {
            error_log('Error during update ' . self::VERSION . ': ' . $e->getMessage());

            return new WP_Error(
                'ccpigd_update_failed',
                'Update ' . self::VERSION . ' failed.' . ' Error: ' . $e->getMessage()
            );
        }
    }

    private function alterModuleTable()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}integration_google_drive_user_access";

        // Check if table exists
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        if ($tableExists !== $table) {
            return new WP_Error('ccpigd_table_missing', 'Required table does not exist.');
        }

        // Check if old column exists
        $forceExists = $this->columnExists($table, 'force');

        if (!$forceExists) {
            // Nothing to migrate
            return true;
        }

        $statusExists = $this->columnExists($table, 'status');
        if ($statusExists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $updateStatus = $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN `status` VARCHAR(10) NOT NULL DEFAULT 'active'", $table));

            if ($updateStatus === false) {
                return new WP_Error('ccpigd_update_failed', 'Failed updating status column.');
            }

            wp_cache_delete(
                "ccpigd_column_exists_{$table}_status",
                'ccpigd_schema'
            );
        }

        $forceExists = $this->columnExists($table, 'force');

        if ($forceExists) {
            // Drop old column
            $dropColumn = $wpdb->query(
                $wpdb->prepare(
                    "ALTER TABLE %i DROP COLUMN `force`",
                    $table
                )
            );

            if ($dropColumn === false) {
                return new WP_Error('ccpigd_drop_column_failed', 'Failed dropping force column.');
            }

            wp_cache_delete(
                "ccpigd_column_exists_{$table}_force",
                'ccpigd_schema'
            );
        }

        // Add new column only if it does not already exist
        $pagesExists = $this->columnExists($table, 'pages');

        if (!$pagesExists) {
            $addColumn = $wpdb->query(
                $wpdb->prepare(
                    "ALTER TABLE %i ADD COLUMN `pages` LONGTEXT DEFAULT NULL AFTER `folders`",
                    $table
                )
            );

            if ($addColumn === false) {
                return new WP_Error('ccpigd_add_column_failed', 'Failed adding pages column.');
            }
            wp_cache_delete("ccpigd_column_exists_{$table}_pages", 'ccpigd_schema');
        }


        unset($tableExists, $forceExists, $statusExists, $dropColumn, $addColumn);

        return true;
    }

    private function migrateFiles()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}integration_google_drive_files";

        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        if ($tableExists !== $table) {
            return new WP_Error('ccpigd_table_missing', 'Required table does not exist.');
        }

        $operations = [];

        if (!$this->columnExists($table, 'description')) {
            $operations[] = "ADD COLUMN `description` LONGTEXT NULL AFTER `name`";
        }

        if (!$this->columnExists($table, 'additionalData')) {
            $operations[] = "ADD COLUMN `additionalData` LONGTEXT NULL AFTER `thumbnail`";
        }

        if (!$this->columnExists($table, 'metaData')) {
            $operations[] = "ADD COLUMN `metaData` LONGTEXT NULL AFTER `additionalData`";
        }

        if (!$this->columnExists($table, 'media')) {
            $operations[] = "ADD COLUMN `media` LONGTEXT NULL AFTER `isShared`";
        }

        if (!$this->columnExists($table, 'permissions')) {
            $operations[] = "ADD COLUMN `permissions` LONGTEXT NULL AFTER `media`";
        }

        if ($this->columnExists($table, 'thumbnailLink') && !$this->columnExists($table, 'thumbnail')) {
            $operations[] = "RENAME COLUMN `thumbnailLink` TO `thumbnail`";
        }

        if ($this->columnExists($table, 'isDirectory') && !$this->columnExists($table, 'isDir')) {
            $operations[] = "RENAME COLUMN `isDirectory` TO `isDir`";
        }

        foreach (['thumbnails', 'exportLinks', 'previewLink', 'downloadLink', 'isOwnedByMe'] as $col) {
            if ($this->columnExists($table, $col)) {
                $operations[] = $wpdb->prepare("DROP COLUMN %i", $col);
            }
        }

        if (empty($operations)) {
            return true;
        }

        $sql = $wpdb->prepare("ALTER TABLE %i " . implode(', ', $operations), $table);

        $result = $wpdb->query($sql);

        if ($result === false) {
            return new WP_Error(
                'ccpigd_migration_failed',
                $wpdb->last_error
            );
        }

        wp_cache_flush_group('ccpigd_schema');

        $this->migrateFileData();

        unset($tableExists, $operations, $sql, $result);

        return true;
    }

    private function migrateFileData()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}integration_google_drive_files";

        $existsFileData = $this->columnExists($table, 'fileData');

        if (!$existsFileData) {
            return true; // Nothing to migrate
        }

        $files = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, fileData FROM %i WHERE fileData IS NOT NULL",
                $table
            ),
            ARRAY_A
        );

        if (empty($files)) {
            return true; // No data to migrate
        }

        foreach ($files as $file) {
            $_fileData = maybe_unserialize($file['fileData']);

            if (!is_object($_fileData)) {
                continue; // Skip if data is not an object
            }

            $name           = $_fileData->name           ?? null;
            $description    = $_fileData->description    ?? null;
            $size           = $_fileData->size           ?? null;
            $mimeType       = $_fileData->mimeType       ?? null;
            $extension      = $_fileData->extension      ?? null;
            $icon           = str_replace('/32/', '/128/', $_fileData->icon ?? '');
            $thumbnail      = $_fileData->thumbnail      ?? null;
            $isDir          = $_fileData->isDirectory    ?? null;
            $isStarred      = $_fileData->isStarred      ?? null;
            $isShared       = $_fileData->isShared       ?? null;
            $media          = $_fileData->media          ?? null;
            $permissions    = $_fileData->permissions    ?? null;



            $additionalData = [
                'trashed'              => intval($_fileData->isTrashed ?? 0),
                'driveId'              => $_fileData->driveId               ?? null,
                'baseName'             => $_fileData->baseName              ?? null,
                'ownedByMe'            => intval($_fileData->isOwnedByMe ?? 0),
                'lastEdited'           => $_fileData->lastEdited            ?? null,
                'createdTime'          => $_fileData->createdTime           ?? null,
                'resourceKey'          => $_fileData->resourceKey           ?? null,
                'canEditInCloud'       => intval($_fileData->canEditInCloud ?? 0),
                'isVirtualFolder'      => intval($_fileData->isVirtualFolder ?? 0),
                'canPreviewInCloud'    => intval($_fileData->canPreviewInCloud ?? 0),
            ];

            $updateResult = $wpdb->update(
                $table,
                [
                    'name'           => $name,
                    'description'    => $description,
                    'size'           => intval($size),
                    'mimeType'       => $mimeType,
                    'extension'      => $extension,
                    'icon'           => $icon,
                    'thumbnail'      => $thumbnail,
                    'isDir'          => intval($isDir),
                    'isStarred'      => intval($isStarred),
                    'isShared'       => intval($isShared),
                    'additionalData' => maybe_serialize($additionalData),
                    'media'          => maybe_serialize($media),
                    'permissions'    => maybe_serialize($permissions),
                ],
                ['id' => $file['id']],
                [
                    '%s', // name
                    '%s', // description
                    '%d', // size
                    '%s', // mimeType
                    '%s', // extension
                    '%s', // icon
                    '%s', // thumbnail
                    '%d', // isDir
                    '%d', // isStarred
                    '%d', // isShared
                    '%s', // additionalData
                    '%s', // media
                    '%s', // permissions
                ],
                ['%s']
            );

            if ($updateResult === false) {
                error_log('Failed migrating file ID ' . $file['id'] . ': ' . $wpdb->last_error);
            }

            unset($name, $description, $size, $mimeType, $extension, $icon, $thumbnail, $isDir, $isStarred, $isShared, $media, $permissions, $additionalData);
        }

        if ($existsFileData) {
            $wpdb->query(
                $wpdb->prepare(
                    "ALTER TABLE %i DROP COLUMN `fileData`",
                    $table
                )
            );
            wp_cache_delete("ccpigd_column_exists_{$table}_fileData", 'ccpigd_schema');
        }

        unset($existsFileData, $table);
    }

    private function migrateSettings()
    {
        $settings = get_option(CCPIGD_OPTIONS_NAME, []);

        if (empty($settings) || !is_array($settings)) {
            return true;
        }

        $changesModuleKey = [
            'gutenbergModules' => 'gutenberg',
            'elementorModules' => 'elementor',
            'tinyMce'          => 'classicEditor',
            'ccpIgdWPforms'    => 'wpForms',
        ];

        $activeIntegrations = $settings['integrations']['activeIntegrations'] ?? [];

        $updatedActiveIntegrations = array_map(fn ($integration) => $changesModuleKey[$integration] ?? $integration, $activeIntegrations);

        $settings['integrations']['activeIntegrations'] = $updatedActiveIntegrations;

        $settings['integrations']['mediaLibrary']['folders'] = array_filter(
            array_column($settings['integrations']['mediaLibrary']['folders'] ?? [], 'key')
        );

        $settings['synchronization']['folders'] = array_filter(
            array_column($settings['synchronization']['folders'] ?? [], 'key')
        );

        if (isset($settings['userAccess'])) {
            unset($settings['userAccess']);
        }

        // Advanced settings restructuring
        if (isset($settings['advanced'])) {
            $settings['advanced']['allowDotExtension'] = true;
        }

        // Appearance settings restructuring
        if (isset($settings['appearance'])) {
            $settings['appearance']['preloader'] = $settings['appearance']['selectedPreloader'] ?? '1';
        }

        update_option(CCPIGD_OPTIONS_NAME, $settings);

        unset($settings, $activeIntegrations, $updatedActiveIntegrations);

        return true;
    }

    private function migrateModules()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}integration_google_drive_shortcodes";

        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        if ($tableExists !== $table) {
            return new WP_Error('ccpigd_table_missing', 'Required table does not exist.');
        }

        $modules = $wpdb->get_results(
            $wpdb->prepare("SELECT id, data, status, type FROM %i", $table),
            ARRAY_A
        );

        if (empty($modules)) {
            return true; // Nothing to migrate
        }

        foreach ($modules as $module) {
            $moduleData         = maybe_unserialize($module['data']);
            $status             = $module['status'] === 'on' ? 'active' : 'inactive';
            $type               = $module['type'] ?? '';
            $migratedModuleData = $this->migrateModuleData($moduleData, $type);

            $updateResult = $wpdb->update(
                $table,
                ['data'   => maybe_serialize($migratedModuleData), 'status' => $status],
                ['id'     => $module['id']],
                ['%s', '%s'],
                ['%d']
            );

            if ($updateResult === false) {
                error_log('Failed migrating module ID ' . $module['id'] . ': ' . $wpdb->last_error);
            }

            unset($moduleData, $status, $type, $migratedModuleData);
        }

        return true;
    }

    private function migrateModuleData($moduleData, $type)
    {
        if (!is_array($moduleData)) {
            return $moduleData;
        }

        $migratedData = [];

        // Helper to unset multiple keys
        $unsetKeys = function (&$array, $keys) {
            foreach ($keys as $k) {
                unset($array[$k]);
            }
        };

        foreach ($moduleData as $key => $value) {
            switch ($key) {
                case 'source':
                    $fileKeys = array_map(function ($fileKey) {
                        return [
                            'fileKey'      => $fileKey['key']          ?? '',
                            'thumbnailKey' => $fileKey['thumbnailKey'] ?? '',
                        ];
                    }, (array) ($value['fileKeys'] ?? []));

                    $migratedData['source'] = [
                            'fileKeys'      => $fileKeys,
                            'privateFolder' => false,
                        ];
                    break;
                case 'filter':
                    $migratedData['filter'] = [
                        'extension' => [
                            'all'     => filter_var($value['allowAllExtensions'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'include' => $value['allowExtensions']       ?? [],
                            'exclude' => $value['allowExceptExtensions'] ?? [],
                        ],
                        'name' => [
                            'all'     => filter_var($value['allowAllNames'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'include' => $value['allowNames']       ?? '',
                            'exclude' => $value['allowExceptNames'] ?? '',
                            'applyTo' => $value['applyNameFilter']  ?? [
                                'files'   => true,
                                'folders' => false,
                            ],
                        ],
                    ];

                    if ('file-uploader' === $type || 'file-browser' === $type) {
                        $migratedData['filter']['upload'] = [
                            'maxFiles' => intval($value['maxFileUpload'] ?? 0),
                            'minSize'  => intval($value['minUploadSize'] ?? 0),
                            'maxSize'  => intval($value['maxUploadSize'] ?? 0),
                        ];
                    }

                    break;
                case 'advanced':
                    $migratedData['advanced'] = [
                        'width' => [
                            'value' => $value['containerWidth'] ?? '',
                            'unit'  => $value['widthUnit']      ?? '%',
                        ],
                        'height' => [
                            'value' => $value['containerHeight'] ?? '',
                            'unit'  => $value['heightUnit']      ?? 'auto',
                        ],
                        'theme'               => $value['moduleTheme'] ?? 'light',
                        'borderBoxVisibility' => !filter_var($value['hideBorderBox'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'files'               => [
                            'loadingType' => str_replace('-', '_', $value['fileLoadingType'] ?? 'load_more'),
                            'perPage'     => intval($value['filesInFirstRender'] ?? 20),
                        ],
                        'autoFetch' => [
                            'status'   => filter_var($value['autoFetch'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'interval' => intval($value['autoFetchInterval'] ?? 60),
                        ],
                        'sort' => $value['sort'] ?? [
                            'orderBy' => 'name',
                            'order'   => 'ASC',
                        ],
                    ];

                    if ($type === 'file-browser') {
                        $fileBrowser                                              = $value['file-browser']         ?? [];

                        $migratedData['advanced']['fileBrowser']['folderView']    = $fileBrowser['folderView']     ?? 'grid';
                        $migratedData['advanced']['fileBrowser']['headerOptions'] = $fileBrowser['headerOptions']  ?? [
                            'breadcrumb' => false,
                            'refresh'    => false,
                            'sorting'    => false,
                        ];

                        $migratedData['advanced']['fileBrowser']['headerOptions']['rootUpload'] = false;
                        $migratedData['advanced']['fileBrowser']['listViewTableHead']           = [
                            'action'    => 'Action',
                            'enable'    => false,
                            'name'      => 'Name',
                            'size'      => 'Size',
                            'type'      => 'Type',
                            'updated'   => 'Updated',
                        ];

                    } elseif ($type === 'file-uploader') {
                        $fileUploader                                                          = $value['file-uploader']                 ?? [];

                        $migratedData['advanced']['fileUploader']['showBoxLabel']              = $fileUploader['showBoxLabel']           ?? true;
                        $migratedData['advanced']['fileUploader']['labelText']                 = $fileUploader['labelText']              ?? 'Upload Files';
                        $migratedData['advanced']['fileUploader']['uploadImmediately']         = $fileUploader['uploadImmediately']      ?? false;
                        $migratedData['advanced']['fileUploader']['showUploadConfirmation']    = $fileUploader['showUploadConfirmation'] ?? false;
                        $migratedData['advanced']['fileUploader']['confirmationMessage']       = $fileUploader['confirmationMessage']    ?? '<h3>Upload successful!</h3><p>Your file(s) have been uploaded. Thank you for your submission!</p>';

                        $migratedData['advanced']['fileUploader']['secureVideoPlayback']      = false;
                        $migratedData['advanced']['fileUploader']['folderUpload']             = false;
                        $migratedData['advanced']['fileUploader']['multipleUpload']           = false;
                        $migratedData['advanced']['fileUploader']['renameFile']               = '';
                        $migratedData['advanced']['fileUploader']['uploadPreview']            = [
                            'enable'            => false,
                            'listViewTableHead' => [
                                'action'    => 'Action',
                                'enable'    => false,
                                'name'      => 'Name',
                                'size'      => 'Size',
                                'type'      => 'Type',
                                'updated'   => 'Updated',
                            ],
                            'previewStyle' => 'grid',
                            'showHeader'   => [
                                'enable'     => false,
                                'breadcrumb' => true,
                                'sorting'    => true,
                            ]
                        ];

                        unset($migratedData['advanced']['autoFetch']);
                    } elseif ($type === 'media-player') {
                        $mediaPlayer                                                       = $value['media-player'] ?? [];

                        $migratedData['advanced']['mediaPlayer']['columns']                = 1;
                        $migratedData['advanced']['mediaPlayer']['openedPlaylist']         = $mediaPlayer['openedPlaylist'] ?? false;
                        $migratedData['advanced']['mediaPlayer']['playlistLayout']         = 'list';
                        $migratedData['advanced']['mediaPlayer']['playlistPosition']       = $mediaPlayer['playlistPosition']       ?? 'right';
                        $migratedData['advanced']['mediaPlayer']['playlistTitle']          = $mediaPlayer['playListTitle']          ?? 'All Content';
                        $migratedData['advanced']['mediaPlayer']['showAndHidePlaylist']    = $mediaPlayer['showAndHidePlaylist']    ?? true;
                        $migratedData['advanced']['mediaPlayer']['showNextPrevious']       = $mediaPlayer['showNextPrevious']       ?? true;
                        $migratedData['advanced']['mediaPlayer']['showNumberPrefix']       = $mediaPlayer['showNextPrefix']         ?? true;
                        $migratedData['advanced']['mediaPlayer']['showThumbnail']          = $mediaPlayer['showThumbnail']          ?? true;
                        $migratedData['advanced']['mediaPlayer']['videoRatio']             = $mediaPlayer['videoRatio']             ?? '16/9';

                        unset($migratedData['advanced']['files']);

                    } elseif ($type === 'gallery') {
                        $gallery                                        = $value['gallery']   ?? [];

                        $migratedData['advanced']['gallery']['columns'] = [
                            'desktop' => intval($gallery['columns']['desktop'] ?? 4),
                            'laptop'  => intval($gallery['columns']['tablet'] ?? 3),
                            'tablet'  => intval($gallery['columns']['tablet'] ?? 2),
                            'mobile'  => intval($gallery['columns']['mobile'] ?? 1),
                        ];
                        $migratedData['advanced']['gallery']['columnsDevice']                    = 'desktop';
                        $migratedData['advanced']['gallery']['layout']                           = ($gallery['layout'] ?? 'grid') == 'justified' ? 'grid' : $gallery['layout'] ?? 'grid';
                        $migratedData['advanced']['gallery']['overlayDisplayDescription']        = $gallery['overlayOptions']['description']                                   ?? true;
                        $migratedData['advanced']['gallery']['overlayDisplayNumber']             = false;
                        $migratedData['advanced']['gallery']['overlayDisplayTitle']              = $gallery['overlayOptions']['title']      ?? true;
                        $migratedData['advanced']['gallery']['showOverlay']                      = $gallery['showOverlay']                  ?? true;
                        $migratedData['advanced']['gallery']['thumbnailQuality']                 = $gallery['thumbnailQuality']             ?? 'thumbnail';
                        $migratedData['advanced']['gallery']['thumbnailRadius']                  = [
                            'value' => ($gallery['thumbnailView'] ?? '') === 'rounded' ? 1 : 0,
                            'unit'  => 'rem',
                        ];
                        $migratedData['advanced']['gallery']['thumbnailSpacing']                  = [
                            'value' => $gallery['imgMargin'] ?? 10,
                            'unit'  => 'px',
                        ];
                    } elseif ($type === 'slider-carousel') {
                        $sliderCarousel                                                         = $value['slider-carousel'] ?? [];

                        $migratedData['advanced']['sliderCarousel']['autoPlaySpeed']            = intval($sliderCarousel['autoPlaySpeed'] ?? 0);
                        $migratedData['advanced']['sliderCarousel']['borderRadius']             = intval($sliderCarousel['borderRadius'] ?? 0);
                        $migratedData['advanced']['sliderCarousel']['itemGap']                  = intval($sliderCarousel['itemGap'] ?? 0);
                        $migratedData['advanced']['sliderCarousel']['mouseControl']             = filter_var($sliderCarousel['mouseControl'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $migratedData['advanced']['sliderCarousel']['navigationStyle']          = 'arrows-dots';
                        $migratedData['advanced']['sliderCarousel']['showNavigation']           = filter_var($sliderCarousel['showNavigation'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $migratedData['advanced']['sliderCarousel']['showOverlay']              = false;
                        $migratedData['advanced']['sliderCarousel']['showSliderCaption']        = false;
                        $migratedData['advanced']['sliderCarousel']['slideAutoPlay']            = filter_var($sliderCarousel['slideAutoPlay'] ?? true, FILTER_VALIDATE_BOOLEAN);
                        $migratedData['advanced']['sliderCarousel']['slideToShow']              = [
                            'desktop' => intval($sliderCarousel['slideToShow']['desktop'] ?? 4),
                            'tablet'  => intval($sliderCarousel['slideToShow']['tablet'] ?? 2),
                            'mobile'  => intval($sliderCarousel['slideToShow']['mobile'] ?? 1),
                        ];
                        $migratedData['advanced']['sliderCarousel']['slideToShowDisplay']       = $sliderCarousel['slideToShowDisplay']   ?? 'desktop';
                        $migratedData['advanced']['sliderCarousel']['sliderDirection']          = $sliderCarousel['sliderType']           ?? 'horizontal';
                        $migratedData['advanced']['sliderCarousel']['sliderEffect']             = $sliderCarousel['sliderEffect']         ?? 'slide';
                        $migratedData['advanced']['sliderCarousel']['sliderType']               = 'normal';
                        $migratedData['advanced']['sliderCarousel']['thumbnailQuality']         = 'thumbnail';

                        unset($migratedData['advanced']['files']['loadingType']);

                    } elseif ($type === 'embed-documents') {
                        $embedDocuments                             = $value['embed-documents'] ?? [];

                        $migratedData['advanced']['embedDocuments'] = [
                            'allowPopOut'          => $embedDocuments['allowPopOut']                  ?? false,
                            'height'               => [
                                'value' => $embedDocuments['height']          ?? 650,
                                'unit'  => $embedDocuments['heightUnit']      ?? 'px',
                            ],
                            'showFileName' => $embedDocuments['showFileName']               ?? true,
                            'width'        => [
                                'value' => $embedDocuments['width']           ?? 100,
                                'unit'  => $embedDocuments['widthUnit']       ?? '%',
                            ],
                        ];
                    } elseif ($type === 'search-box') {
                        $searchBox                             = $value['search-box'] ?? [];

                        $migratedData['advanced']['searchBox'] = $value ?? [
                            'browserView'         => $searchBox['browserView']      ?? 'grid',
                            'searchBoxText'       => $searchBox['searchBoxText']    ?? 'Search for files & folders...',
                            'showLastModified'    => $searchBox['showLastModified'] ?? false,
                        ];
                        $migratedData['advanced']['searchBox']['secureVideoPlayback'] = false;
                    } elseif ($type === 'file-list') {
                        $migratedData['advanced']['fileList'] = [
                            'activeView'  => 'list',
                            'listDisplay' => [
                                'name' => [
                                    'enable'  => true,
                                    'text'    => 'File Info',
                                ],
                                'thumbnail' => [
                                    'enable' => true,
                                ],
                                'extension' => [
                                    'enable'  => true,
                                    'text'    => 'Extension',
                                ],
                                'size' => [
                                    'enable'  => $value['showFileSize'] ?? true,
                                    'text'    => 'Size',
                                ],
                                'date' => [
                                    'enable'  => $value['showTimeStamp'] ?? true,
                                    'text'    => 'Modified',
                                ],
                                'actions' => [
                                    'enable'  => true,
                                    'text'    => 'Actions',
                                ]
                            ],
                            'secureVideoPlayback' => false,
                        ];
                    }
                    break;
                case 'notification':
                    $notifications                 = $value ?? [];
                    $migratedData['notifications'] = [
                        'enable'          => $notifications['enable']                   ?? [],
                        'emailRecipients' => $notifications['emailRecipients']          ?? '',
                        'skipCurrentUser' => $notifications['skipCurrentUser']          ?? false,
                    ];

                    if ($type === 'file-browser') {
                        $migratedData['notifications']['newFolder']           = $notifications['new_folder']                 ?? false;
                        $migratedData['notifications']['upload']              = $notifications['upload']                     ?? false;
                        $migratedData['notifications']['preview']             = $notifications['preview']                    ?? false;
                        $migratedData['notifications']['rename']              = $notifications['rename']                     ?? false;
                        $migratedData['notifications']['download']            = $notifications['download']                   ?? false;
                        $migratedData['notifications']['copy']                = $notifications['copy']                       ?? false;
                        $migratedData['notifications']['move']                = $notifications['move']                       ?? false;
                        $migratedData['notifications']['share']               = $notifications['create_share_link']          ?? false;
                        $migratedData['notifications']['viewShareLink']       = $notifications['view_share_link']            ?? false;
                        $migratedData['notifications']['delete']              = $notifications['delete']                     ?? false;
                    } elseif ($type === 'file-uploader') {
                        $migratedData['notifications']['upload']              = $notifications['upload']       ?? false;
                    } elseif ($type === 'media-player') {
                        $migratedData['notifications']['download']              = $notifications['download']       ?? false;
                    } elseif ($type === 'gallery' || $type === 'search-box') {
                        $migratedData['notifications']['download'] = $notifications['download']       ?? false;
                        $migratedData['notifications']['preview']  = $notifications['preview']        ?? false;
                    } elseif ($type === 'slider-carousel' || $type === 'embed-documents') {
                        unset($migratedData['notifications']);
                    } elseif ($type === 'file-list') {
                        $migratedData['notifications']['download']            = $notifications['download'] ?? false;
                        $migratedData['notifications']['preview']             = false;
                        $migratedData['notifications']['rename']              = false;
                        $migratedData['notifications']['share']               = false;
                        $migratedData['notifications']['delete']              = false;
                    }
                    break;
                case 'permissions':
                    $permissions                 = $value ?? [];
                    $migratedData['permissions'] = [
                        'passwordProtect' => $permissions['passwordProtect'] ?? [
                            'enable'   => false,
                        ],
                        'displayFor'  => $permissions['displayFor']  ?? [
                            'accessDeniedMessage'     => "You do not have access to this module.",
                            'displayFor'              => [],
                            'loggedInUserType'        => 'users',
                            'showAccessDeniedMessage' => true,
                            'whoCanViewModule'        => 'everyone',
                        ],
                    ];

                    $defaultValue = [
                        'enable'           => false,
                        'loggedInUserType' => 'users',
                        'userAccess'       => 'everyone',
                    ];

                    if ($type === 'file-browser') {
                        $migratedData['permissions']['newFolder']           = $permissions['newFolder']         ?? $defaultValue;
                        $migratedData['permissions']['copy']                = $permissions['moveAndCopy']       ?? $defaultValue;
                        $migratedData['permissions']['move']                = $permissions['moveAndCopy']       ?? $defaultValue;
                        $migratedData['permissions']['delete']              = $permissions['delete']            ?? $defaultValue;
                        $migratedData['permissions']['download']            = $permissions['download']          ?? $defaultValue;
                        $migratedData['permissions']['preview']             = $permissions['preview']           ?? $defaultValue;
                        $migratedData['permissions']['rename']              = $permissions['rename']            ?? $defaultValue;
                        $migratedData['permissions']['search']              = $permissions['searchPermission']  ?? $defaultValue;
                        $migratedData['permissions']['share']               = $permissions['allowShare']        ?? $defaultValue;
                        $migratedData['permissions']['upload']              = $permissions['upload']            ?? $defaultValue;
                    } elseif ($type === 'file-uploader') {
                        $migratedData['permissions']['newFolder']           = $permissions['newFolder']         ?? $defaultValue;
                        $migratedData['permissions']['delete']              = $permissions['delete']            ?? $defaultValue;
                        $migratedData['permissions']['download']            = $permissions['download']          ?? $defaultValue;
                        $migratedData['permissions']['preview']             = $permissions['preview']           ?? $defaultValue;
                        $migratedData['permissions']['rename']              = $permissions['rename']            ?? $defaultValue;
                        $migratedData['permissions']['search']              = $permissions['searchPermission']  ?? $defaultValue;
                        $migratedData['permissions']['share']               = $permissions['allowShare']        ?? $defaultValue;
                    } elseif ($type === 'media-player') {
                        $migratedData['permissions']['download']            = $permissions['download']          ?? $defaultValue;
                    } elseif ($type === 'gallery' || $type === 'search-box') {
                        $migratedData['permissions']['download']            = $defaultValue;
                        $migratedData['permissions']['preview']             = $permissions['preview']           ?? $defaultValue;
                    } elseif ($type === 'file-list') {
                        $migratedData['permissions']['download']            = $permissions['download']      ?? $defaultValue;
                        $migratedData['permissions']['preview']             = $defaultValue;
                        $migratedData['permissions']['rename']              = $defaultValue;
                        $migratedData['permissions']['share']               = $defaultValue;
                        $migratedData['permissions']['delete']              = $defaultValue;
                    }
            }
            unset($key, $value);
        }

        return $migratedData;
    }

    public function setRewriteRules()
    {

        if (function_exists('add_rewrite_rule') === false || function_exists('add_rule') === false) {
            set_transient('ccpigd_rewrite_rules_error', 'Required rewrite functions are not available.', 10 * MINUTE_IN_SECONDS);

            return;
        }

        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)/([^/]+)\.([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]',
            'top'
        );
        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)/([^/]+)/([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]&ccpigd-name=$matches[3]&ccpigd-ext=$matches[4]',
            'top'
        );

        add_rewrite_rule(
            '^ccpigd/([^/]+)/([^/]+)$',
            'index.php?ccpigd-action=$matches[1]&ccpigd-key=$matches[2]',
            'top'
        );

        if (get_transient('ccpigd_rewrite_rules_error')) {
            delete_transient('ccpigd_rewrite_rules_error');
        }

        flush_rewrite_rules();
    }
}
