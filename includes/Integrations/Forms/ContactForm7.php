<?php

namespace CodeConfig\IGD\Integrations\Forms;

use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Integrations\BaseIntegration;
use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Utils\Singleton;
use WPCF7_Submission;

defined('ABSPATH') or exit;

class ContactForm7 extends BaseIntegration
{
    use Singleton;
    public function __construct()
    {
        parent::__construct('contactForm7', 'Contact Form 7');
    }

    public function init(string $id, array $integration): void
    {
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_style('ccpigd-admin');
        });
        add_action('wpcf7_init', [$this, 'wpcf7Init']);
    }

    public function wpcf7Init()
    {
        add_filter('wpcf7_pre_construct_contact_form_properties', [$this, 'registerProperty'], 10, 2);
        add_filter('wpcf7_editor_panels', [$this, 'editorPanel']);
        add_action('wpcf7_save_contact_form', [$this, 'saveForm'], 10, 3);
        add_action('wpcf7_before_send_mail', [$this, 'processUploadedFiles'], 10, 3);
    }

    /**
     * Register the google_drive property on CF7 forms.
     */
    public function registerProperty($properties, $contact_form)
    {
        $properties += [
            'google_drive' => [],
        ];

        return $properties;
    }

    /**
     * Add the Google Drive tab to the CF7 editor.
     */
    public function editorPanel($panels)
    {
        $panels['google-drive-panel'] = [
            'title'    => __('Google Drive', 'integration-google-drive'),
            'callback' => [$this, 'renderEditorPanel'],
        ];

        return $panels;
    }

    /**
     * Render the Google Drive settings panel.
     */
    public function renderEditorPanel($post)
    {
        $prop = wp_parse_args(
            $post->prop('google_drive'),
            [
                'enable'            => false,
                'upload_folder'     => '',
                'skip_local_upload' => false,
            ]
        );

        $folders = $this->getGoogleDriveFolder();
        ?>
        <h2><?php esc_html_e('Google Drive Upload', 'integration-google-drive'); ?></h2>
        <?php wp_nonce_field('ccpigd_cf7_google_drive_settings', 'ccpigd_cf7_google_drive_nonce'); ?>

        <fieldset>
            <legend>
                <?php esc_html_e('Upload files attached to this form to Google Drive. Files will be uploaded to the selected folder. This settings will apply only the CF7 file fields.', 'integration-google-drive'); ?>
            </legend>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="ccpigd-cf7-enable">
                            <?php esc_html_e('Enable Google Drive Upload', 'integration-google-drive'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="ccpigd-cf7-enable" name="ccpigd-google-drive[enable]" value="1" <?php checked($prop['enable']); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccpigd-cf7-folder">
                            <?php esc_html_e('Upload Folder', 'integration-google-drive'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="ccpigd-cf7-folder" name="ccpigd-google-drive[upload_folder]">
                            <option value=""><?php esc_html_e('— Select Folder —', 'integration-google-drive'); ?></option>
                            <?php foreach ($folders as $key => $name) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($prop['upload_folder'], $key); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccpigd-cf7-skip-local">
                            <?php esc_html_e('Skip Local Upload', 'integration-google-drive'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="ccpigd-cf7-skip-local" name="ccpigd-google-drive[skip_local_upload]" value="1" <?php checked($prop['skip_local_upload']); ?> />
                        <p class="description">
                            <?php esc_html_e('If enabled, files will only be uploaded to Google Drive and not stored locally on the server.', 'integration-google-drive'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </fieldset>
        <?php
    }

    /**
     * Save the Google Drive settings when the form is saved.
     */
    public function saveForm($contact_form, $args, $context)
    {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ccpigd_cf7_google_drive_nonce'] ?? '')), 'ccpigd_cf7_google_drive_settings')) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = isset($_POST['ccpigd-google-drive']) ? (array) $_POST['ccpigd-google-drive'] : [];

        $prop = [
            'enable'            => !empty($data['enable']),
            'upload_folder'     => sanitize_text_field($data['upload_folder'] ?? ''),
            'skip_local_upload' => !empty($data['skip_local_upload']),
        ];

        $contact_form->set_properties([
            'google_drive' => $prop,
        ]);
    }

    public function processUploadedFiles($contact_form, $abort, $submission)
    {
        $submission   = WPCF7_Submission::get_instance();
        $google_drive = $contact_form->prop('google_drive');

        if (empty($google_drive['enable']) || empty($google_drive['upload_folder'])) {
            return;
        }

        if ($submission) {
            $uploaded_files = $submission->uploaded_files();

            $result = null;

            if (!empty($uploaded_files)) {
                foreach ($uploaded_files as $fieldName => $path) {
                    if (is_array($path)) {
                        $path = reset($path);
                    }

                    if (!wpcf7_is_file_path_in_content_dir($path)) {
                        continue;
                    }

                    $originalName = '';
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    if (!empty($_FILES[$fieldName]['name'])) {

                        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                        $names        = (array) $_FILES[$fieldName]['name'];
                        $originalName = sanitize_file_name(reset($names));
                    }

                    $name      = !empty($originalName) ? $originalName : basename($path);
                    $type      = mime_content_type($path);
                    $content   = file_get_contents($path);
                    $folderKey = $google_drive['upload_folder'];

                    $result = App::getInstance()->upload($name, $type, $folderKey, $content);
                }
            }

            if (is_wp_error($result) || empty($result)) {
                $message = is_wp_error($result) ? $result->get_error_message() : __('Unknown error', 'integration-google-drive');
                Notices::getInstance()->add([
                    'type'    => 'error',
                    'message' => sprintf(
                        /* translators: 1: File name, 2: Error message */
                        __('Failed to upload file "%1$s" to Google Drive: %2$s', 'integration-google-drive'),
                        $name,
                        $message
                    ),
                ]);
            } else {
                Notices::getInstance()->add([
                    'type'    => 'success',
                    'message' => sprintf(
                        /* translators: 1: File name */
                        __('File "%s" uploaded to Google Drive successfully.', 'integration-google-drive'),
                        $name
                    ),
                ]);

                // If skip_local_upload is enabled, remove the local file after uploading to Google Drive
                if (!empty($google_drive['skip_local_upload'])) {
                    wpcf7_rmdir_p($path);
                }
            }
        }
    }
}
