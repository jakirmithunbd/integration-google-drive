<?php

namespace CodeConfig\IGD\Pages;

defined('ABSPATH') || exit('No direct script access allowed');

class AdminPages
{
    private const SUB_MENU_PAGES = [
        [
            'id'    => 'file_browser',
            'menu'  => 'File Manager',
            'slug'  => CCPIGD_SLUG . '#/file-browser/my-drive',
        ],
        [
            'id'    => 'module_builder',
            'menu'  => 'Module Builder',
            'slug'  => CCPIGD_SLUG . '#/module-builder',
        ],
        [
            'id'    => 'settings',
            'menu'  => 'Settings',
            'slug'  => CCPIGD_SLUG . '#/settings/accounts',
        ]
    ];

    /**
     * Adds the top level menu item for the Integration Google Drive settings page.
     *
     * @since 1.0.0
     */
    public static function adminMenu()
    {
        $isMenuAdded = false;
        foreach (self::SUB_MENU_PAGES as $page) {
            if (empty($page['id']) || empty($page['menu']) || empty($page['slug'])) {
                continue;
            }

            $page_id = $page['id'];
            if (!ccpigdHasUserAccessPage($page_id)) {
                continue;
            }

            if (!$isMenuAdded) {
                self::addMenuPage($page['menu'], $page['slug']);
                $isMenuAdded = true;
            } else {
                self::addSubMenuPage($page['menu'], $page['slug']);
            }
        }
    }

    public static function adminPage()
    {
        wp_enqueue_style('ccpigd-admin');
        wp_enqueue_script('ccpigd-file-browser');
        echo '<div id="ccpigd-admin" class="ccpigd-admin ccpigd-top-level-wrapper"></div>';
    }

    private static function addMenuPage($menu, $slug)
    {
        add_menu_page(
            'Integration Google Drive',
            'Google Drive',
            'read',
            CCPIGD_SLUG,
            [self::class, 'adminPage'],
            CCPIGD_ASSETS . '/images/drive.png',
            10
        );

        self::addSubMenuPage($menu, $slug);
        remove_submenu_page(CCPIGD_SLUG, CCPIGD_SLUG);
    }

    private static function addSubMenuPage($menu, $slug)
    {
        add_submenu_page(
            CCPIGD_SLUG,
            "$menu - Integration Google Drive",
            $menu,
            'read',
            $slug,
            '__return_null'
        );
    }
}
