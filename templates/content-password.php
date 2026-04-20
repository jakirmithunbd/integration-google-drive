<?php defined('ABSPATH') || exit;

$ccpigd_code       = $args['code']         ?? '';
$ccpigd_message    = $args['message']      ?? '';
$ccpigd_fileKey    = $args['fileKey']      ?? '';
$ccpigd_name       = $args['name']         ?? '';
$ccpigd_field_name = $args['fieldName']    ?? 'ccpigd-field-password';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php esc_attr__('Password Form', 'integration-google-drive'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            text-decoration: none;
            border: none;
            outline: none;
            scroll-behavior: smooth;
        }

        .ccpigd-password {
            --primary: #15be7c;
            --secondary: #1d9265ff;
            --light: hsl(from var(--primary) h s l / 11%);
            --extra-light: hsl(from var(--primary) h s l / 1%);
            --white: #ffffff;
            background: var(--extra-light);
            margin: clamp(30px, 10vw, 100px) 0;
            padding: 0 clamp(10px, 5vw, 30px);

        }

        .ccpigd-password .ccpigd-password-field {
            font-family: Arial, Helvetica, sans-serif;
            width: 100%;
            max-width: 1024px;
            margin: auto;
            border: 1px solid var(--light);
            padding: clamp(15px, 2vw, 30px);
            border-radius: 12px;
            background: var(--white);

        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper {
            width: 100%;
            text-align: center;
            display: flex;
            align-items: center;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-content,
        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input {
            width: 100%;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-content .ccpigd-icon {
            height: clamp(30px, 10vw, 100px);
            aspect-ratio: 1 / 1;
            fill: var(--primary);

        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-content .ccpigd-password-field__title {
            font-size: clamp(20px, 5vw, 30px);
            font-weight: 600;
            line-height: 1.2em;
            color: #000000;
            margin-bottom: 10px;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-content .ccpigd-password-field__description {
            font-size: clamp(14px, 3vw, 18px);
            font-weight: 400;
            line-height: 1.2em;
            color: #424242ff;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input {
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-bottom: 10px;

        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-input {
            width: 100%;
            max-width: 400px;
            background: var(--white);
            border: 1px solid;
            border-color: var(--light);
            border-radius: 4px;
            text-align: left;
            padding: 10px 15px;
            transition: all 0.3s ease;
            position: relative;

        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-input:hover {
            border-color: var(--primary);
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-input input[type="password"] {
            font-family: Verdana, sans-serif;
            font-size: clamp(14px, 3vw, 20px);
            color: #000000;
            width: 100%;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-submit-btn {
            background: var(--primary);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 4px;
            font-size: clamp(14px, 3vw, 18px);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-submit-btn:hover {
            background: var(--secondary);
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-submit-btn {
            background: var(--primary);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 4px;
            font-size: clamp(14px, 3vw, 18px);
            cursor: pointer;
            transition: all 0.3s ease;

        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-submit-btn:hover {
            background: var(--secondary);
        }

        .ccpigd-password .ccpigd-password-field .ccpigd-password-field__wrapper .ccpigd-password-field__wrapper-input .ccpigd-input .ccpigd-password-field__wrapper-error {
            position: absolute;
            bottom: -20px;
            left: 0;
            font-size: 14px;
            font-weight: 500;
            color: red;
        }
    </style>
</head>

<body class="ccpigd-password">
    <div class="ccpigd-password-field">
        <div class="ccpigd-password-field__wrapper">
            <div class="ccpigd-password-field__wrapper-content">
                <svg class="ccpigd-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960">
                    <path d="M420-360h120l-23-129q20-10 31.5-29t11.5-42q0-33-23.5-56.5T480-640q-33 0-56.5 23.5T400-560q0 23 11.5 42t31.5 29l-23 129Zm60 280q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z" />
                </svg>
                <h5 class="ccpigd-password-field__title"><?php esc_attr_e('You do not have access to this file.', 'integration-google-drive'); ?></h5>
                <p class="ccpigd-password-field__description"><?php esc_attr_e('Enter the secret password to access this.', 'integration-google-drive'); ?></p>
            </div>

            <form method="post" class="ccpigd-password-field__wrapper-input">
                <?php wp_nonce_field('ccpigd_password_nonce', 'ccpigd-password-nonce'); ?>
                <div class="ccpigd-input">
                    <input id="<?php echo esc_attr($ccpigd_field_name); ?>" type="password" name="<?php echo esc_attr($ccpigd_field_name); ?>" placeholder="Enter Password" class="ccpigd-input__input" aria-invalid="false" required>
                    <?php
                        if (! empty($ccpigd_message) && $ccpigd_code === 'invalid_password') {
                            echo '<p class="ccpigd-password-field__wrapper-error">' . esc_html($ccpigd_message) . '</p>';
                        }?>
                </div>

                <button type="submit" class="ccpigd-submit-btn">
                    <?php esc_attr_e('Submit', 'integration-google-drive'); ?>
                </button>
            </form>
        </div>
    </div>
</body>

</html>

