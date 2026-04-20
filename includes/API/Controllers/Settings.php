<?php

namespace CodeConfig\IGD\API\Controllers;

use CodeConfig\IGD\API\BaseController;
use CodeConfig\IGD\Utils\Helpers;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit('No direct script access allowed');

class Settings extends BaseController
{
    public function __construct()
    {
        parent::__construct('ccpigd/v1', 'settings');
    }

    public function register_routes(): void
    {
        // Main settings endpoint
        register_rest_route($this->namespace, $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->getCollectionParams(),
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'manageSettingsPermission'],
                'args'                => $this->getUpdateParams(),
            ]
        ]);

        // Get specific setting
        register_rest_route($this->namespace, "{$this->rest_base}/(?P<key>accounts|advanced|appearance|integrations|userAccess|synchronization|tools)", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getSetting'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'key' => [
                    'required'    => true,
                    'enum'        => ['accounts', 'advanced', 'appearance', 'integrations', 'userAccess', 'synchronization', 'tools'],
                    'description' => 'Setting key to retrieve',
                ],
            ],
        ]);

        // Bulk operations
        register_rest_route($this->namespace, "{$this->rest_base}/bulk", [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'bulkUpdateSettings'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'operations' => [
                    'required'    => true,
                    'type'        => 'array',
                    'description' => 'Array of operations to perform',
                ],
            ],

        ]);

        // Reset to defaults
        register_rest_route($this->namespace, "{$this->rest_base}/reset", [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'resetSettings'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'keys' => [
                    'required'    => false,
                    'type'        => 'array',
                    'description' => 'Specific keys to reset (empty = reset all)',
                ],
            ],
        ]);

        // Validate settings
        register_rest_route($this->namespace, "{$this->rest_base}/validate", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'validateSettingsEndpoint'],
            'permission_callback' => [$this, 'manageSettingsPermission'],
            'args'                => [
                'settings' => [
                    'required'    => true,
                    'type'        => 'object',
                    'description' => 'Settings to validate',
                ],
            ],
        ]);
    }

    /**
     * Get all settings
     */
    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $defaults         = $this->getDefaultSettings();
            $current_settings = get_option(CCPIGD_OPTIONS_NAME, $defaults);

            // Merge with defaults to ensure all keys exist
            [$merged_settings, $diffKeys] = $this->mergeWithDefaults($current_settings, $defaults);

            if (!empty($merged_settings['accounts']['appClientSecret'])) {
                $decoded_secret = Helpers::decode($merged_settings['accounts']['appClientSecret']);

                $musk                                           = array_fill(0, strlen($decoded_secret) - 4, '*');
                $merged_settings['accounts']['appClientSecret'] = implode('', $musk) . substr($decoded_secret, - 4);
            }

            return $this->successResponse([
                'defaults' => $defaults,
                'current'  => $merged_settings,
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve settings');
        }
    }

    /**
     * Get a specific setting by key
     */
    public function getSetting(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $key              = $request->get_param('key');
            $defaults         = $this->getDefaultSettings();
            $current_settings = get_option(CCPIGD_OPTIONS_NAME, $defaults);

            if (!array_key_exists($key, $current_settings)) {
                return $this->errorResponse("Setting key '{$key}' not found", self::HTTP_NOT_FOUND);
            }

            if (!empty($current_settings[$key]['appClientSecret'])) {
                $decoded_secret = Helpers::decode($current_settings[$key]['appClientSecret']);

                $musk                                      = array_fill(0, strlen($decoded_secret) - 4, '*');
                $current_settings[$key]['appClientSecret'] = implode('', $musk) . substr($decoded_secret, - 4);
            }

            return $this->successResponse([
                'key'     => $key,
                'value'   => $current_settings[$key],
                'default' => $defaults[$key] ?? null,
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to retrieve setting');
        }
    }

    /**
     * Update settings
     */
    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $new_settings = $request->get_param('settings');

            if (empty($new_settings) || !is_array($new_settings)) {
                return $this->errorResponse('Settings parameter is required and must be an array', self::HTTP_BAD_REQUEST);
            }

            // Get current settings
            $defaults         = $this->getDefaultSettings();
            $current_settings = get_option(CCPIGD_OPTIONS_NAME, $defaults);

            // Validate and sanitize new settings
            $validated_settings = $this->validateAndSanitizeSettings($new_settings, $defaults);

            if (!empty($validated_settings['accounts']['appClientSecret'])) {
                if (strpos($validated_settings['accounts']['appClientSecret'], '****') !== false) {
                    $validated_settings['accounts']['appClientSecret'] = $current_settings['accounts']['appClientSecret'] ?? '';
                } else {
                    $validated_settings['accounts']['appClientSecret'] = Helpers::encode($validated_settings['accounts']['appClientSecret']);
                }
            }

            // Merge with current settings (deep merge)
            [$updated_settings, $diffKeys] = $this->deepMerge($current_settings, $validated_settings);

            // Update option
            $result = update_option(CCPIGD_OPTIONS_NAME, $updated_settings);

            // Check if update was successful or value was unchanged
            $verification = get_option(CCPIGD_OPTIONS_NAME);
            if ($verification !== $updated_settings && !$result) {
                return $this->errorResponse('Failed to update settings', self::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (!empty($updated_settings['accounts']['appClientSecret'])) {
                $decoded_secret = Helpers::decode($updated_settings['accounts']['appClientSecret']);

                $musk                                            = array_fill(0, strlen($decoded_secret) - 4, '*');
                $updated_settings['accounts']['appClientSecret'] = implode('', $musk) . substr($decoded_secret, - 4);
            }

            return $this->successResponse([
                'settings' => $updated_settings,
                'updated'  => $diffKeys,
            ], 'Settings updated successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), self::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to update settings');
        }
    }

    /**
     * Bulk update settings with multiple operations
     */
    public function bulkUpdateSettings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $operations = $request->get_param('operations');

            if (empty($operations) || !is_array($operations)) {
                return $this->errorResponse('Operations parameter is required and must be an array', self::HTTP_BAD_REQUEST);
            }

            $defaults          = $this->getDefaultSettings();
            $current_settings  = get_option(CCPIGD_OPTIONS_NAME, $defaults);
            $results           = [];
            $errors            = [];
            $updated_settings  = $current_settings;

            foreach ($operations as $index => $operation) {
                try {
                    $result    = $this->processBulkOperation($operation, $current_settings, $defaults);
                    $results[] = $result;

                    // Update current settings for next operation
                    if ($result['status'] === 'success' && isset($result['data'])) {
                        [$updated_settings, $diffKeys] = $this->deepMerge($current_settings, $result['data']);

                        if (!empty($updated_settings['accounts']['appClientSecret'])) {
                            if (strpos($updated_settings['accounts']['appClientSecret'], '****') !== false) {
                                $updated_settings['accounts']['appClientSecret'] = $current_settings['accounts']['appClientSecret'];
                            } else {
                                $updated_settings['accounts']['appClientSecret'] = Helpers::encode($updated_settings['accounts']['appClientSecret']);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'index'     => $index,
                        'operation' => $operation,
                        'error'     => $e->getMessage(),
                    ];
                }
            }

            // Save all changes if no errors
            if (empty($errors)) {
                update_option(CCPIGD_OPTIONS_NAME, $updated_settings);

                return $this->successResponse([
                    'results'  => $results,
                    'settings' => $updated_settings,
                ], 'All bulk operations completed successfully');
            }

            return $this->errorResponse('Some bulk operations failed', self::HTTP_BAD_REQUEST, [
                'results' => $results,
                'errors'  => $errors,
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Bulk operation failed');
        }
    }

    /**
     * Reset settings to defaults
     */
    public function resetSettings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $keys_to_reset = $request->get_param('keys');
            $defaults      = $this->getDefaultSettings();

            // Reset all settings
            if (empty($keys_to_reset)) {
                update_option(CCPIGD_OPTIONS_NAME, $defaults);

                return $this->successResponse([
                    'settings' => $defaults,
                    'reset'    => 'all',
                ], 'All settings reset to defaults');
            }

            // Reset specific keys
            $current_settings = get_option(CCPIGD_OPTIONS_NAME, $defaults);
            $updated_settings = $current_settings;
            $reset_keys       = [];

            foreach ($keys_to_reset as $key) {
                if (array_key_exists($key, $defaults)) {
                    $updated_settings[$key] = $defaults[$key];
                    $reset_keys[]           = $key;
                }
            }

            if (empty($reset_keys)) {
                return $this->errorResponse('No valid keys provided for reset', self::HTTP_BAD_REQUEST);
            }

            update_option(CCPIGD_OPTIONS_NAME, $updated_settings);

            return $this->successResponse([
                'settings' => $updated_settings,
                'reset'    => $reset_keys,
            ], 'Selected settings reset to defaults');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to reset settings');
        }
    }

    /**
     * Validate settings without saving
     */
    public function validateSettingsEndpoint(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $settings = $request->get_param('settings');
            $defaults = $this->getDefaultSettings();

            $validated = $this->validateAndSanitizeSettings($settings, $defaults);

            return $this->successResponse([
                'valid'    => true,
                'settings' => $validated,
            ], 'Settings are valid');

        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), self::HTTP_BAD_REQUEST, [
                'valid' => false,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Validation failed');
        }
    }

    /**
     * Process a single bulk operation
     */
    private function processBulkOperation(array $operation, array $current_settings, array $defaults): array
    {
        $action = $operation['action'] ?? null;
        $key    = $operation['key']    ?? null;
        $value  = $operation['value']  ?? null;

        if (!$action) {
            throw new \InvalidArgumentException('Operation action is required');
        }

        switch ($action) {
            case 'set':
                if (!$key) {
                    throw new \InvalidArgumentException('Key is required for set operation');
                }

                $validated = $this->validateAndSanitizeSettings([$key => $value], $defaults);

                return [
                    'status' => 'success',
                    'action' => 'set',
                    'key'    => $key,
                    'data'   => $validated,
                ];

            case 'delete':
                if (!$key) {
                    throw new \InvalidArgumentException('Key is required for delete operation');
                }

                return [
                    'status' => 'success',
                    'action' => 'delete',
                    'key'    => $key,
                    'data'   => [$key => $defaults[$key] ?? null],
                ];

            case 'reset':
                if (!$key) {
                    throw new \InvalidArgumentException('Key is required for reset operation');
                }

                if (!array_key_exists($key, $defaults)) {
                    throw new \InvalidArgumentException(esc_html("Invalid setting key for reset: {$key}"));
                }

                return [
                    'status' => 'success',
                    'action' => 'reset',
                    'key'    => $key,
                    'data'   => [$key => $defaults[$key]],
                ];

            default:
                throw new \InvalidArgumentException(esc_html("Invalid operation action: {$action}"));
        }
    }

    /**
     * Validate and sanitize settings
     */
    private function validateAndSanitizeSettings(array $settings, array $defaults): array
    {
        $validated = [];

        foreach ($settings as $key => $value) {
            // Check if key exists in defaults
            if (!array_key_exists($key, $defaults)) {
                throw new \InvalidArgumentException(esc_html("Invalid setting key: {$key}"));
            }

            // Type validation and sanitization
            $validated[$key] = $this->sanitizeSettingValue($key, $value, $defaults[$key]);
        }

        return $validated;
    }

    /**
     * Sanitize a single setting value based on its type
     */
    private function sanitizeSettingValue(string $key, $value, $default)
    {
        // Handle arrays
        if (is_array($default)) {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(esc_html("Setting '{$key}' must be an array"));
            }

            // Recursively sanitize array values
            $sanitized = [];
            foreach ($value as $sub_key => $sub_value) {
                if (isset($default[$sub_key])) {
                    $sanitized[$sub_key] = $this->sanitizeSettingValue("{$key}.{$sub_key}", $sub_value, $default[$sub_key]);
                } else {
                    // Allow new keys in arrays (for dynamic data like userAccess)
                    $sanitized[$sub_key] = $this->sanitizeValue($sub_value);
                }
            }

            return $sanitized;
        }

        // Handle booleans
        if (is_bool($default)) {
            return (bool) $value;
        }

        // Handle integers
        if (is_int($default)) {
            return (int) $value;
        }

        // Handle strings
        if (is_string($default)) {
            if ('appearance.customCSS' === $key) {
                return trim(sanitize_textarea_field($value));
            }

            return sanitize_text_field($value);
        }

        // Handle null or other types
        return $this->sanitizeValue($value);
    }

    /**
     * Generic value sanitization
     */
    private function sanitizeValue($value)
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }
        if ($value === null) {
            return null;
        }

        return sanitize_text_field((string) $value);
    }

    /**
    * Deep merge arrays - only merges associative arrays, replaces indexed arrays
    *
    * - Merges associative arrays recursively.
    * - Replaces indexed arrays entirely.
    * - Returns merged array.
    * - Logs changed keys in dot-notation.
    */
    private function deepMerge(array $original, array $changes, string $prefix = ''): array
    {
        $diffKeys = [];

        foreach ($changes as $key => $value) {

            $dotKey = $prefix === '' ? $key : "{$prefix}.{$key}";

            // If both are associative arrays → deep merge
            if (
                isset($original[$key])    &&
                is_array($value)          &&
                is_array($original[$key]) &&
                $this->isAssoc($value)    &&
                $this->isAssoc($original[$key])
            ) {
                [$original[$key], $childDiff] = $this->deepMerge($original[$key], $value, $dotKey);

                // Append child diff keys
                $diffKeys = array_merge($diffKeys, $childDiff);
            } else {
                // Detect changes
                if (!array_key_exists($key, $original) || $original[$key] !== $value) {
                    $diffKeys[] = $dotKey;
                }

                // Replace directly
                $original[$key] = $value;
            }
        }

        return [$original, $diffKeys];
    }


    /**
     * Check if array is associative
     */
    private function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Merge current settings with defaults to ensure all keys exist
     */
    private function mergeWithDefaults(array $current, array $defaults): array
    {
        return $this->deepMerge($defaults, $current);
    }

    /**
     * Get default settings
     */
    private function getDefaultSettings(): array
    {
        return function_exists('ccpigdGetDefaultSettings')
            ? ccpigdGetDefaultSettings()
            : [];
    }

    /**
     * Get collection parameters
     */
    private function getCollectionParams(): array
    {
        return [];
    }

    /**
     * Get update parameters
     */
    private function getUpdateParams(): array
    {
        return [
            'settings' => [
                'required'          => true,
                'type'              => 'object',
                'description'       => 'Plugin settings to update',
                'validate_callback' => function ($param) {
                    return is_array($param);
                },
            ],
        ];
    }
}
