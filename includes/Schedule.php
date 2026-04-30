<?php

namespace CodeConfig\IGD;

use CodeConfig\IGD\App\Accounts;
use CodeConfig\IGD\App\App;
use CodeConfig\IGD\Utils\Singleton;

defined('ABSPATH') || exit('No direct script access allowed');

class Schedule
{
    use Singleton;

    private const MAX_EXECUTION_TIME = 120;
    private const PER_PAGE           = 50;

    public function __construct()
    {
        \add_action('ccpigd_sync_all_files', [$this, 'syncCachedFiles'], 10, 2);
    }

    public function syncCachedFiles(string $accountId, int $page): void
    {
        if (empty($accountId) || $page < 1) {
            return;
        }

        $startTime        = time();
        $perPage          = self::PER_PAGE;
        $processedFolders = 0;
        $processedFiles   = 0;
        $errors           = [];

        $app = App::getInstance($accountId);

        $account = Accounts::getInstance()->getAccount($accountId);

        if (\is_wp_error($account)) {
            $this->updateSyncProgress($accountId, ['status' => 'error', 'message' => $account->get_error_message()]);
            $this->cleanupSync($accountId);

            return;
        }

        $rootFolder = $app->getFiles([
            'id'        => $account->getRootId(),
            'accountId' => $accountId,
            'from'      => 'server',
        ]);

        if (\is_wp_error($rootFolder)) {
            $this->updateSyncProgress($accountId, ['status' => 'error', 'message' => $rootFolder->get_error_message()]);
            $this->cleanupSync($accountId);

            return;
        }

        $result = $app->getFolders($accountId, [
            'page'    => $page,
            'perPage' => $perPage,
        ]);

        if (\is_wp_error($result)) {
            $this->updateSyncProgress($accountId, ['status' => 'error', 'message' => $result->get_error_message()]);
            $this->cleanupSync($accountId);

            return;
        }

        if (empty($result['files'])) {
            $this->updateSyncProgress($accountId, ['status' => 'complete', 'processedFolders' => 0, 'processedFiles' => 0, 'errors' => []]);
            $this->cleanupSync($accountId);

            return;
        }

        $running = !empty($result['files']);

        while ($running) {
            if (time() - $startTime >= self::MAX_EXECUTION_TIME) {
                $this->updateSyncProgress($accountId, [
                    'status'           => 'partial',
                    'processedFolders' => $processedFolders,
                    'processedFiles'   => $processedFiles,
                    'nextPage'         => $result['nextPage'] ?? $page,
                    'errors'           => $errors,
                ]);

                return;
            }

            foreach ($result['files'] as $file) {
                if (empty($file['id'])) {
                    continue;
                }

                try {
                    $fileResult = $app->getFiles([
                        'id'        => $file['id'],
                        'accountId' => $accountId,
                        'from'      => 'server',
                    ]);

                    if (\is_wp_error($fileResult)) {
                        $errors[] = "Failed to sync file {$file['id']}: " . $fileResult->get_error_message();
                    } else {
                        $processedFiles += count($fileResult['files'] ?? []);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Exception syncing file {$file['id']}: " . $e->getMessage();
                }
            }

            $processedFolders += count($result['files']);

            if (isset($result['hasMore']) && $result['hasMore'] && isset($result['nextPage'])) {
                $result = $app->getFolders($accountId, [
                    'page'    => $result['nextPage'],
                    'perPage' => $perPage,
                ]);

                if (\is_wp_error($result) || empty($result['files'])) {
                    $running = false;
                }
            } else {
                $running = false;
            }
        }

        $this->updateSyncProgress($accountId, [
            'status'           => 'complete',
            'processedFolders' => $processedFolders,
            'processedFiles'   => $processedFiles,
            'errors'           => $errors,
        ]);

        $this->cleanupSync($accountId);
    }

    private function updateSyncProgress(string $accountId, array $data): void
    {
        \set_transient("ccpigd_syncing_account_{$accountId}", maybe_serialize($data), \HOUR_IN_SECONDS);
    }

    private function cleanupSync(string $accountId): void
    {
        \delete_transient("ccpigd_syncing_account_{$accountId}");
    }
}
