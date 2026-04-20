<?php

namespace CodeConfig\IGD\Integrations\Elementor;

defined('ABSPATH') || exit('No direct script access allowed');

class EmbedDocuments extends BaseWidget
{
    public function get_name()
    {
        return 'ccpigd-embed-documents';
    }
    public function get_title()
    {
        return __('Embed Documents', 'integration-google-drive');
    }
    public function get_icon()
    {
        return 'ccpigd-embed-document ccpigd-icon-pro';
    }
    public function get_categories()
    {
        return ['integration-google-drive', 'basic'];
    }

    protected function get_module_type()
    {
        return 'embed-documents';
    }

    protected function is_pro(): bool
    {
        return true;
    }
}
