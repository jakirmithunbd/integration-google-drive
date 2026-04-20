<?php

namespace CodeConfig\IGD\Google\Service;

use CodeConfig\IGD\Google\Client;
use CodeConfig\IGD\Google\Model;
use CodeConfig\IGD\Google\Service;

/*
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

/**
 * Service definition for Firebase (v1).
 *
 * <p>
 * Lets you create, inspect, and manage goo.gl short URLs</p>
 *
 * <p>
 * For more information about this service, see the API
 * <a href="https://developers.google.com/url-shortener/v1/getting_started" target="_blank">Documentation</a>
 * </p>
 *
 * @author Google, Inc.
 */
class ServiceFirebase extends Service
{
    /** Manage your goo.gl short URLs. */
    public const FIREBASE =
        "https://firebasedynamiclinks.googleapis.com/v1/shortLinks";

    public $url;
    public $serviceName;


    /**
     * Constructs the internal representation of the Firebase service.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->rootUrl     = 'https://firebasedynamiclinks.googleapis.com';
        $this->servicePath = 'v1/shortLinks';
        $this->version     = 'v1';
        $this->serviceName = 'firebase';

        $this->url = new ServiceFirebaseUrlResource(
            $this,
            $this->serviceName,
            'url',
            [
              'methods' => [
                'insert' => [
                  'path'       => 'url',
                  'httpMethod' => 'POST',
                  'parameters' => [
                    'longDynamicLink' => [
                      'location' => 'query',
                      'type'     => 'string',
                      'required' => true,
                    ],
                    'suffix' => [
                      'location' => 'query',
                      'type'     => 'string',
                    ],
                  ],
                ],
              ]
            ]
        );
    }
}


/**
 * The "url" collection of methods.
 * Typical usage is:
 *  <code>
 *   $firebaseService = new ServiceFirebase(...);
 *   $url = $firebaseService->url;
 *  </code>
 */
class ServiceFirebaseUrlResource extends ServiceResource
{
    /**
     * Creates a new short URL. (url.insert)
     *
     * @param Url $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceFirebaseUrl
     */
    public function insert($longDynamicLink, $params = [])
    {
        $defaultParams = ['longDynamicLink' => $longDynamicLink];

        $params = array_merge($params, $defaultParams);

        return $this->call('insert', [$params], ServiceFirebaseUrl::class);
    }

}



class ServiceFirebaseUrl extends Model
{
    protected $internal_gapi_mappings = [
    ];

    public $shortLink;
    public $previewLink;

    public function setShortLink($shortLink)
    {
        $this->shortLink = $shortLink;
    }
    public function getShortLink()
    {
        return $this->shortLink;
    }
    public function setPreviewLink($previewLink)
    {
        $this->previewLink = $previewLink;
    }
    public function getPreviewLink()
    {
        return $this->previewLink;
    }
}
