<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Google\Service\ServiceDriveDriveFile;
use CodeConfig\IGD\Google\Service\ServiceDriveDriveFileCapabilities;
use CodeConfig\IGD\Utils\Helpers;
use WP_Error;

class File extends BaseFile
{
    /**
     *  The API file object
     *
     * @var ServiceDriveDriveFile
     */
    private $apiFile;

    public function processFile(ServiceDriveDriveFile $apiFile, bool $virtualFolder = false)
    {
        if (!$apiFile instanceof ServiceDriveDriveFile) {
            return new WP_Error(403, __('Google response is not a valid Entry.', 'integration-google-drive'));
        }

        $this->apiFile = $apiFile;

        $this->setVirtualFolder($virtualFolder);

        $this->setMetadata($apiFile);

        $this->setShortcutDetailsAttributes($apiFile);

        $this->setPreviewAndPermissions($apiFile);

        $this->setIconAndThumbnail($apiFile);

        $this->setMediaData($apiFile);
    }

    public function processData()
    {
        $this->id                   = $this->getProperty('id');
        $this->fileKey              = $this->getProperty('fileKey');
        $this->name                 = $this->getProperty('name');
        $this->description          = $this->getProperty('description');
        $this->parentId             = $this->getProperty('parentId');
        $this->accountId            = $this->getProperty('accountId');
        $this->size                 = $this->getProperty('size');
        $this->mimeType             = $this->getProperty('mimeType');
        $this->extension            = $this->getProperty('extension');
        $this->icon                 = $this->getProperty('icon');
        $this->thumbnail            = $this->getProperty('thumbnail');
        $this->additionalData       = $this->getProperty('additionalData');
        $this->metaData             = $this->getProperty('metaData');
        $this->isDir                = $this->getProperty('isDir');
        $this->isShared             = $this->getProperty('isShared');
        $this->isStarred            = $this->getProperty('isStarred');
        $this->media                = $this->getProperty('media');
        $this->permissions          = $this->getProperty('permissions');
    }

    public function editSupportedInCloud()
    {
        switch ($this->getMimeType()) {
            case 'application/msword':
            case 'application/vnd.ms-excel':
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.ms-excel.sheet.macroenabled.12':
            case 'application/vnd.google-apps.drawing':
            case 'application/vnd.google-apps.document':
            case 'application/vnd.google-apps.spreadsheet':
            case 'application/vnd.google-apps.presentation':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.openxmlformats-officedocument.presentational.slideshow':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                return true;

            default:
                return false;
        }
    }

    public function hasPermission($permission_role = ['reader', 'writer'])
    {
        $workspaceDomain = Helpers::getSetting('advanced.googleWorkspaceDomain', '');
        $users           = $this->permissions['users'] ?? [];

        if (empty($users)) {
            return false;
        }

        $type = empty($workspaceDomain) ? 'anyone' : 'domain';
        foreach ($users as $user) {
            if (
                $user['type'] == $type                    &&
                in_array($user['role'], $permission_role) &&
                (empty($workspaceDomain) || $user['domain'] == $workspaceDomain)
            ) {
                return true;
            }
        }

        return false;
    }

    // ========================== Private methods ==========================
    private function setMetadata(ServiceDriveDriveFile $apiFile)
    {
        $this->setId($apiFile->getId());
        $this->setName($apiFile->getName());
        $this->setDriveId($apiFile->getDriveId());
        $this->setStarred($apiFile->getStarred());
        $this->setAccountId($apiFile->getAccountId());

        if (!empty($apiFile->getFileExtension())) {
            $this->setExtension(strtolower($apiFile->getFileExtension()));
        }

        if ('application/vnd.google-apps.shortcut' === $apiFile->getMimeType()) {
            $this->setExtension('shortcut');
        } elseif (empty($apiFile->getFileExtension())) {
            $mimeType  = $apiFile->getMimeType();
            $extension = ccpigdGetExtensionByMimeType($mimeType);
            $this->setExtension($extension ?: 'unknown');
        }

        $this->setMimeType($apiFile->getMimeType());

        if (empty($this->extension)) {
            $this->setBasename($this->getName());
        } else {
            $this->setBasename(str_ireplace('.' . $this->getExtension(), '', $apiFile->getName()));
        }

        $this->setTrashed($apiFile->getTrashed());
        $this->setIsDir('application/vnd.google-apps.folder' === $apiFile->getMimeType());
        $this->setSize($this->isDir() ? 0 : $apiFile->getSize());
        $this->setDescription($apiFile->getDescription());
        $this->setLastEdited($apiFile->getModifiedTime());
        $this->setCreatedTime($apiFile->getCreatedTime());

        $this->setOwnedByMe($this->isOwnedByMe($apiFile));
        $this->setShared($this->isShared($apiFile));

        $this->setParentId($apiFile->getParents());
        $this->setParentFolder($apiFile);
        $this->setResourceKey($apiFile->getResourceKey());
    }

    private function setPreviewAndPermissions(ServiceDriveDriveFile $apiFile)
    {
        $capabilities = $apiFile->getCapabilities();
        if (empty($capabilities)) {
            return;
        }
        $this->setCapabilities($capabilities);

        $permissions = $this->getPermissions($apiFile);
        $this->setPermissions($permissions);
    }

    private function setIconAndThumbnail(ServiceDriveDriveFile $apiFile)
    {
        $icon = $apiFile->getIconLink();
        if (!empty($icon)) {
            $this->setIcon(str_replace(['/16/'], ['/128/'], $icon));
        }
        $thumbnail = $apiFile->getThumbnailLink();
        $this->setThumbnail($thumbnail);
    }

    private function setMediaData(ServiceDriveDriveFile $apiFile)
    {
        $mediaData = [];

        $imageMetadata = $apiFile->getImageMediaMetadata();
        $videoMetadata = $apiFile->getVideoMediaMetadata();

        if (!empty($imageMetadata)) {
            $mediaData = $this->getImageMetadata($imageMetadata);
        } elseif (!empty($videoMetadata)) {
            $mediaData = $this->getVideoMetadata($videoMetadata);
        }

        $this->setMedia($mediaData);
    }

    private function setParentFolder(ServiceDriveDriveFile $apiFile)
    {
        if (empty($apiFile->getParents()) && !$this->isVirtualFolder()) {
            if ($apiFile->getDriveId() === $apiFile->getId()) {
                $this->setParentId('shared-drives');
                $this->setVirtualFolder('shared-drive');
            } elseif ($apiFile->getShared() && !$apiFile->getOwnedByMe()) {
                $this->setParentId('shared');
            } elseif (!empty($apiFile->getSharedWithMeTime()) && !$apiFile->getOwnedByMe()) {
                $this->setParentId('shared');
            } elseif (!$apiFile->getShared() && $apiFile->getOwnedByMe()) {
                $this->setParentId('computers');
                $this->setVirtualFolder('computer');
            } else {
                return new WP_Error(403, __('Found an item without a parent folder (orphaned):', 'integration-google-drive'));
            }
        }
    }

    private function isOwnedByMe(ServiceDriveDriveFile $apiFile)
    {
        return ('mydrive' !== $apiFile->getDriveId()) ? true : $apiFile->getOwnedByMe();
    }

    /**
     * Checks if a file is shared.
     *
     * Checks if a file is shared with the current user.
     *
     * @param ServiceDriveDriveFile|null $apiFile The file to be checked. If empty, uses the current file.
     * @return bool True if the file is shared, false if not.
     */
    private function isShared($apiFile = null)
    {
        if (empty($apiFile)) {
            $apiFile = $this->apiFile;
        }

        return $apiFile->getShared();
    }

    private function setCapabilities(ServiceDriveDriveFileCapabilities $capabilities)
    {
        if (!$capabilities instanceof ServiceDriveDriveFileCapabilities) {
            return;
        }

        $this->setCanEditInCloud($capabilities->getCanEdit() && $this->editSupportedInCloud());
        $this->setPermission('canEdit', $capabilities->getCanEdit());
        $this->setPermission('canRename', $capabilities->getCanRename());
        $this->setPermission('canShare', $capabilities->getCanShare());
        $this->setPermission('canDelete', $capabilities->getCanDelete());
        $this->setPermission('canTrash', $capabilities->getCanTrash());
        $this->setPermission('canMove', $capabilities->getCanMoveItemWithinDrive());
        $this->setPermission('canChangeCopyRequiresWriterPermission', $capabilities->getCanChangeCopyRequiresWriterPermission() ?? false);
    }

    private function getPermissions(ServiceDriveDriveFile $apiFile)
    {
        $users          = [];
        $apiPermissions = $apiFile->getPermissions();

        if (count($apiPermissions) > 0) {
            foreach ($apiPermissions as $permission) {
                $users[$permission->getId()] = [
                    'type'   => $permission->getType(),
                    'role'   => $permission->getRole(),
                    'domain' => $permission->getDomain()
                ];
            }
        }

        return [
            'users'                                 => $users,
            'canPreview'                            => true,
            'canDownload'                           => true,
            'canAdd'                                => $apiFile->getOwnedByMe(),
            'canMove'                               => $apiFile->getOwnedByMe(),
            'canShare'                              => $apiFile->getOwnedByMe(),
            'canTrash'                              => $apiFile->getOwnedByMe(),
            'canRename'                             => $apiFile->getOwnedByMe(),
            'canDelete'                             => $apiFile->getOwnedByMe(),
            'copyRequiresWriterPermission'          => $apiFile->getCopyRequiresWriterPermission(),
            'canChangeCopyRequiresWriterPermission' => $this->getPermission('canChangeCopyRequiresWriterPermission'),
        ];
    }

    private function setShortcutDetailsAttributes($apiFile)
    {
        $shortcutDetails = $apiFile->getShortcutDetails();

        if (!empty($shortcutDetails)) {
            $this->setShortcutDetails([
                'targetId'          => $shortcutDetails->getTargetId(),
                'targetMimeType'    => $shortcutDetails->getTargetMimeType(),
                'targetResourceKey' => $shortcutDetails->getTargetResourceKey()
            ]);
        }
    }

    private function getImageMetadata($imageMetadata)
    {
        $mediaData = [];
        if (empty($imageMetadata->rotation) || 0 === $imageMetadata->getRotation() || 2 === $imageMetadata->getRotation()) {
            $mediaData['width']  = $imageMetadata->getWidth();
            $mediaData['height'] = $imageMetadata->getHeight();
        } else {
            $mediaData['width']  = $imageMetadata->getHeight();
            $mediaData['height'] = $imageMetadata->getWidth();
        }

        if (!empty($imageMetadata->time)) {
            $dtime = \DateTime::createFromFormat('Y:m:d H:i:s', $imageMetadata->getTime(), new \DateTimeZone('UTC'));

            if ($dtime) {
                $mediaData['time'] = $dtime->getTimestamp();
            }
        }

        return $mediaData;
    }

    private function getVideoMetadata($videoMetadata)
    {
        return [
            'width'    => $videoMetadata->getWidth(),
            'height'   => $videoMetadata->getHeight(),
            'duration' => $videoMetadata->getDurationMillis(),
        ];
    }
}
