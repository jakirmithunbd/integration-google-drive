<?php

namespace CodeConfig\IGD;

defined('ABSPATH') || exit;

use CodeConfig\IGD\Utils\Singleton;

class Update
{
    use Singleton;

    /**
     * List of available update versions.
     * Keep versions in ascending order.
     *
     * @var array
     */
    private static $updateList = [
        '1.1.0',
        '1.2.0',
        '1.3.0',
        '1.3.6',
        '1.4.0',
    ];

    /**
     * Run updates if required.
     *
     * @return string|false
     */
    public function maybeUpdate(): string|false
    {
        $installedVersion = get_option('ccpigd_version');

        // First install — just store current version.
        if (!$installedVersion) {
            update_option('ccpigd_version', CCPIGD_VERSION);

            return false;
        }

        // No update required.
        if (version_compare($installedVersion, CCPIGD_VERSION, '>=')) {
            return false;
        }

        return $this->performUpdates($installedVersion);
    }

    /**
     * Perform all necessary updates.
     *
     * @param string $installedVersion
     * @return string
     */
    private function performUpdates(string $installedVersion): string
    {
        usort(self::$updateList, 'version_compare');

        $currentVersion = $installedVersion;

        foreach (self::$updateList as $version) {

            if (
                version_compare($version, $currentVersion, '>') &&
                version_compare($version, CCPIGD_VERSION, '<=')
            ) {
                $filePath = CCPIGD_UPDATES . "/class-update-{$version}.php";

                if (!file_exists($filePath)) {
                    continue;
                }

                include_once $filePath;

                $className = "CodeConfig\\IGD\\Updates\\Update_" . str_replace('.', '_', $version);

                if (!class_exists($className)) {
                    continue;
                }

                $instance = $className::getInstance();

                if (!method_exists($instance, 'run_update')) {
                    continue;
                }

                if ($timestamp = wp_next_scheduled('ccpigd_cron_fire')) {
                    wp_unschedule_event($timestamp, 'ccpigd_cron_fire');
                }

                $result = $instance->run_update();

                if (is_wp_error($result) || version_compare($result, $version, '!=')) {
                    break;
                }

                $currentVersion = $version;
                update_option('ccpigd_version', $currentVersion);
            }
        }

        if (version_compare($currentVersion, CCPIGD_VERSION, '<')) {
            update_option('ccpigd_version', CCPIGD_VERSION);
        }

        return $currentVersion;
    }
}
