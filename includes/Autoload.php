<?php

namespace CodeConfig\IGD;

defined('ABSPATH') or exit('No direct script access allowed');

class Autoload
{
    public static function register()
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * PSR-4 style autoloader for the CodeConfig\IGD namespace.
     *
     * @param string $class Fully qualified class name.
     */
    private static function loadClass($class)
    {
        $prefixes = self::getAutoloadPaths();

        foreach ($prefixes as $prefix => $dirs) {
            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $filePath      = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            foreach ($dirs as $dir) {
                $fullPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filePath;
                if (is_file($fullPath)) {
                    require_once $fullPath;

                    return;
                }
            }
        }
    }

    /**
     * Maps namespace prefixes to their corresponding base directories.
     *
     * @return array
     */
    private static function getAutoloadPaths()
    {
        return [
            // Order matters: more specific namespaces first
            'CodeConfig\\IGD\\ZipStream\\' => [CCPIGD_VENDORS . '/ZipStream'],
            'CodeConfig\\IGD\\Google\\'    => [CCPIGD_VENDORS . '/Google'],
            'CodeConfig\\IGD\\Models\\'    => [CCPIGD_MODELS],
            'CodeConfig\\IGD\\App\\'       => [CCPIGD_APP],
            'CodeConfig\\IGD\\'            => [CCPIGD_INCLUDES],
        ];
    }
}
