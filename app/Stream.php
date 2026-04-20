<?php

namespace CodeConfig\IGD\App;

use CodeConfig\IGD\Models\Notices;
use CodeConfig\IGD\Utils\Helpers;

defined('ABSPATH') || exit;

class Stream
{
    private $file;

    private const MAX_OPEN_RANGE_BYTES = 2 * 1024 * 1024; // 2MB per open-ended request

    public function __construct($key)
    {
        $referrer = wp_get_raw_referer();

        if (empty($referrer) && !empty(Helpers::getSetting('advanced.secureVideoPlayback', false))) {
            $userIP      = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $description = 'A request was made to the streaming endpoint without a valid referrer. User IP: ' . $userIP . ', Video Key: ' . ($key ?? 'none');
            Notices::getInstance()->add([
                'title'       => __('Unauthorized access attempt to streaming endpoint.', 'integration-google-drive'),
                'description' => $description,
                'type'        => 'error',
                'status'      => 401,
                'context'     => 'streaming',
            ]);
            http_response_code(401);
            exit;
        }

        if (empty($key)) {
            $userIP = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            Notices::getInstance()->add([
                'title'       => __('Missing file key to stream.', 'integration-google-drive'),
                'description' => 'A request was made to the streaming endpoint without a valid key. User IP: ' . $userIP,
                'type'        => 'error',
                'status'      => 400,
                'context'     => 'streaming',
            ]);
            http_response_code(400);
            exit;
        }

        $this->streaming($key);
    }

    /**
     * Handle streaming requests.
     */
    private function streaming($key): void
    {
        if (empty($key)) {
            Notices::getInstance()->add([
                'title'   => __('Missing file key to stream.', 'integration-google-drive'),
                'type'    => 'error',
                'status'  => 400,
                'context' => 'streaming',
            ]);
            http_response_code(400);
            exit;
        }

        ignore_user_abort(true);

        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        @set_time_limit(0);

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }

        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();

        // Clean all output buffers so we can stream raw binary
        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->file = ccpigdGetFileByKey($key);

        if (is_wp_error($this->file)) {
            Notices::getInstance()->add([
                'title'   => $this->file->get_error_message(),
                'type'    => 'error',
                'status'  => 400,
                'context' => 'streaming',
            ]);
            http_response_code(400);
            exit;
        }

        if (empty($this->file)) {
            Notices::getInstance()->add([
                'title'   => __('File not found or access denied.', 'integration-google-drive'),
                'type'    => 'error',
                'status'  => 404,
                'context' => 'streaming',
            ]);
            http_response_code(404);
            exit;
        }

        $size   = (int) ($this->file['size'] ?? 0);
        $start  = 0;
        $end    = $size - 1;
        $length = $size;

        // Set basic headers
        header('Accept-Ranges: bytes');
        header('Content-Type: ' . ($this->file['mimeType'] ?? 'application/octet-stream'));
        header('X-Accel-Buffering: no');

        $filename         = basename($this->file['name'] ?? 'video');
        $encoded_filename = rawurlencode($filename);
        header("Content-Disposition: inline; filename=\"$filename\"; filename*=UTF-8''$encoded_filename");

        header_remove('Content-Encoding');
        header_remove('Transfer-Encoding');

        $cache_expiry = HOUR_IN_SECONDS * 4;
        $expires      = gmdate('D, d M Y H:i:s', time() + $cache_expiry) . ' GMT';
        header("Expires: {$expires}");
        header('Pragma: cache');
        header("Cache-Control: max-age=$cache_expiry");

        // Handle range requests
        if (isset($_SERVER['HTTP_RANGE'])) {
            [$range_start, $range_end] = $this->parseRange(
                sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE'] ?? '')),
                $size
            );

            if ($range_start === null || $range_end === null) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes */{$size}");
                exit;
            }

            $start  = $range_start;
            $end    = $range_end;
            $length = $end - $start + 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        } else {
            header('HTTP/1.1 200 OK');
        }

        header("Content-Length: {$length}");

        $this->streamFile($start, $end);
    }

    private function streamFile(int $start, int $end): void
    {
        $bytes_sent = $this->streamGetChunk($start, $end);

        if ($bytes_sent === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("Stream: failed to stream range {$start}-{$end}");
        }

        // Flush any remaining data in PHP's output buffer to the browser
        flush();
    }

    private function streamGetChunk(int $start, int $end): int|false
    {
        $headers = ['Range' => "bytes={$start}-{$end}"];

        if (!empty($this->file['resourceKey'])) {
            $headers['X-Goog-Drive-Resource-Keys'] = $this->file['id'] . '/' . $this->file['resourceKey'];
        }

        $request = new \CodeConfig\IGD\Google\Http\HttpRequest($this->getApiUrl(), 'GET', $headers);
        $request->disableGzip();

        $bytes_written = 0;
        $buffer_count  = 0;
        $client        = Client::getInstance($this->file['accountId'])->getClient();

        // Flush to browser every ~512KB (2 x 256KB curl buffers).
        // Tune down for lower latency, up for fewer syscalls.
        $flush_interval = 2;

        $client->getIo()->setOptions([
            CURLOPT_RETURNTRANSFER  => false,      // stream directly to output, no memory buffering
            CURLOPT_FOLLOWLOCATION  => true,       // follow Google Drive redirects
            CURLOPT_HEADER          => false,      // exclude response headers from body
            CURLOPT_CONNECTTIMEOUT  => 10,         // 10s max to establish connection
            CURLOPT_TIMEOUT         => 0,          // no transfer timeout (large files need time)
            CURLOPT_LOW_SPEED_LIMIT => 32768,      // 32KB/s minimum (was 262144 = 256KB, far too strict)
            CURLOPT_LOW_SPEED_TIME  => 30,         // abort if below limit for 30 consecutive seconds
            CURLOPT_TCP_NODELAY     => 1,          // disable Nagle for lower packet latency
            CURLOPT_BUFFERSIZE      => 256 * 1024, // 256KB per write callback invocation
            CURLOPT_WRITEFUNCTION   => function ($ch, $data) use (&$bytes_written, &$buffer_count, $flush_interval) {
                $data_len = strlen($data);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $data;

                $buffer_count++;
                if ($buffer_count % $flush_interval === 0) {
                    flush();
                }

                $bytes_written += $data_len;

                // Must return exact byte count — any other value aborts the transfer
                return $data_len;
            },
        ]);

        try {
            $client->getAuth()->authenticatedRequest($request);

            return $bytes_written;
        } catch (\Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("Stream chunk error ({$start}-{$end}): " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get Google Drive media endpoint URL.
     */
    private function getApiUrl(): string
    {
        return 'https://www.googleapis.com/drive/v3/files/' . $this->file['id'] . '?alt=media';
    }

    private function parseRange(string $range_header, int $total_size): array
    {
        if (!preg_match('/bytes=(\d*)-(\d*)/', $range_header, $matches)) {
            return [null, null];
        }

        $start         = $matches[1] === '' ? 0 : (int) $matches[1];
        $is_open_ended = $matches[2] === '';
        $end           = $is_open_ended ? $total_size - 1 : (int) $matches[2];

        if ($start < 0 || $start >= $total_size || $end < $start || $end >= $total_size) {
            return [null, null];
        }

        if ($is_open_ended) {
            $end = min($end, $start + self::MAX_OPEN_RANGE_BYTES - 1);
        }

        return [$start, $end];
    }
}
