<?php

namespace CodeConfig\IGD\Integrations;

use CodeConfig\IGD\Enqueue;
use CodeConfig\IGD\Integrations\Elementor\EmbedDocuments;
use CodeConfig\IGD\Integrations\Elementor\FileBrowser;
use CodeConfig\IGD\Integrations\Elementor\FileList;
use CodeConfig\IGD\Integrations\Elementor\Gallery;
use CodeConfig\IGD\Integrations\Elementor\MediaPlayer;
use CodeConfig\IGD\Integrations\Elementor\SearchBox;
use CodeConfig\IGD\Integrations\Elementor\Shortcode;
use CodeConfig\IGD\Integrations\Elementor\SliderCarousel;
use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') or exit;

class Elementor extends BaseIntegration
{
    use Singleton;

    public function __construct()
    {
        parent::__construct('elementor', 'Elementor Blocks');
    }

    public function init(string $id, array $integration): void
    {
        add_action('elementor/editor/wp_head', [$this,'renderStyles']);
        add_action('elementor/frontend/after_enqueue_scripts', [$this,'renderStyles']);
        add_action('plugin_loaded', function () {
            if (!defined('ELEMENTOR_VERSION')) {
                return;
            }

            add_action('elementor/elements/categories_registered', [$this, 'addCategory']);
            $hook = version_compare(\ELEMENTOR_VERSION, '3.5.0', '>=') ? 'elementor/widgets/register' : 'elementor/widgets/widgets_registered';
            add_action($hook, [$this, 'registerWidgets']);
        });
    }

    public function renderStyles()
    {
        Enqueue::getInstance()->add('common', 'css');
    }

    public function addCategory($elements_manager): void
    {
        $elements_manager->add_category('integration-google-drive', [
            'title'=> __('Google Drive', 'integration-google-drive'),
            'icon' => 'fa fa-cloud',
        ]);
    }

    public function registerWidgets($widgets_manager): void
    {
        $widgets = [
            FileBrowser::class,
            Gallery::class,
            FileList::class,
            MediaPlayer::class,
            SliderCarousel::class,
            SearchBox::class,
            EmbedDocuments::class,
            Shortcode::class,
        ];

        foreach ($widgets as $class) {
            if (class_exists($class)) {
                if (method_exists($widgets_manager, 'register')) {
                    $widgets_manager->register(new $class());
                } else {
                    $widgets_manager->register_widget_type(new $class());
                }
            }
        }
    }
}
