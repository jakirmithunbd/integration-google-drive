<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class FileBrowser extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-file-browser';
    }
    public function get_title()
    {
        return __('File Browser', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-file-browser';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'file-browser';
    }

    protected function is_pro(): bool
    {
        return false;
    }
}
