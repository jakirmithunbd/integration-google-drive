<?php

namespace CodeConfig\IGD\Integrations;

use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') || exit('No direct script access allowed');

class ClassicEditor extends BaseIntegration
{
    use Singleton;
    public function __construct()
    {
        parent::__construct('classicEditor', 'Classic Editor');
    }

    public function init(string $id, array $integration): void
    {
        $this->isInit = true;
    }

    public function doHooks()
    {
        if (!$this->isActive()) {
            return;
        }

        add_action('media_buttons', [$this, 'addMediaButton'], 20);
    }

    public function addMediaButton()
    {

        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (! empty($screen) && $screen->base !== 'post') {
            return;
        }

        printf(
            '<div class="ccpigd-top-level-wrapper" style="display: inline;"><button id="ccpigd-media-button" type="button" class="cc-button cc-button--primary cc-button--extrasmall rounded-sm text-capitalize" role="button"><span class="cc-icon">add_to_drive</span>Google Drive</button></div>'
        );
    }
}
