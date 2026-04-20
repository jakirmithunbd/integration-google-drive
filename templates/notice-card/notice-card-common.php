<?php defined('ABSPATH') || exit;

if (! empty($args)) :
    // Safe defaults to avoid "undefined index" notices
    $title            = isset($args['title']) ? $args['title'] : '';
    $description      = isset($args['description']) ? $args['description'] : '';
    $icon             = isset($args['icon']) ? $args['icon'] : '';
    $wrapper_class    = isset($args['wrapper_class']) ? $args['wrapper_class'] : '';
    $iconClass        = isset($args['iconClass']) ? $args['iconClass'] :
        $card_status      = isset($args['card_status']) ? $args['card_status'] : 'primary';
    $primary_button   = isset($args['primary_button']) ? (array) $args['primary_button'] : [];
    $secondary_button = isset($args['secondary_button']) ? (array) $args['secondary_button'] : [];

    // Button field fallbacks
    $pb_title  = isset($primary_button['title']) ? $primary_button['title'] : '';
    $pb_url    = isset($primary_button['url']) ? $primary_button['url'] : '';
    $pb_target = ! empty($primary_button['target']) ? '_blank' : '_self';
    $pb_icon   = isset($primary_button['icon']) ? $primary_button['icon'] : 'check';
    $pb_class  = isset($primary_button['class']) ? ' ccpigd-' . $primary_button['class'] : '';

    $sb_title  = isset($secondary_button['title']) ? $secondary_button['title'] : '';
    $sb_url    = isset($secondary_button['url']) ? $secondary_button['url'] : '';
    $sb_target = ! empty($secondary_button['target']) ? '_blank' : '_self';
    $sb_icon   = isset($secondary_button['icon']) ? $secondary_button['icon'] : 'check';
    $sb_class  = isset($secondary_button['class']) ? ' ccpigd-' . $secondary_button['class'] : '';

?>
    <div class="ccpigd-top-level-wrapper <?php echo esc_attr($wrapper_class) ?>">
        <div class="ccpigd-notice-card flex-center rounded-md ccpigd-notice-status-<?php echo esc_attr($card_status); ?>">
            <div class="ccpigd-notice-card-wrapper flex-center flex-col">
                <?php if (! empty($icon)) : ?>
                    <span class="ccpigd-icon ccpigd-notice-card-wrapper-icon <?php echo esc_attr($iconClass); ?>"><?php echo esc_html($icon); ?></span>
                <?php endif; ?>

                <div class="ccpigd-notice-card-wrapper-content flex-center flex-col">
                    <?php if (! empty($title)) : ?>
                        <h3 class="ccpigd-title"><?php echo esc_html($title); ?></h3>
                    <?php endif; ?>

                    <?php if (! empty($description)) : ?>
                        <p class="ccpigd-description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($pb_title || $sb_title) : ?>
                    <div class="ccpigd-notice-card-wrapper-buttons flex-center">
                        <?php if ($pb_title) : ?>
                            <?php if ($pb_url) : ?>
                                <a href="<?php echo esc_url($pb_url); ?>"
                                    target="<?php echo esc_attr($pb_target); ?>"
                                    class="ccpigd-notice-card-btn cc-button cc-button--primary cc-button--small rounded-sm ccpigd-btn--<?php echo esc_attr($card_status); ?> <?php echo esc_attr($pb_class); ?>">
                                <?php else : ?>
                                    <button type="button"
                                        class="ccpigd-notice-card-btn cc-button cc-button--primary cc-button--small rounded-sm ccpigd-btn--<?php echo esc_attr($card_status); ?> <?php echo esc_attr($pb_class); ?>">
                                    <?php endif; ?>

                                    <span class="ccpigd-icon text-md"><?php echo esc_html($pb_icon); ?></span>
                                    <span><?php echo esc_html($pb_title); ?></span>
                                    <?php if ($pb_url) : ?>
                                </a>
                            <?php else : ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>


                        <?php if ($sb_title) : ?>
                            <?php if ($sb_url) : ?>
                                <a href="<?php echo esc_url($sb_url); ?>"
                                    target="<?php echo esc_attr($sb_target); ?>"
                                    class="ccpigd-notice-card-btn ccpigd-btn ccpigd-btn--rounded-sm ccpigd-btn--<?php echo esc_attr($card_status); ?> <?php echo esc_attr($sb_class); ?>">
                                <?php else : ?>
                                    <button type="button"
                                        class="ccpigd-notice-card-btn ccpigd-btn ccpigd-btn--rounded-sm ccpigd-btn--<?php echo esc_attr($card_status); ?> <?php echo esc_attr($sb_class); ?>">
                                    <?php endif; ?>

                                    <span class="ccpigd-icon text-md"><?php echo esc_html($sb_icon); ?></span>
                                    <span><?php echo esc_html($sb_title); ?></span>

                                    <?php if ($sb_url) : ?>
                                </a>
                            <?php else : ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
<?php endif; ?>