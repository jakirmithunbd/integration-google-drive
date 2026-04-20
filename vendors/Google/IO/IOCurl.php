<?php

namespace CodeConfig\IGD\Google\IO;

use CodeConfig\IGD\Google\Client;
use CodeConfig\IGD\Google\Http\HttpRequest;

/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Curl based implementation of IO.
 *
 * @author Stuart Langley <slangley@google.com>
 */

class IOCurl extends IOAbstract
{
    // cURL hex representation of version 7.30.0
    public const NO_QUIRK_VERSION = 0x071E00;

    private $options = [];

    private $handler;

    /** @var bool $disableProxyWorkaround */
    private $disableProxyWorkaround;

    public function __construct(Client $client)
    {
        if (!extension_loaded('curl')) {
            $error = 'The cURL IO handler requires the cURL extension to be enabled';
            $client->getLogger()->critical($error);
            throw new IOException(esc_html($error));
        }

        parent::__construct($client);

        $this->disableProxyWorkaround = $this->client->getClassConfig(
            'IOCurl',
            'disable_proxy_workaround'
        );
    }

    public function getHandler()
    {

        if (!is_resource($this->handler) || get_resource_type($this->handler) !== 'curl') {
            $this->handler = curl_init();
            // FIX cURL error 2: easy handle already used in multi handle
            //    } elseif (function_exists('curl_reset')){
            //        curl_reset($this->handler);
        } else {
            curl_close($this->handler);
            $this->handler = curl_init();
        }

        return $this->handler;
    }

    /**
     * Execute an HTTP Request
     *
     * @param HttpRequest $request the http request to be executed
     * @return array containing response headers, body, and http code
     * @throws IOException on curl or IO error
     */
    public function executeRequest(HttpRequest $request)
    {

        $curl = $this->getHandler();

        curl_setopt($curl, CURLOPT_POSTFIELDS, null);
        if ($request->getPostBody()) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request->getPostBody());
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, ['x-curl-fix:'  => true]);
        $requestHeaders = $request->getRequestHeaders();
        if ($requestHeaders && is_array($requestHeaders)) {
            $curlHeaders = [];
            foreach ($requestHeaders as $k => $v) {
                $curlHeaders[] = "$k: $v";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
        }
        curl_setopt($curl, CURLOPT_URL, $request->getUrl());

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getRequestMethod());
        curl_setopt($curl, CURLOPT_USERAGENT, $request->getUserAgent());

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        if (defined('FORCE_CURL_HTTP_VERSION_1_1') && FORCE_CURL_HTTP_VERSION_1_1 === true) {
            // Prevent 'Uncaught IOException: HTTP/2 stream 0 was not closed cleanly: PROTOCOL_ERROR (err 1)' errors
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }

        // The SSL version will be determined by the underlying library
        // @see https://github.com/google/google-api-php-client/pull/644
        //curl_setopt($curl, CURLOPT_SSLVERSION, 1);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        curl_setopt($curl, CURLOPT_ENCODING, null);
        if ($request->canGzip()) {
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
        }

        $options = $this->client->getClassConfig('IOCurl', 'options');
        if (is_array($options)) {
            $this->setOptions($options);
        }

        foreach ($this->options as $key => $var) {
            curl_setopt($curl, $key, $var);
        }

        if (!isset($this->options[CURLOPT_CAINFO])) {
            curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . '/cacerts.pem');
        }

        // DEBUG: Verbose
        // curl_setopt($curl, CURLOPT_VERBOSE, true);
        // $verbose = fopen('php://temp', 'w+');
        // curl_setopt($curl, CURLOPT_STDERR, $verbose);

        $this->client->getLogger()->debug(
            'cURL request',
            [
                'url'     => $request->getUrl(),
                'method'  => $request->getRequestMethod(),
                'headers' => $requestHeaders,
                'body'    => $request->getPostBody()
            ]
        );

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            $code  = curl_errno($curl);
            $map   = $this->client->getClassConfig('IOException', 'retry_map');

            $this->client->getLogger()->error('cURL ' . $error);
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new IOException(esc_html($error), intval($code), null, $map);
        }
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        [$responseHeaders, $responseBody]     = $this->parseHttpResponse($response, $headerSize);
        $responseCode                         = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $this->client->getLogger()->debug(
            'cURL response',
            [
                'code'    => $responseCode,
                'headers' => $responseHeaders,
                'body'    => $responseBody,
            ]
        );

        return [$responseBody, $responseHeaders, $responseCode];
    }

    /**
     * Set options that update the transport implementation's behavior.
     * @param $options
     */
    public function setOptions($options)
    {
        $this->options = $options + $this->options;
    }

    /**
     * Set the default options, removing all options set during runtime
     */
    public function setDefaultOptions()
    {
        $this->options = [];

        $timeout = $this->client->getClassConfig('IOAbstract', 'request_timeout_seconds');
        if ($timeout > 0) {
            $this->setTimeout($timeout);
        }

        $options = $this->client->getClassConfig('IOCurl', 'options');
        if (is_array($options)) {
            $this->options = $options + $this->options;
        }
    }

    /**
    * Get options that update the transport implementation's behavior.
     * @param $options
    */
    public function getOptions()
    {
        return $this->options;
    }


    /**
     * Set the maximum request time in seconds.
     * @param $timeout in seconds
     */
    public function setTimeout($timeout)
    {
        // Since this timeout is really for putting a bound on the time
        // we'll set them both to the same. If you need to specify a longer
        // CURLOPT_TIMEOUT, or a higher CONNECTTIMEOUT, the best thing to
        // do is use the setOptions method for the values individually.
        $this->options[CURLOPT_CONNECTTIMEOUT] = $timeout;
        $this->options[CURLOPT_TIMEOUT]        = $timeout;
    }

    /**
     * Get the maximum request time in seconds.
     * @return timeout in seconds
     */
    public function getTimeout()
    {
        return $this->options[CURLOPT_TIMEOUT];
    }

    /**
     * Test for the presence of a cURL header processing bug
     *
     * {@inheritDoc}
     *
     * @return boolean
     */
    protected function needsQuirk()
    {
        if ($this->disableProxyWorkaround) {
            return false;
        }

        $ver        = curl_version();
        $versionNum = $ver['version_number'];

        return $versionNum < IOCurl::NO_QUIRK_VERSION;
    }
}
