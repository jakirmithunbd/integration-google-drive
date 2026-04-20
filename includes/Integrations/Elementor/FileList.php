<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class FileList extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-file-list';
    }
    public function get_title()
    {
        return __('File List', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-file-list';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'file-list';
    }

    protected function is_pro(): bool
    {
        return true;
    }
}
