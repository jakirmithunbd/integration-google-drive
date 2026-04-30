<?php

namespace CodeConfig\IGD\API;

use CodeConfig\IGD\API\Controllers\Account;
use CodeConfig\IGD\API\Controllers\File;
use CodeConfig\IGD\API\Controllers\Folder;
use CodeConfig\IGD\API\Controllers\MediaLibrary;
use CodeConfig\IGD\API\Controllers\Notices;
use CodeConfig\IGD\API\Controllers\Settings;
use CodeConfig\IGD\API\Controllers\Shortcode;
use CodeConfig\IGD\API\Controllers\Users;
use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') || exit('No direct script access allowed');

class ApiRegistry
{
    use Singleton;
    private array $controllers = [];

    public function doHooks()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        $this->register_controllers();
    }

    private function register_controllers(): void
    {
        $this->controllers = [
            'account'       => new Account(),
            'file'          => new File(),
            'folder'        => new Folder(),
            'settings'      => new Settings(),
            'shortcode'     => new Shortcode(),
            'notice'        => new Notices(),
            'users'         => new Users(),
            'media-library' => new MediaLibrary(),
        ];
    }
    public function registerRoutes(): void
    {
        foreach ($this->controllers as $controller) {
            $controller->register_routes();
        }
    }

    public function get_controller(string $name): ?BaseController
    {
        return $this->controllers[$name] ?? null;
    }
}
