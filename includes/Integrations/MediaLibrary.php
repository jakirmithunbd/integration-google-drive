<?php

namespace CodeConfig\IGD\Integrations;

use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') || exit('No direct script access allowed');

class MediaLibrary extends BaseIntegration
{
    use Singleton;

    public function __construct()
    {
        parent::__construct('mediaLibrary', 'Media Library');
    }


    public function doHooks()
    {
        add_action('pre_get_posts', [$this, 'filterGridAttachments']);
    }

    public function localizeData(array $data, $script): array
    {

        if ($script !== 'admin') {
            return $data;
        }

        global $pagenow;

        $data['pagenow'] = $pagenow;

        return $data;
    }

    public function enqueueScripts($hook)
    {
        wp_enqueue_style('ccpigd-admin');
        wp_enqueue_script('ccpigd-media-library');
    }

    public function filterGridAttachments($query)
    {
        if (! isset($query->query_vars['post_type']) || 'attachment' !== $query->query_vars['post_type']) {
            return $query;
        }

        if (wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['ccpigdNonce'] ?? '')), 'ccpigd') === false) {
            return $query;
        }

        if (empty($_REQUEST['query'])) {
            return $query;
        }

        $meta_query = $query->get('meta_query') ?: [];

        $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => '_ccpigd_media_file_key',
                    'compare' => 'NOT EXISTS',
                ],
            ];

        $query->set('meta_query', $meta_query);

        return $query;
    }

    public function init(string $id, array $integration): void
    {
        add_filter('ccpigd_localize_data', [$this, 'localizeData'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
}
