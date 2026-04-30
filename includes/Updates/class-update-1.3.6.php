<?php

namespace CodeConfig\IGD\Updates;

use CodeConfig\IGD\Utils\Helpers;

defined('ABSPATH') || exit;

class Update_1_3_6 extends Updater
{
    public const VERSION = '1.3.6';

    public function run_update()
    {
        try {
            $this->migrationSettings();

            return self::VERSION;
        } catch (\Exception $e) {
            return new \WP_Error('update_failed', __('Update to version 1.3.6 failed: ', 'integration-google-drive') . $e->getMessage());
        }
    }

    private function migrationSettings()
    {
        $settings = get_option(CCPIGD_OPTIONS_NAME, []);
        Helpers::updateSettings($settings);
    }
}
