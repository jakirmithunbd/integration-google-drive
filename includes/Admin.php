<?php

namespace CodeConfig\IGD;

defined('ABSPATH') || exit('No direct script access allowed');

use CodeConfig\IGD\Utils\Singleton;

class Admin
{
    use Singleton;

    private function doHooks()
    {
        add_action('admin_menu', ['CodeConfig\IGD\Pages\AdminPages', 'adminMenu']);
        add_filter('admin_body_class', [$this, 'adminBodyClasses']);
    }

    public function adminBodyClasses($classes)
    {
        $screen = get_current_screen();

        if (!$screen) {
            return $classes;
        }

        $classes .= ' ccpigd-admin';

        if ($screen->id === 'toplevel_page_' . CCPIGD_SLUG) {
            $classes .= ' ccpigd-file-browser-page';
        }

        if ($screen->id === 'google-drive_page_' . CCPIGD_SLUG . '#/settings/accounts') {
            $classes .= ' ccpigd-settings-page';
        }

        if ($screen->id === 'google-drive_page_' . CCPIGD_SLUG . '#/module-builder') {
            $classes .= ' ccpigd-module-builder-page';
        }

        return $classes;
    }
}
