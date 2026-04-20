<?php

namespace CodeConfig\IGD\Utils;

use function in_array;
use function is_array;

defined('ABSPATH') || exit('No direct script access allowed');

class MimeTypeManager
{
    private static $mimeMap    = null;
    private static $reverseMap = null;

    public const CATEGORY_EXPORT_TYPE = [
        'document'     => ['pdf','docx','odt','rtf','txt','html','epub','zip','markdown'],
        'presentation' => ['pptx','odp','pdf','txt','jpeg','png','svg'],
        'spreadsheet'  => ['xlsx','ods','csv','tsv','pdf','zip'],
        'drawing'      => ['png','jpeg','svg','pdf'],
        'script'       => ['json'],
    ];

    public const EXPORT_MIME_TYPE_MAP = [
        'application/pdf'                                                              => 'pdf',
        'application/json'                                                             => 'json',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'      => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'            => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'    => 'pptx',
        'application/vnd.oasis.opendocument.spreadsheet'                               => 'ods',
        'application/vnd.oasis.opendocument.presentation'                              => 'odp',
        'application/vnd.oasis.opendocument.text'                                      => 'odt',
        'text/plain'                                                                   => 'txt',
        'text/csv'                                                                     => 'csv',
        'image/png'                                                                    => 'png',
        'image/jpeg'                                                                   => 'jpeg',
        'image/svg+xml'                                                                => 'svg',
        'application/epub+zip'                                                         => 'epub',
        'application/rtf'                                                              => 'rtf',
        'text/markdown'                                                                => 'markdown',
        'application/zip'                                                              => 'zip',
        'text/html'                                                                    => 'html',
        'text/tab-separated-values'                                                    => 'tsv',
    ];

    public const EDITOR_MIME_TYPE_MAP = [
        'application/vnd.google-apps.document'       => 'document',
        'application/vnd.google-apps.spreadsheet'    => 'spreadsheets',
        'application/vnd.google-apps.presentation'   => 'presentation',
        'application/vnd.google-apps.form'           => 'forms',
        'application/vnd.google-apps.drawing'        => 'drawings',
        'application/vnd.google-apps.jam'            => 'jam',
        'application/vnd.google-apps.site'           => 'site',
        'application/vnd.google-apps.map'            => 'maps',
        'application/vnd.google-apps.script'         => 'script',
        'application/vnd.google-apps.script+json'    => 'script',
        'application/vnd.google-apps.script+webapp'  => 'script',
        'application/vnd.google-apps.addon'          => 'addon',
        'application/vnd.google-apps.vid'            => 'vid',
    ];

    public const DEFAULT_EXPORT_FORMAT = [
        'document'     => 'docx',
        'presentation' => 'pptx',
        'spreadsheet'  => 'xlsx',
        'drawing'      => 'png',
        'script'       => 'json',
    ];

    public const NON_DOWNLOADABLE_TYPES = ['form', 'jam', 'map', 'addon', 'vid', 'site', 'shortcut'];

    public static function isExportAble($extension, $format)
    {
        $extension = strtolower(trim($extension));
        self::initializeMaps();

        if (!isset(self::CATEGORY_EXPORT_TYPE[$extension])) {
            return false;
        }

        return in_array($format, self::CATEGORY_EXPORT_TYPE[$extension]);
    }

    /**
     * Get MIME type from file extension
     *
     * @param string $extension File extension (with or without dot)
     * @return string MIME type
     */
    public static function getMimeType($extension = '')
    {
        if (empty($extension)) {
            return 'application/octet-stream';
        }

        // Remove dot if present
        $extension = ltrim(strtolower($extension), '.');

        self::initializeMaps();

        return self::$mimeMap[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get file extension(s) from MIME type
     *
     * @param string $mimeType MIME type
     * @param bool $getAll Return all extensions or just the primary one
     * @return string|array|null Extension(s) or null if not found
     */
    public static function getExtension($mimeType, $getAll = false)
    {
        if (empty($mimeType)) {
            return null;
        }

        $mimeType = strtolower(trim($mimeType));
        self::initializeMaps();

        if (!isset(self::$reverseMap[$mimeType])) {
            return null;
        }

        $extensions = self::$reverseMap[$mimeType];

        return $getAll ? $extensions : $extensions[0];
    }

    /**
     * Get MIME type from file path
     *
     * @param string $filePath File path or filename
     * @return string MIME type
     */
    public static function getMimeTypeFromPath($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return self::getMimeType($extension);
    }

    /**
     * Check if file extension is valid
     *
     * @param string $extension File extension
     * @return bool
     */
    public static function isValidExtension($extension)
    {
        $extension = ltrim(strtolower($extension), '.');
        self::initializeMaps();

        return isset(self::$mimeMap[$extension]);
    }

    /**
     * Check if MIME type is valid
     *
     * @param string $mimeType MIME type
     * @return bool
     */
    public static function isValidMimeType($mimeType)
    {
        $mimeType = strtolower(trim($mimeType));
        self::initializeMaps();

        return isset(self::$reverseMap[$mimeType]);
    }

    /**
     * Get category of MIME type (image, video, audio, document, etc.)
     *
     * @param string $mimeType MIME type
     * @return string Category name
     */
    public static function getCategory($mimeType)
    {
        $mimeType = strtolower(trim($mimeType));

        $categories = [
            'image'    => ['image/'],
            'video'    => ['video/'],
            'audio'    => ['audio/'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.ms-word',
                'application/vnd.openxmlformats-officedocument.wordprocessingml',
                'application/vnd.oasis.opendocument.text',
                'text/plain',
                'text/rtf',
            ],
            'spreadsheet' => [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml',
                'application/vnd.oasis.opendocument.spreadsheet',
                'text/csv',
            ],
            'presentation' => [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml',
                'application/vnd.oasis.opendocument.presentation',
            ],
            'archive' => [
                'application/zip',
                'application/x-rar',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
            ],
            'code' => [
                'text/html',
                'text/css',
                'application/javascript',
                'application/json',
                'application/xml',
                'text/x-',
            ],
            'folder' => [
                'folder',
            ],
        ];

        foreach ($categories as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($mimeType, $pattern) === 0) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /**
     * Get all extensions for a specific category
     *
     * @param string|array $categories Category name (image, video, audio, etc.)
     * @return array Extensions
     */
    public static function getExtensionsByCategory($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        self::initializeMaps();
        $extensions = [];

        foreach (self::$mimeMap as $ext => $mime) {
            if (in_array(self::getCategory($mime), $categories)) {
                $extensions[] = $ext;
            }
        }

        return $extensions;
    }
    /**
     * Get all MIME types for a specific category
     *
     * @param string|array $categories Category name (image, video, audio, etc.)
     * @return array MIME types
     */
    public static function getMimeTypeByCategory($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        self::initializeMaps();
        $mimeTypes = [];

        foreach (self::$mimeMap as $mime) {
            if (in_array(self::getCategory($mime), $categories)) {
                $mimeTypes[] = $mime;
            }
        }

        return $mimeTypes;
    }

    /**
     * Check if file is an image
     *
     * @param string $extensionOrMime File path/extension or MIME type
     * @return bool
     */
    public static function isImage($extensionOrMime)
    {
        $mime = strpos($extensionOrMime, '/') !== false
            ? $extensionOrMime
            : self::getMimeType($extensionOrMime);

        return strpos($mime, 'image/') === 0;
    }

    /**
     * Check if file is a video
     *
     * @param string $extensionOrMime File path/extension or MIME type
     * @return bool
     */
    public static function isVideo($extensionOrMime)
    {
        $mime = strpos($extensionOrMime, '/') !== false
            ? $extensionOrMime
            : self::getMimeType($extensionOrMime);

        return strpos($mime, 'video/') === 0;
    }

    /**
     * Check if file is audio
     *
     * @param string $extensionOrMime File path/extension or MIME type
     * @return bool
     */
    public static function isAudio($extensionOrMime)
    {
        $mime = strpos($extensionOrMime, '/') !== false
            ? $extensionOrMime
            : self::getMimeType($extensionOrMime);

        return strpos($mime, 'audio/') === 0;
    }

    /**
     * Get human-readable description of file type
     *
     * @param string $extensionOrMime Extension or MIME type
     * @return string Description
     */
    public static function getDescription($extensionOrMime)
    {
        $descriptions = [
            'image/jpeg'                                                              => 'JPEG Image',
            'image/png'                                                               => 'PNG Image',
            'image/gif'                                                               => 'GIF Image',
            'image/svg+xml'                                                           => 'SVG Vector Image',
            'application/pdf'                                                         => 'PDF Document',
            'application/msword'                                                      => 'Microsoft Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Microsoft Word Document (DOCX)',
            'application/vnd.ms-excel'                                                => 'Microsoft Excel Spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'Microsoft Excel Spreadsheet (XLSX)',
            'text/plain'                                                              => 'Text File',
            'text/html'                                                               => 'HTML Document',
            'application/json'                                                        => 'JSON File',
            'application/zip'                                                         => 'ZIP Archive',
            'video/mp4'                                                               => 'MP4 Video',
            'audio/mpeg'                                                              => 'MP3 Audio',
        ];

        $mime = strpos($extensionOrMime, '/') !== false
            ? $extensionOrMime
            : self::getMimeType($extensionOrMime);

        return $descriptions[$mime] ?? ucfirst(self::getCategory($mime)) . ' File';
    }

    /**
     * Get all available MIME types
     *
     * @return array All MIME types
     */
    public static function getAllMimeTypes()
    {
        self::initializeMaps();

        return array_values(array_unique(self::$mimeMap));
    }

    /**
     * Get all available extensions
     *
     * @return array All extensions
     */
    public static function getAllExtensions()
    {
        self::initializeMaps();

        return array_keys(self::$mimeMap);
    }

    /**
     * Initialize MIME type maps (lazy loading)
     */
    private static function initializeMaps()
    {
        if (self::$mimeMap !== null) {
            return;
        }

        self::$mimeMap = [
            // Common images
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'bmp'   => 'image/bmp',
            'webp'  => 'image/webp',
            // Common video
            'mp4'   => 'video/mp4',
            'mov'   => 'video/quicktime',
            'avi'   => 'video/x-msvideo',
            'mkv'   => 'video/x-matroska',
            'webm'  => 'video/webm',
            // Common audio
            'mp3'   => 'audio/mpeg',
            'wav'   => 'audio/wav',
            'ogg'   => 'audio/ogg',
            'flac'  => 'audio/flac',
            'aac'   => 'audio/aac',
            // Common documents
            'pdf'   => 'application/pdf',
            'doc'   => 'application/msword',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'   => 'application/vnd.ms-excel',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'   => 'application/vnd.ms-powerpoint',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt'   => 'text/plain',
            'csv'   => 'text/csv',
            'rtf'   => 'text/rtf',
            'odt'   => 'application/vnd.oasis.opendocument.text',
            'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp'   => 'application/vnd.oasis.opendocument.presentation',
            // Common archives
            'zip'   => 'application/zip',
            'rar'   => 'application/x-rar-compressed',
            '7z'    => 'application/x-7z-compressed',
            'tar'   => 'application/x-tar',
            'gz'    => 'application/gzip',
            // Google Apps MIME types
            'gdoc'     => 'application/vnd.google-apps.document',
            'gslides'  => 'application/vnd.google-apps.presentation',
            'gsheet'   => 'application/vnd.google-apps.spreadsheet',
            'gdraw'    => 'application/vnd.google-apps.drawing',
            'gtable'   => 'application/vnd.google-apps.fusiontable',
            'gform'    => 'application/vnd.google-apps.form',
            'shortcut' => 'application/vnd.google-apps.shortcut',
            // Add more as needed
            'folder' => 'folder',
        ];

        // Build reverse map for MIME type to extensions
        self::$reverseMap = [];
        foreach (self::$mimeMap as $ext => $mime) {
            if (!isset(self::$reverseMap[$mime])) {
                self::$reverseMap[$mime] = [];
            }
            self::$reverseMap[$mime][] = $ext;
        }
    }

    /**
     * Search for extensions or MIME types by keyword
     *
     * @param string $keyword Search keyword
     * @param string $searchIn 'extension', 'mime', or 'both'
     * @return array Results
     */
    public static function search($keyword, $searchIn = 'both')
    {
        self::initializeMaps();
        $keyword = strtolower($keyword);
        $results = [];

        if (in_array($searchIn, ['extension', 'both'])) {
            foreach (self::$mimeMap as $ext => $mime) {
                if (strpos($ext, $keyword) !== false) {
                    $results['extensions'][$ext] = $mime;
                }
            }
        }

        if (in_array($searchIn, ['mime', 'both'])) {
            foreach (self::$reverseMap as $mime => $extensions) {
                if (strpos($mime, $keyword) !== false) {
                    $results['mimes'][$mime] = $extensions;
                }
            }
        }

        return $results;
    }

    public static function getPreviewExtensions()
    {
        return [
            'csv','pdf','txt','ai','eps','odp','odt','doc','docx','docm',
            'ppt','pps','ppsx','ppsm','pptx','pptm','xls','xlsx','xlsm','rtf',
            'jpg','jpeg','gif','png','webp','mp4','m4v','ogg','ogv','webmv',
            'mp3','m4a','ogg','oga','wav','flac','paper','gdoc','gslides',
            'gsheet','mov','mkv','webm','svg',
        ];
    }

    public static function isPreviewable($extension)
    {
        $previewExtensions = self::getPreviewExtensions();

        return in_array(strtolower($extension), $previewExtensions);
    }

    public static function getThumbnailExtensions()
    {
        return [
            'csv','doc','docm','docx','ods','odt','pdf','rtf','xls','xlsm','xlsx',
            'odp','pps','ppsm','ppsx','ppt','pptm','pptx','3fr','ai','arw','bmp',
            'cr2','crw','dcs','dcr','dng','eps','erf','gif','heic','jpg','jpeg',
            'kdc','mef','mos','mrw','nef','nrw','orf','pef','png','psd','r3d',
            'raf','rw2','rwl','sketch','sr2','svg','svgz','tif','tiff','x3f',
            '3gp','3gpp','3gpp2','asf','avi','dv','flv','m2t','m4v','mkv','mov',
            'webm','mp4','mpeg','mpg','mts','oggtheora','ogv','rm','ts','vob',
            'wmv','paper','webp',
        ];
    }
    public static function isThumbnailable($extension)
    {
        $thumbnailExtensions = self::getThumbnailExtensions();

        return in_array(strtolower($extension), $thumbnailExtensions);
    }
}
