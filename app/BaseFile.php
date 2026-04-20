<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Google\Service\ServiceDriveDriveFile;
use CodeConfig\IGD\Models\Files;
use CodeConfig\IGD\Utils\Helpers;

use function is_array;

defined("ABSPATH") or die("Direct access is not allowed");

abstract class BaseFile
{
    public $id;
    public $fileKey;
    public $name;
    public $parentId;
    public $accountId;
    public $size;
    public $mimeType;
    public $extension;
    public $icon;
    public $thumbnail;
    public $metaData;
    public $isDir             = false;
    public $isStarred;
    public $isShared          = false;
    public $description;
    public $permissions     = [
        'canDownload'                           => false,
        'canPreview'                            => false,
        'canDelete'                             => false,
        'canTrash'                              => false,
        'canMove'                               => false,
        'canEdit'                               => false,
        'canShare'                              => false,
        'canRename'                             => false,
        'copyRequiresWriterPermission'          => false,
        'canChangeCopyRequiresWriterPermission' => false,
    ];

    public $media;
    public $additionalData  = [];
    public $lifeTime        = 0;

    private $data = [];

    public function __construct($data, bool $isVirtualFolder = false)
    {
        if (empty($data)) {
            return;
        }

        if ($data instanceof ServiceDriveDriveFile) {
            $this->fileKey = ccpigdGenerateKey($data->getId(), $data->getAccountId());
            $this->processFile($data, $isVirtualFolder);
            $this->data = $this->toArray();
        } elseif (is_array($data) && isset($data['id'], $data['accountId'])) {
            $this->fileKey = ccpigdGenerateKey($data['id'], $data['accountId']);
            $this->data    = $data;
            $this->processData();
        }
    }

    abstract public function processFile(ServiceDriveDriveFile $apiFile, bool $isVirtualFolder = false);
    abstract public function processData();

    protected function getProperty($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray()
    {

        return $this->dataForSave(false);
    }

    public function dataForSave($isSerialized = true)
    {
        $file = [
            'id'              => $this->id,
            'fileKey'         => $this->fileKey,
            'name'            => $this->name,
            'description'     => $this->description,
            'parentId'        => $this->parentId,
            'accountId'       => $this->accountId,
            'size'            => $this->size,
            'mimeType'        => $this->mimeType,
            'extension'       => $this->extension,
            'icon'            => $this->icon,
            'thumbnail'       => $this->thumbnail,
            'isDir'           => $this->isDir,
            'isShared'        => $this->isShared,
            'isStarred'       => $this->isStarred,
            'additionalData'  => $this->additionalData,
            'metaData'        => $this->metaData,
            'media'           => $this->media,
            'permissions'     => $this->permissions,
        ];

        return $file;

    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
    }

    public function hasThumbnail()
    {
        return !empty($this->thumbnail);
    }

    public function setThumbnail($value)
    {
        return $this->thumbnail = $value;
    }

    public function getShared()
    {
        return $this->isShared;
    }

    public function setShared($shared = true)
    {
        $this->isShared = $shared;

        return $this;
    }

    public function getStarred()
    {
        return $this->isStarred;
    }

    public function setStarred($isStarred)
    {
        return $this->isStarred = (bool) $isStarred;
    }

    public function getMedia($setting = null)
    {
        if (!empty($setting)) {
            if (isset($this->media[$setting])) {
                return $this->media[$setting];
            }

            return null;
        }

        return $this->media;
    }

    public function setMedia($media)
    {
        $this->media = $media;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function setParentId($parentId)
    {
        $this->parentId = is_array($parentId) ? reset($parentId) : $parentId;
    }

    public function getParentKey()
    {
        return ccpigdGenerateKey($this->parentId, $this->accountId);
    }

    public function hasParent()
    {
        return !empty($this->parentId);
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    public function getMimeType()
    {
        return $this->mimeType;
    }

    public function setMimeType($mimeType)
    {
        $this->mimeType = !empty($mimeType) ? $mimeType : '';
    }

    public function isDir()
    {
        return !empty($this->isDir);
    }

    public function isFile()
    {
        return !$this->isDir;
    }

    public function setIsDir($isDir)
    {
        $this->isDir = (bool) $isDir;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setSize($size)
    {
        $this->size = (int) $size;
    }

    public function getDescription()
    {
        return $this->description ?? '';
    }

    public function setDescription($description)
    {
        $this->description = !empty($description) ? trim($description) : '';
    }

    public function setPermissions(array $permissions)
    {
        $this->permissions = $permissions;
    }

    public function getPermission(string $key)
    {
        return $this->permissions[$key] ?? null;
    }
    public function setPermission(string $key, $value)
    {
        return $this->permissions[$key] = $value;
    }

    public function getFileKey()
    {
        return $this->fileKey;
    }
    public function setFileKey()
    {
        $this->fileKey = ccpigdGenerateKey($this->id, $this->accountId);
    }

    public function save()
    {
        Files::getInstance()->addFile($this->dataForSave());

        return $this->toArray();
    }

    public function setLifeTime($lifeTime)
    {
        $this->lifeTime = $lifeTime;
    }

    public function getLifeTime()
    {
        return $this->lifeTime;
    }

    // Additional data getters and setters
    public function getCanPreviewInCloud()
    {
        return $this->additionalData['canPreviewInCloud'] ?? false;
    }

    public function setCanPreviewInCloud($canPreviewInCloud)
    {
        return $this->additionalData['canPreviewInCloud'] = $canPreviewInCloud;
    }

    public function getCanEditInCloud()
    {
        return $this->additionalData['canEditInCloud'] ?? false;
    }

    public function setCanEditInCloud($canEditInCloud)
    {
        return $this->additionalData['canEditInCloud'] = $canEditInCloud;
    }

    public function getResourceKey()
    {
        return $this->additionalData['resourceKey'] ?? null;
    }

    public function setResourceKey($resourceKey)
    {
        $this->additionalData['resourceKey'] = $resourceKey;

        return $this;
    }

    public function hasResourceKey()
    {
        return !empty($this->additionalData['resourceKey'] ?? false);
    }

    public function setExportLinks($exportLinks)
    {
        return $this->additionalData['exportLinks'] = $exportLinks;
    }
    public function getCreatedTime()
    {
        return $this->additionalData['createdTime'] ?? '';
    }

    public function setCreatedTime($createdTime)
    {
        $this->additionalData['createdTime'] = $createdTime;
    }

    public function getCreatedTimeFormatted($isShort = true)
    {
        return empty($this->additionalData['createdTime']) ? '' : Helpers::formatDateTime(strtotime($this->additionalData['createdTime']), $isShort);
    }

    public function getLastEdited()
    {
        return $this->additionalData['lastEdited'] ?? '';
    }

    public function setLastEdited($lastEdited)
    {
        $this->additionalData['lastEdited'] = $lastEdited;
    }

    public function getLastEditedFormatted($isShort = true)
    {
        return empty($this->additionalData['lastEdited']) ? '' : Helpers::formatDateTime(strtotime($this->additionalData['lastEdited']), $isShort);
    }

    public function isVirtualFolder()
    {
        return $this->additionalData['isVirtualFolder'] ?? false;
    }

    public function setVirtualFolder($isVirtualFolder)
    {
        $this->additionalData['isVirtualFolder'] = $isVirtualFolder;
    }

    public function getOwnedByMe()
    {
        return $this->additionalData['ownedByMe'] ?? false;
    }

    public function setOwnedByMe($isOwnedByMe = true)
    {
        $this->additionalData['ownedByMe'] = $isOwnedByMe;

        return $this;
    }

    public function getTrashed()
    {
        return $this->additionalData['trashed'] ?? false;
    }

    public function setTrashed($isTrashed)
    {
        return $this->additionalData['trashed'] = (bool) $isTrashed;
    }

    public function getAdditionalData()
    {
        return $this->additionalData;
    }

    public function getDriveId()
    {
        return $this->additionalData['driveId'] ?? null;
    }

    public function setDriveId($driveId)
    {
        $this->additionalData['driveId'] = $driveId;
    }

    public function getBaseName()
    {
        return $this->additionalData['baseName'] ?? $this->baseName ?? pathinfo($this->name, PATHINFO_FILENAME);
    }

    public function setBaseName($baseName)
    {
        $this->additionalData['baseName'] = $baseName;
    }

    public function getShortcutDetails()
    {
        return $this->additionalData['shortcutDetails'] ?? null;
    }

    public function setShortcutDetails(array $shortcutDetails)
    {
        $this->additionalData['shortcutDetails'] = $shortcutDetails;
    }

}
