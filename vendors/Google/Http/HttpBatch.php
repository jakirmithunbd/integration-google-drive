<?php

namespace CodeConfig\IGD\Google\Http;

use CodeConfig\IGD\Google\Client;
use CodeConfig\IGD\Google\Service\ServiceException;

/*
 * Copyright 2012 Google Inc.
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
 * Class to handle batched requests to the Google API service.
 */
class HttpBatch
{
    /** @var string Multipart Boundary. */
    private $boundary;

    /** @var array service requests to be executed. */
    private $requests = [];

    /** @var Client */
    private $client;

    private $expected_classes = [];

    private $root_url;

    private $batch_path;

    public function __construct(Client $client, $boundary = false, $rootUrl = '', $batchPath = '')
    {
        $this->client           = $client;
        $this->root_url         = rtrim($rootUrl ? $rootUrl : $this->client->getBasePath(), '/');
        $this->batch_path       = $batchPath ? $batchPath : 'batch/drive/v3';
        $this->expected_classes = [];
        $boundary               = (false == $boundary) ? mt_rand() : $boundary;
        $this->boundary         = str_replace('"', '', $boundary);
    }

    public function add(HttpRequest $request, $key = false)
    {
        if (false == $key) {
            $key = mt_rand();
        }

        $this->requests[$key] = $request;
    }

    public function execute()
    {
        $body = '';

        /** @var HttpRequest $req */
        foreach ($this->requests as $key => $req) {
            $body .= "--{$this->boundary}\n";
            $body .= $req->toBatchString($key) . "\n\n";
            $this->expected_classes["response-" . $key] = $req->getExpectedClass();
        }

        $body .= "--{$this->boundary}--";

        $url         = $this->root_url . '/' . $this->batch_path;
        $httpRequest = new HttpRequest($url, 'POST');
        $httpRequest->setRequestHeaders(
            ['Content-Type' => 'multipart/mixed; boundary=' . $this->boundary]
        );
        $httpRequest->enableGzip();
        $httpRequest->setPostBody($body);
        $response = $this->client->getIo()->makeRequest($httpRequest);

        return $this->parseResponse($response);
    }

    public function parseResponse(HttpRequest $response)
    {
        $contentType = $response->getResponseHeader('content-type');
        $contentType = explode(';', $contentType);
        $boundary    = false;
        foreach ($contentType as $part) {
            $part = (explode('=', $part, 2));
            if (isset($part[0]) && 'boundary' == trim($part[0])) {
                $boundary = $part[1];
            }
        }

        $body = $response->getResponseBody();
        if ($body) {
            $body      = str_replace("--$boundary--", "--$boundary", $body);
            $parts     = explode("--$boundary", $body);
            $responses = [];

            foreach ($parts as $part) {
                $part = trim($part);
                if (!empty($part)) {
                    [$metaHeaders, $part]     = explode("\r\n\r\n", $part, 2);
                    $metaHeaders              = $this->client->getIo()->getHttpResponseHeaders($metaHeaders);

                    $status = substr($part, 0, strpos($part, "\n"));
                    $status = explode(" ", $status);
                    $status = $status[1];

                    list($partHeaders, $partBody) = $this->client->getIo()->ParseHttpResponse($part, false);
                    $response                     = new HttpRequest("");
                    $response->setResponseHttpCode($status);
                    $response->setResponseHeaders($partHeaders);
                    $response->setResponseBody($partBody);

                    // Need content id.
                    $key = $metaHeaders['content-id'];

                    if (isset($this->expected_classes[$key]) &&
                        strlen($this->expected_classes[$key]) > 0) {
                        $class = $this->expected_classes[$key];
                        $response->setExpectedClass($class);
                    }

                    try {
                        $response        = HttpREST::decodeHttpResponse($response, $this->client);
                        $responses[$key] = $response;
                    } catch (ServiceException $e) {
                        // Store the exception as the response, so successful responses
                        // can be processed.
                        $responses[$key] = $e;
                    }
                }
            }

            return $responses;
        }

        return null;
    }
}
