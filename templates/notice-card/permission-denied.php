<?php defined('ABSPATH') || exit; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #eef2f3, #dfe6e9);
            font-family: "Inter", Arial, sans-serif;
            color: #2d3436;
        }

        body#error-page  {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            flex-direction: column;
            margin: 0;
            max-width: 100%;
        }

        .ccpigd-notice-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 420px;
            width: 100%;
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            color: #e74c3c;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #e74c3c;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            color: #636e72;
        }
    </style>
</head>

<body>
    <div class="ccpigd-notice-card">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>

        <?php $ccpigd_title       = $args['title']             ?? __('Access Denied', 'integration-google-drive'); ?>
        <?php $ccpigd_description = $args['description']       ?? __('You do not have permission to access this file.', 'integration-google-drive'); ?>

        <h1><?php echo esc_html($ccpigd_title); ?></h1>
        <p><?php echo esc_html($ccpigd_description); ?></p>
    </div>
</body>
</html>
