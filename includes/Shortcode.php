<?php

namespace CodeConfig\IGD;

use function CodeConfig\ccpigd_fs;
use CodeConfig\IGD\Models\Shortcode as ModelsShortcode;
use CodeConfig\IGD\Utils\Singleton;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
class Shortcode {
    use Singleton;
    /**
     * Shortcode instance
     * @var ModelsShortcode
     */
    private $scModel;

    private $return = 'string';

    private $integration = null;

    private $attributes = '';

    public static $modulesList = ['file-browser', 'gallery'];

    public function __construct() {
        if ( empty( $this->scModel ) ) {
            $this->scModel = ModelsShortcode::getInstance();
        }
    }

    public static function getModulesList() {
        return self::$modulesList;
    }

    private function doHooks() {
        add_shortcode( 'integration-google-drive', [$this, 'render'] );
    }

    public function render( $atts = [] ) {
        $atts = shortcode_atts( [
            'id'          => 0,
            'return'      => 'string',
            'integration' => '',
            'attributes'  => '',
        ], $atts );
        $this->return = ( $atts['return'] === 'array' ? 'array' : 'string' );
        $this->integration = sanitize_text_field( wp_unslash( $atts['integration'] ?? '' ) );
        $this->attributes = $atts['attributes'] ?? '';
        // Validate and sanitize ID
        $id = $atts['id'] ?? 0;
        if ( !is_numeric( $id ) && preg_match( '/id="(\\d+)"/', $id, $matches ) ) {
            $id = absint( $matches[1] );
        } else {
            $id = absint( $id );
        }
        if ( empty( $id ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $args = [
                'title'       => __( 'Please provide a valid ID', 'integration-google-drive' ),
                'description' => 'Please provide a valid ID. Module ID not found.',
                'card_status' => 'error',
                'icon'        => 'report',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        $shortcode = $this->scModel->get( $id );
        if ( empty( $shortcode ) || is_wp_error( $shortcode ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = ( is_wp_error( $shortcode ) ? $shortcode->get_error_message() : __( 'Module not found', 'integration-google-drive' ) );
            $args = [
                'title'       => "#{$id} - {$message}",
                'card_status' => 'error',
                'icon'        => 'error',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        $proModules = ccpigdGetModules( 'pro' );
        if ( in_array( $shortcode['type'], wp_list_pluck( $proModules, 'id' ) ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $args = [
                'title'          => __( 'This Module is a Pro Module', 'integration-google-drive' ),
                'description'    => 'Need to upgrade to get access to this Module.',
                'card_status'    => 'warning',
                'icon'           => 'report',
                'primary_button' => [
                    'icon'   => 'crown',
                    'title'  => 'Upgrade Now',
                    'url'    => ccpigd_fs()->get_upgrade_url(),
                    'target' => true,
                ],
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        if ( is_wp_error( $shortcode ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = $shortcode->get_error_message();
            $args = [
                'title'       => $message,
                'card_status' => 'warning',
                'icon'        => 'error',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        if ( empty( $shortcode ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = __( 'Module not found', 'integration-google-drive' );
            $args = [
                'title'       => "#{$id} - {$message}",
                'card_status' => 'error',
                'icon'        => 'error',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        if ( empty( $shortcode['status'] ) || isset( $shortcode['status'] ) && $shortcode['status'] !== 'active' ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = __( 'Shortcode is disabled', 'integration-google-drive' );
            $args = [
                'title'          => "#{$id} - {$message}",
                'description'    => __( 'Please enable this Module from Module Builder', 'integration-google-drive' ),
                'card_status'    => 'error',
                'icon'           => 'sentiment_very_dissatisfied',
                'primary_button' => [
                    'title'  => 'Enable Shortcode',
                    'url'    => admin_url( "admin.php?page=integration-google-drive#/module-builder/{$id}/modules" ),
                    'target' => true,
                ],
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        // if (!empty($this->integration)) {
        //     $integration = str_replace('*', '', $this->integration);
        //     if (empty($shortcode['integration']) || (isset($shortcode['integration']) && $shortcode['integration'] !== $integration)) {
        //         if (!current_user_can('manage_options')) {
        //             return;
        //         }
        //         $title   = __('Integration Mismatch', 'integration-google-drive');
        //         $message = __('This Module is not compatible with this Integration', 'integration-google-drive');
        //         if ($integration === 'contactForm7' && $shortcode['type'] !== 'file-upload') {
        //             $message = __('This module is not compatible with this integration. Contact Form 7 only supports File Upload modules that are created using the Contact Form 7 Module Builder.', 'integration-google-drive');
        //         }
        //         $args = [
        //             'title'          => "#$id - $title",
        //             'description'    => $message,
        //             'card_status'    => 'warning',
        //             'icon'           => 'report',
        //         ];
        //         ob_start();
        //         ccpigdGetTemplate('notice-card/notice-card-common', $args);
        //         return ob_get_clean();
        //     }
        // }
        $data = maybe_unserialize( $shortcode['data'] ?? '' );
        if ( empty( $data ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = __( 'No data available for this Module', 'integration-google-drive' );
            $args = [
                'title'       => "#{$id} - {$message}",
                'card_status' => 'error',
                'icon'        => 'sentiment_dissatisfied',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        $shortcode['data'] = $data;
        return $this->renderShortcode( $id, $shortcode );
    }

    private function renderShortcode( $id, $data ) {
        $type = $data['type'] ?? '';
        if ( empty( $type ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = __( 'Type not given for this Module', 'integration-google-drive' );
            $args = [
                'title'       => "#{$id} - {$message}",
                'card_status' => 'warning',
                'icon'        => 'warning',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        if ( !isset( $data['data'] ) || empty( $data['data'] ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            $message = __( 'No data provided for this Module', 'integration-google-drive' );
            $args = [
                'title'       => "#{$id} - {$message}",
                'card_status' => 'warning',
                'icon'        => 'sentiment_very_dissatisfied',
            ];
            ob_start();
            ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
            return ob_get_clean();
        }
        $status = 'public';
        if ( isset( $data['data']['permissions']['passwordProtect']['password'] ) ) {
            unset($data['data']['permissions']['passwordProtect']['password']);
        }
        $object_key = "ccpigd_{$id}";
        $enqueueHandle = "ccpigd-{$type}";
        $theme = $data['data']['advanced']['theme'] ?? 'light';
        $enqueue = Enqueue::getInstance();
        $enqueue->common_scripts( $object_key, 'frontend' );
        $enqueue->add( 'shared', 'js', [$enqueueHandle] );
        $enqueue->add(
            $type,
            'css',
            [],
            [
                'folder' => 'css/frontend',
            ]
        );
        // Escape all output for security
        $escaped_id = absint( $id );
        $escaped_status = esc_attr( $status );
        $escaped_enqueue_handle = esc_attr( $enqueueHandle );
        $escaped_object_key = esc_js( $object_key );
        $width = $data['data']['advanced']['width']['value'] ?? '';
        $width_unit = $data['data']['advanced']['width']['unit'] ?? '%';
        $height = $data['data']['advanced']['height']['value'] ?? '';
        $height_unit = $data['data']['advanced']['height']['unit'] ?? 'auto';
        $style = '';
        if ( $width !== '' ) {
            $style .= 'width:' . esc_attr( $width . $width_unit ) . ';';
        }
        if ( $height !== '' ) {
            if ( $height_unit === 'auto' ) {
                $style .= 'height:auto;';
            } else {
                $style .= 'height:' . esc_attr( $height . $height_unit ) . ';';
            }
        }
        $postId = get_the_ID();
        $render_id = uniqid( "ccpigd_{$escaped_id}_", true );
        $html = sprintf(
            '<div data-post_id="%d" data-id="ccpigd_%d" data-render_id="%s" data-status="%s" id="ccpigd-module-%d" class="ccpigd-top-level-wrapper ccpigd-module %s" ccpigd-theme-status="%s" style="%s" %s></div>',
            $postId,
            $escaped_id,
            $render_id,
            $escaped_status,
            $escaped_id,
            $escaped_enqueue_handle,
            $theme,
            esc_attr( $style ),
            esc_html( $this->attributes )
        );
        if ( $this->return === 'array' ) {
            return [
                'id'             => $escaped_id,
                'status'         => $escaped_status,
                'data_id'        => "ccpigd_{$escaped_id}",
                'element_id'     => "ccpigd-module-{$escaped_id}",
                'enqueue_handle' => $escaped_enqueue_handle,
                'type'           => $type,
                'data'           => $data,
                'html'           => $html,
            ];
        }
        // Use proper JSON encoding with security flags to prevent XSS
        $json_data = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
        if ( false === $json_data ) {
            // Fallback if encoding fails
            $json_data = '{}';
        }
        $html .= sprintf( '<script>window.%s = %s;</script>', $escaped_object_key, $json_data );
        return $html;
    }

}
