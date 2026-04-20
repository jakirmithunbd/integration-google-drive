<?php

namespace CodeConfig\IGD\Google\Service;

use CodeConfig\IGD\Google\Client;
use CodeConfig\IGD\Google\Exception;
use CodeConfig\IGD\Google\Http\HttpMediaFileUpload;
use CodeConfig\IGD\Google\Http\HttpRequest;
use CodeConfig\IGD\Google\Http\HttpREST;
use CodeConfig\IGD\Google\Model;

/**
 * Copyright 2010 Google Inc.
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
 * Implements the actual methods/resources of the discovered Google API using magic function
 * calling overloading (__call()), which on call will see if the method name (plus.activities.list)
 * is available in this service, and if so construct an apiHttpRequest representing it.
 *
 */
class ServiceResource
{
    // Valid query parameters that work, but don't appear in discovery.
    private $stackParameters = [
      'alt'         => ['type' => 'string', 'location' => 'query'],
      'fields'      => ['type' => 'string', 'location' => 'query'],
      'trace'       => ['type' => 'string', 'location' => 'query'],
      'userIp'      => ['type' => 'string', 'location' => 'query'],
      'quotaUser'   => ['type' => 'string', 'location' => 'query'],
      'data'        => ['type' => 'string', 'location' => 'body'],
      'mimeType'    => ['type' => 'string', 'location' => 'header'],
      'uploadType'  => ['type' => 'string', 'location' => 'query'],
      'mediaUpload' => ['type' => 'complex', 'location' => 'query'],
      'prettyPrint' => ['type' => 'string', 'location' => 'query'],
    ];

    /** @var string $rootUrl */
    private $rootUrl;

    /** @var Client $client */
    private $client;

    /** @var string $serviceName */
    private $serviceName;

    /** @var string $servicePath */
    private $servicePath;

    /** @var string $resourceName */
    private $resourceName;

    /** @var array $methods */
    private $methods;

    public function __construct($service, $serviceName, $resourceName, $resource)
    {
        $this->rootUrl      = $service->rootUrl;
        $this->client       = $service->getClient();
        $this->servicePath  = $service->servicePath;
        $this->serviceName  = $serviceName;
        $this->resourceName = $resourceName;
        $this->methods      = is_array($resource) && isset($resource['methods']) ?
          $resource['methods'] :
          [$resourceName => $resource];
    }

    /**
     * TODO: This function needs simplifying.
     * @param $name
     * @param $arguments
     * @param null|string $expected_class - optional, the expected class name
     * @return HttpRequest|mixed
     * @throws Exception
     */
    public function call($name, $arguments, $expected_class = null)
    {
        if (! isset($this->methods[$name])) {
            $this->client->getLogger()->error(
                'Service method unknown',
                [
                'service'  => $this->serviceName,
                'resource' => $this->resourceName,
                'method'   => $name
        ]
            );

            throw new Exception(
                "Unknown function: " .
                esc_html($this->serviceName)."->".esc_html($this->resourceName)."->".esc_html($name)."()"
            );
        }
        $method     = $this->methods[$name];
        $parameters = $arguments[0];

        // postBody is a special case since it's not defined in the discovery
        // document as parameter, but we abuse the param entry for storing it.
        $postBody = null;
        if (isset($parameters['postBody'])) {
            if ($parameters['postBody'] instanceof Model) {
                // In the cases the post body is an existing object, we want
                // to use the smart method to create a simple object for
                // for JSONification.
                $parameters['postBody'] = $parameters['postBody']->toSimpleObject();
            } elseif (is_object($parameters['postBody'])) {
                // If the post body is another kind of object, we will try and
                // wrangle it into a sensible format.
                $parameters['postBody'] =
                  $this->convertToArrayAndStripNulls($parameters['postBody']);
            }
            $postBody = wp_json_encode($parameters['postBody']);
            if ($postBody === false && $parameters['postBody'] !== false) {
                throw new Exception("JSON encoding failed. Ensure all strings in the request are UTF-8 encoded.");
            }
            unset($parameters['postBody']);
        }

        // TODO: optParams here probably should have been
        // handled already - this may well be redundant code.
        if (isset($parameters['optParams'])) {
            $optParams = $parameters['optParams'];
            unset($parameters['optParams']);
            $parameters = array_merge($parameters, $optParams);
        }

        if (!isset($method['parameters'])) {
            $method['parameters'] = [];
        }

        $method['parameters'] = array_merge(
            $this->stackParameters,
            $method['parameters']
        );
        foreach ($parameters as $key => $val) {
            if ($key != 'postBody' && ! isset($method['parameters'][$key])) {
                $this->client->getLogger()->error(
                    'Service parameter unknown',
                    [
                    'service'   => $this->serviceName,
                    'resource'  => $this->resourceName,
                    'method'    => $name,
                    'parameter' => $key
            ]
                );
                throw new Exception("(".esc_html($name).") unknown parameter: '".esc_html($key)."'");
            }
        }

        foreach ($method['parameters'] as $paramName => $paramSpec) {
            if (
                isset($paramSpec['required']) &&
                $paramSpec['required']        &&
                ! isset($parameters[$paramName])
            ) {
                $this->client->getLogger()->error(
                    'Service parameter missing',
                    [
                    'service'   => $this->serviceName,
                    'resource'  => $this->resourceName,
                    'method'    => $name,
                    'parameter' => $paramName
            ]
                );
                throw new Exception("(".esc_html($name).") missing required param: '".esc_html($paramName)."'");
            }
            if (isset($parameters[$paramName])) {
                $value                           = $parameters[$paramName];
                $parameters[$paramName]          = $paramSpec;
                $parameters[$paramName]['value'] = $value;
                unset($parameters[$paramName]['required']);
            } else {
                // Ensure we don't pass nulls.
                unset($parameters[$paramName]);
            }
        }

        $this->client->getLogger()->info(
            'Service Call',
            [
            'service'   => $this->serviceName,
            'resource'  => $this->resourceName,
            'method'    => $name,
            'arguments' => $parameters,
      ]
        );

        $url = HttpREST::createRequestUri(
            $this->servicePath,
            $method['path'],
            $parameters
        );
        $httpRequest = new HttpRequest(
            $url,
            $method['httpMethod'],
            null,
            $postBody
        );

        if ($this->rootUrl) {
            $httpRequest->setBaseComponent($this->rootUrl);
        } else {
            $httpRequest->setBaseComponent($this->client->getBasePath());
        }

        if ($postBody) {
            $contentTypeHeader                 = [];
            $contentTypeHeader['content-type'] = 'application/json; charset=UTF-8';
            $httpRequest->setRequestHeaders($contentTypeHeader);
            $httpRequest->setPostBody($postBody);
        }

        $httpRequest = $this->client->getAuth()->sign($httpRequest);
        $httpRequest->setExpectedClass($expected_class);

        if (
            isset($parameters['data']) &&
            ($parameters['uploadType']['value'] == 'media' || $parameters['uploadType']['value'] == 'multipart')
        ) {
            // If we are doing a simple media upload, trigger that as a convenience.
            $mfu = new HttpMediaFileUpload(
                $this->client,
                $httpRequest,
                isset($parameters['mimeType']) ? $parameters['mimeType']['value'] : 'application/octet-stream',
                $parameters['data']['value']
            );
        }

        if (isset($parameters['alt']) && $parameters['alt']['value'] == 'media') {
            $httpRequest->enableExpectedRaw();
        }

        if ($this->client->shouldDefer()) {
            // If we are in batch or upload mode, return the raw request.
            return $httpRequest;
        }

        return $this->client->execute($httpRequest);
    }

    protected function convertToArrayAndStripNulls($o)
    {
        $o = (array) $o;
        foreach ($o as $k => $v) {
            if ($v === null) {
                unset($o[$k]);
            } elseif (is_object($v) || is_array($v)) {
                $o[$k] = $this->convertToArrayAndStripNulls($o[$k]);
            }
        }

        return $o;
    }
}
