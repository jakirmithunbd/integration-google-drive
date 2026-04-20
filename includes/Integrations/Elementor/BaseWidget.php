<?php

namespace CodeConfig\IGD\Integrations\Elementor;

use CodeConfig\IGD\Enqueue;
use CodeConfig\IGD\Shortcode;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
defined( 'ABSPATH' ) || exit( 'No direct script access allowed' );
abstract class BaseWidget extends Widget_Base {
    protected abstract function get_module_type();

    protected abstract function is_pro() : bool;

    public function register_controls() {
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Content', 'integration-google-drive' ),
        ] );
        $this->add_control( 'edit_module', [
            'label' => $this->get_title(),
            'type'  => Controls_Manager::BUTTON,
            'text'  => __( 'Configure Module', 'integration-google-drive' ),
            'event' => 'ccpigd_elementor_settings',
        ] );
        $this->add_control( 'module_data', [
            'label'   => __( 'Module Data', 'integration-google-drive' ),
            'type'    => Controls_Manager::HIDDEN,
            'default' => $this->get_default_module_data(),
        ] );
        $this->add_control( 'select_shortcode', [
            'label'       => __( 'Select Shortcode', 'integration-google-drive' ),
            'type'        => Controls_Manager::SELECT,
            'options'     => $this->get_shortcode_options( $this->get_module_type() ),
            'default'     => '',
            'description' => __( 'Select a shortcode to display its content.', 'integration-google-drive' ),
            'label_block' => true,
        ] );
        $this->end_controls_section();
    }

    protected function get_default_module_data() {
        return wp_json_encode( [
            'id'     => $this->get_module_type(),
            'type'   => $this->get_module_type(),
            'is_pro' => $this->is_pro(),
        ] );
    }

    public function is_editable() {
        global $ccpigd_fs;
        if ( !$ccpigd_fs->is_paying() && $this->is_pro() ) {
            return false;
        }
        return true;
    }

    public function get_script_depends() {
        return [];
    }

    public function get_style_depends() {
        return ['ccpigd-common', 'ccpigd-admin'];
    }

    public function render() {
        $settings = $this->get_settings_for_display();
        $module_id = $this->get_module_id( $settings );
        if ( $this->should_show_upgrade_notice( $settings ) ) {
            $this->render_upgrade_notice();
            return;
        }
        if ( $this->should_show_empty_notice( $module_id ) ) {
            $this->render_empty_notice();
            return;
        }
        $this->render_shortcode( $module_id );
    }

    protected function get_module_id( $settings ) {
        if ( $this->get_module_type() === 'shortcode' ) {
            return $settings['select_shortcode'] ?? null;
        }
        $module_data = json_decode( $settings['module_data'] ?? '', true );
        return $module_data['id'] ?? null;
    }

    protected function should_show_upgrade_notice( $settings ) {
        if ( !is_user_logged_in() ) {
            return false;
        }
        $module_data = json_decode( $settings['module_data'] ?? '', true );
        return $module_data['is_pro'] ?? false;
    }

    protected function should_show_empty_notice( $module_id ) {
        if ( !is_user_logged_in() ) {
            return false;
        }
        return empty( $module_id ) || !intval( $module_id );
    }

    protected function render_shortcode( $module_id ) {
        $shortcode_data = Shortcode::getInstance()->render( [
            'id'     => $module_id,
            'type'   => 'file-uploader',
            'return' => 'array',
        ] );
        if ( empty( $shortcode_data['html'] ) ) {
            return;
        }
        printf(
            '<script>window.%s = %s;</script>%s',
            esc_js( $shortcode_data['data_id'] ),
            wp_json_encode( $shortcode_data['data'] ),
            wp_kses( $shortcode_data['html'], [
                'div' => [
                    'class'               => [],
                    'id'                  => [],
                    'data-post_id'        => [],
                    'data-id'             => [],
                    'data-status'         => [],
                    'ccpigd-theme-status' => [],
                    'style'               => [],
                ],
            ] )
        );
    }

    protected function render_upgrade_notice() {
        $args = [
            'title'          => $this->get_title(),
            'description'    => __( 'Please upgrade to unlock this feature', 'integration-google-drive' ),
            'icon'           => 'report',
            'card_status'    => 'warning',
            'primary_button' => [
                'title'  => __( 'Upgrade now!', 'integration-google-drive' ),
                'target' => false,
                'icon'   => 'crown',
                'class'  => 'ccpigd-upgrade-now',
                'url'    => ccpigd_fs()->get_upgrade_url(),
            ],
        ];
        ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
    }

    protected function render_empty_notice() {
        $args = [
            'title'          => $this->get_title(),
            'description'    => __( 'Click Configure to setup your module', 'integration-google-drive' ),
            'icon'           => $this->get_icon(),
            'iconClass'      => $this->get_icon(),
            'card_status'    => 'primary',
            'primary_button' => [
                'title'  => __( 'Configure Module', 'integration-google-drive' ),
                'target' => false,
                'icon'   => 'settings',
                'class'  => 'configure-module',
            ],
        ];
        ccpigdGetTemplate( 'notice-card/notice-card-common', $args );
    }

    public function get_shortcode_options( $type = '' ) {
        try {
            $shortcodes = \CodeConfig\IGD\Models\Shortcode::getInstance()->getAll( [
                'perPage' => 0,
            ] );
            if ( empty( $shortcodes ) ) {
                return [];
            }
            if ( !empty( $type ) ) {
                $shortcodes = array_filter( $shortcodes, function ( $shortcode ) use($type) {
                    return $shortcode['type'] === $type;
                } );
            }
            $formatted_options = [
                '' => sprintf( __( '-- Select %s Shortcode --', 'integration-google-drive' ), ucfirst( $type ) ),
            ];
            foreach ( $shortcodes as $shortcode ) {
                if ( !empty( $shortcode['id'] ) && !empty( $shortcode['title'] ) ) {
                    $formatted_options[$shortcode['id']] = $shortcode['id'] . ' - ' . $shortcode['title'];
                }
            }
            return $formatted_options;
        } catch ( \Exception $e ) {
            return [];
        }
    }

}
