<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\App\API\Account;
use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Utils\Singleton;
use Exception;

defined('ABSPATH') || exit('No direct script access allowed');

class Authorization
{
    use Singleton;

    public function doingAuth(string $code)
    {

        if (empty($code)) {
            Notices::getInstance()->add([
                'type'        => 'error',
                'title'       => __('Unable to get authorization code.', 'integration-google-drive'),
                'description' => __('Required parameters "code" is missing. so we are unable to get authorization this account. please try again', 'integration-google-drive'),
            ]);
            $this->closeAndExit();
            exit;
        }

        $this->createAccessToken($code);

        $this->closeAndExit();
    }

    private function createAccessToken($code)
    {

        try {
            $client = new Client('new');

            $googleClient = $client->getClient();

            $accessToken = $googleClient->authenticate($code);
            if (false === $accessToken) {
                Notices::getInstance()->add([
                'type'        => 'error',
                'title'       => __('Unable to get access token.', 'integration-google-drive'),
                'description' => __('Access token is missing so we are not able to add this account. please try again', 'integration-google-drive'),
            ]);
                $this->closeAndExit();
                exit;
            }

            // $data = Account::getInstance($googleClient, 'client')->get();
            $accountAPI    = new Account($client);
            $account       = $accountAPI->get();

            if (is_wp_error($account)) {
                Notices::getInstance()->add([
                    'type'        => 'error',
                    'title'       => __('Unable to get account data.', 'integration-google-drive'),
                    'description' => $account->get_error_message(),
                ]);
                $this->closeAndExit();
            }

            if (false === $account) {
                Notices::getInstance()->add([
                    'type'        => 'error',
                    'title'       => __('Unable to get account data.', 'integration-google-drive'),
                    'description' => __('Account data is missing so we are not able to add this account. please try again', 'integration-google-drive'),
                ]);
                $this->closeAndExit();
            }

            $id      = $account['id']      ?? 0;
            $name    = $account['name']    ?? '';
            $email   = $account['email']   ?? '';
            $photo   = $account['photo']   ?? '';
            $storage = $account['storage'] ?? [];
            $lost    = $account['lost']    ?? false;
            $root_id = $account['root_id'] ?? 0;
            $user_id = get_current_user_id();

            if (empty($id) || empty($name) || empty($email) || empty($photo) || empty($storage) || empty($root_id)) {
                Notices::getInstance()->add([
                    'type'        => 'error',
                    'title'       => __('Unable to get account data.', 'integration-google-drive'),
                    'description' => __('Account data is missing so we are not able to add this account. please try again', 'integration-google-drive'),
                ]);
                $this->closeAndExit();
            }

            $account = Accounts::getInstance()->addAccount($id, $name, $email, $photo, $storage, $lost, $root_id, $user_id, $accessToken);
            if (is_wp_error($account)) {
                Notices::getInstance()->add([
                    'type'        => 'error',
                    'title'       => __('Unable to add account.', 'integration-google-drive'),
                    'description' => $account->get_error_message(),
                ]);
                $this->closeAndExit();
            } else {
                Notices::getInstance()->add([
                    'type'        => 'success',
                    'title'       => __('Account added successfully.', 'integration-google-drive'),
                    'description' => __('Great! Your account is added successfully. Now you can start using your files in your website. if you have any issue or need any help please feel free to contact us.', 'integration-google-drive'),
                ]);
            }

        } catch (Exception $exception) {
            Notices::getInstance()->add([
                'type'        => 'error',
                'title'       => __('Unable to get account data.', 'integration-google-drive'),
                'description' => $exception->getMessage(),
            ]);
            $this->closeAndExit();
        }

        return true;
    }

    private function closeAndExit()
    {
        $redirect_url = esc_url(admin_url("admin.php?page=integration-google-drive"));
        wp_safe_redirect($redirect_url);
        exit;
    }
}
