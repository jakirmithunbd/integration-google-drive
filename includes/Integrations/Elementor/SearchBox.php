<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class SearchBox extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-search-box';
    }
    public function get_title()
    {
        return __('Search Box', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-search-box ccpigd-icon-pro';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'search-box';
    }

    protected function is_pro(): bool
    {
        return true;
    }
}
