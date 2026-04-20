<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Google\Client as GoogleClient;
use CodeConfig\IGD\Google\Service\ServiceDrive;

defined('ABSPATH') || exit('No direct script access allowed');

abstract class API
{
    /**
     * Google Client instance.
     *
     * @var GoogleClient
     */
    protected $client;

    /**
     * ServiceDrive instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDrive
     */
    protected $service;

    /**
     * About API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveAboutResource
     */
    protected $about;

    /**
     * Changes API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveChangesResource
     */
    protected $changes;

    /**
     * Channels API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveChannelsResource
     */
    protected $channels;

    /**
     * Comments API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveCommentsResource
     */
    protected $comments;

    /**
     * Files API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveFilesResource
     */
    protected $files;

    /**
     * Permissions API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDrivePermissionsResource
     */
    protected $permissions;

    /**
     * Replies API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveRepliesResource
     */
    protected $replies;

    /**
     * Revisions API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveRevisionsResource
     */
    protected $revisions;

    /**
     * Drives API instance.
     *
     * @var \CodeConfig\IGD\Google\Service\ServiceDriveDrivesResource
     */
    protected $drives;

    /**
     * Service name.
     *
     * @var string
     */
    protected $serviceName;


    public function __construct(GoogleClient $client)
    {
        $this->init($client);
    }

    // ================= Protected methods ==================

    protected function getGoogleService()
    {
        return $this->service;
    }

    protected function getGoogleClient()
    {
        return $this->client;
    }

    protected function getGoogleAbout()
    {
        return $this->about;
    }

    protected function getGoogleDrives()
    {
        return $this->drives;
    }

    protected function getGoogleFiles()
    {
        return $this->files;
    }

    /**
     * Initializes the API service with the given client
     *
     * @param GoogleClient $client The client to use for the API service
     *
     * @return void
     */
    protected function init(GoogleClient $client)
    {
        $this->client  = $client;
        $this->service = new ServiceDrive($this->client);

        $this->drives      = $this->service->drives;
        $this->about       = $this->service->about;
        $this->changes     = $this->service->changes;
        $this->channels    = $this->service->channels;
        $this->files       = $this->service->files;
        $this->comments    = $this->service->comments;
        $this->permissions = $this->service->permissions;
        $this->replies     = $this->service->replies;
        $this->revisions   = $this->service->revisions;
        $this->serviceName = $this->service->serviceName;

    }
}
