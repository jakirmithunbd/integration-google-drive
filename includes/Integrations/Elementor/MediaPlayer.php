<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class MediaPlayer extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-media-player';
    }
    public function get_title()
    {
        return __('Media Player', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-media-player ccpigd-icon-pro';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'media-player';
    }

    protected function is_pro(): bool
    {
        return true;
    }
}
