<?php

namespace CodeConfig\IGD\Integrations;

use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Integration;
use CodeConfig\IGD\Utils\Helpers;
use CodeConfig\IGD\Models\Shortcode;

defined('ABSPATH') || exit('No direct script access allowed');

abstract class BaseIntegration
{
    protected $id;
    protected $title;
    protected $isInit       = false;
    protected $integrations = [];
    protected $isActive     = null;
    public function __construct($id, $title)
    {
        $this->id    = $id;
        $this->title = $title;

        Integration::getInstance()->register($this->id, [
            'title'    => $this->title,
            'callback' => [$this, 'init'],
        ]);

        $this->integrations = Helpers::getSetting('integrations', []);

        if ($this->isActive()) {
            $this->init($this->id, $this->integrations[$this->id] ?? []);
        }
        // $this->ensureActive();
    }

    abstract public function init(string $id, array $integration): void;

    // protected function ensureActive(): bool
    // {
    //     if (!$this->isActive()) {
    //         return false;
    //     }

    //     if (!$this->isInit) {
    //         $integration = $this->integrations[$this->id] ?? [];
    //         $this->init($this->id, $integration);
    //         $this->isInit = true;
    //     }

    //     return true;
    // }

    /**
     * Check if integration is active
     */
    public function isActive(): bool
    {
        // Use lazy loading - only check once
        if ($this->isActive === null) {
            $activeIntegrations = $this->integrations['activeIntegrations'] ?? [];
            $this->isActive     = in_array($this->id, $activeIntegrations, true);
        }

        return $this->isActive;
    }

    /**
     * Get integration setting
     */
    protected function getSetting(string $key, $default = null)
    {
        return $this->integrations[$this->id][$key] ?? $default;
    }

    /**
     * Get integration ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get integration title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Check if integration is initialized
     */
    public function isInitialized(): bool
    {
        return $this->isInit;
    }

    protected function getGoogleDriveFolder(): array
    {
        $folder = App::getInstance()->getFolders();

        if (is_wp_error($folder) || empty($folder)) {
            return [];
        }

        $options = [];
        foreach ($folder as $file) {
            $options[$file['fileKey']] = $file['name'];
        }

        return $options;
    }

    public static function getExistingFileUploaderShortcodes()
    {
        $shortcodes = Shortcode::getInstance()->getAll(['type' => 'file-uploader', 'perPage' => 1000]);

        $options = [
            '' => __('Select a shortcode', 'integration-google-drive'),
        ];

        if (! empty($shortcodes)) {
            foreach ($shortcodes as $shortcode) {
                $options[ $shortcode['id'] ] = sprintf(
                    '%s (%s)',
                    ! empty($shortcode['title']) ? $shortcode['title'] : $shortcode['id'],
                    $shortcode['id']
                );
            }
        }

        return $options;
    }
}
