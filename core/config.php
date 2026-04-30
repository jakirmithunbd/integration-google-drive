<?php

defined('ABSPATH') or exit('Direct access to this file is not allowed.');

/**
 * Plugin version information
 */
define('CCPIGD_DB_VERSION', '1.0.0');
define('CCPIGD_OPTIONS_VERSION', '1.0.0');
define('CCPIGD_VERSION', '1.4.5');

define('CCPIGD_URL', plugin_dir_url(CCPIGD_FILE));
define('CCPIGD_ASSETS', CCPIGD_URL . 'assets');

define('CCPIGD_BUILD_ASSETS', CCPIGD_URL . 'build');
define('CCPIGD_PLUGIN_URL', 'https://codeconfig.dev/integration-google-drive/');
define('CCPIGD_INCLUDES_URL', CCPIGD_URL . 'includes');
define('CCPIGD_INTEGRATIONS_URL', CCPIGD_INCLUDES_URL . '/Integrations');
define('CCPIGD_BLOCKS_URL', CCPIGD_INTEGRATIONS_URL . '/Blocks');


/**
 * Plugin directory paths
 */
define('CCPIGD_PATH', plugin_dir_path(CCPIGD_FILE));
define('CCPIGD_APP', CCPIGD_PATH . 'app');
define('CCPIGD_INCLUDES', CCPIGD_PATH . 'includes');
define('CCPIGD_INTEGRATIONS', CCPIGD_INCLUDES . '/Integrations');
define('CCPIGD_MODELS', CCPIGD_PATH . 'models');
define('CCPIGD_UPDATES', CCPIGD_INCLUDES . '/Updates');
define('CCPIGD_VENDORS', CCPIGD_PATH . 'vendors');


/**
 * Plugin author information
 */
define('CCPIGD_AUTHOR', 'CodeConfig');
define('CCPIGD_AUTHOR_URL', 'https://codeconfig.dev');

/**
 * Plugin capabilities and access
 */
define('CCPIGD_ACCESS_CAP', 'manage_ccpigd_files');

define('CCPIGD_MANUAL_REDIRECT_URI', site_url("/?authorization=integration-google-drive"));

/**
 * Plugin constants for Google Drive API fields
 */
define('CCPIGD_FILE_FIELDS', 'capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,starred,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey');
define('CCPIGD_LIST_CHANGES_FIELDS', 'changes(file(' . CCPIGD_FILE_FIELDS . '),removed, changeType, fileId),newStartPageToken,nextPageToken');
define('CCPIGD_LIST_FIELDS', 'files( ' . CCPIGD_FILE_FIELDS . '),nextPageToken');

/**
 * Plugin localization
 */
define('CCPIGD_TEXTDOMAIN', 'integration-google-drive');
define('CCPIGD_TEXTDOMAIN_PATH', dirname(plugin_basename(CCPIGD_FILE)) . '/languages/');

/**
 * Plugin naming and slug
 */
define('CCPIGD_NAME', 'Integration Google Drive');
define('CCPIGD_OPTIONS_NAME', 'ccpigd_settings');
define('CCPIGD_SLUG', CCPIGD_TEXTDOMAIN);

/**
 * Plugin minimum requirements
 */
define('CCPIGD_PHP_VERSION', '7.4');
define('CCPIGD_WP_VERSION', '5.2');

/**
 * Plugin database
 */
define('CCPIGD_DB_PREFIX', 'ccpigd_');

/**
 * Chunk size for file uploads (5 MB)
 */
define('CCPIGD_CHUNK_SIZE', 5 * 1024 * 1024);

define('CCPIGD_UNSET', '[unset]');

/**
 * Plugin documentation URL
 */
define('CCPIGD_DOCUMENTATION_URL', 'https://codeconfig.dev/docs-category/integration-google-drive/');

// Required functions for the plugin to work
require_once dirname(__FILE__) . "/functions.php";
