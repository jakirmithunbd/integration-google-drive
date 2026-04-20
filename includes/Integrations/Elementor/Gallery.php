<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class Gallery extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-gallery';
    }
    public function get_title()
    {
        return __('Gallery', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-gallery';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'gallery';
    }

    protected function is_pro(): bool
    {
        return false;
    }
}
