<?php

namespace CodeConfig\IGD\Google\Cache;

use CodeConfig\IGD\Google\Client;

/*
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
 * A persistent storage class based on the APC cache, which is not
 * really very persistent, as soon as you restart your web server
 * the storage will be wiped, however for debugging and/or speed
 * it can be useful, and cache is a lot cheaper then storage.
 *
 * @author Chris Chabot <chabotc@google.com>
 */
class CacheApc extends CacheAbstract
{
    /**
     * @var Client the current client
     */
    private $client;

    public function __construct(Client $client)
    {
        if (! function_exists('apc_add')) {
            $error = "Apc functions not available";

            $client->getLogger()->error($error);
            throw new CacheException("Apc functions not available");
        }

        $this->client = $client;
    }

    /**
    * @inheritDoc
    */
    public function get($key, $expiration = false)
    {
        $ret = apc_fetch($key);
        if ($ret === false) {
            $this->client->getLogger()->debug(
                'APC cache miss',
                ['key' => $key]
            );

            return false;
        }
        if (is_numeric($expiration) && (time() - $ret['time'] > $expiration)) {
            $this->client->getLogger()->debug(
                'APC cache miss (expired)',
                ['key' => $key, 'var' => $ret]
            );
            $this->delete($key);

            return false;
        }

        $this->client->getLogger()->debug(
            'APC cache hit',
            ['key' => $key, 'var' => $ret]
        );

        return $ret['data'];
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value)
    {
        $var = ['time' => time(), 'data' => $value];
        $rc  = apc_store($key, $var);

        if ($rc == false) {
            $this->client->getLogger()->error(
                'APC cache set failed',
                ['key' => $key, 'var' => $var]
            );
            throw new CacheException("Couldn't store data");
        }

        $this->client->getLogger()->debug(
            'APC cache set',
            ['key' => $key, 'var' => $var]
        );
    }

    /**
     * @inheritDoc
     * @param String $key
     */
    public function delete($key)
    {
        $this->client->getLogger()->debug(
            'APC cache delete',
            ['key' => $key]
        );
        apc_delete($key);
    }
}
