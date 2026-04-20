<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class SliderCarousel extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-slider-carousel';
    }
    public function get_title()
    {
        return __('Slider Carousel', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-slider-carousel';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'slider-carousel';
    }

    // public function get_custom_help_url(): string
    // {
    //     return '';
    // }

    protected function is_pro(): bool
    {
        return true;
    }
}
