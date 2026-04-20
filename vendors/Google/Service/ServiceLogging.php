<?php

namespace CodeConfig\IGD\Google\Service;

use CodeConfig\IGD\Google\Client;
use CodeConfig\IGD\Google\Collection;
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
 * Service definition for Logging (v2beta1).
 *
 * <p>
 * The Google Cloud Logging API lets you write log entries and manage your logs,
 * log sinks and logs-based metrics.</p>
 *
 * <p>
 * For more information about this service, see the API
 * <a href="https://cloud.google.com/logging/docs/" target="_blank">Documentation</a>
 * </p>
 *
 * @author Google, Inc.
 */
class ServiceLogging extends Service
{
    /** View and manage your data across Google Cloud Platform services. */
    public const CLOUD_PLATFORM =
        "https://www.googleapis.com/auth/cloud-platform";
    /** View your data across Google Cloud Platform services. */
    public const CLOUD_PLATFORM_READ_ONLY =
        "https://www.googleapis.com/auth/cloud-platform.read-only";
    /** Administrate log data for your projects. */
    public const LOGGING_ADMIN =
        "https://www.googleapis.com/auth/logging.admin";
    /** View log data for your projects. */
    public const LOGGING_READ =
        "https://www.googleapis.com/auth/logging.read";
    /** Submit log data for your projects. */
    public const LOGGING_WRITE =
        "https://www.googleapis.com/auth/logging.write";

    public $entries;
    public $monitoredResourceDescriptors;
    public $projects_logs;
    public $projects_metrics;
    public $projects_sinks;


    /**
     * Constructs the internal representation of the Logging service.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->rootUrl     = 'https://logging.googleapis.com/';
        $this->servicePath = '';
        $this->version     = 'v2beta1';
        $this->serviceName = 'logging';

        $this->entries = new ServiceLoggingEntriesResource(
            $this,
            $this->serviceName,
            'entries',
            [
              'methods' => [
                'list' => [
                  'path'       => 'v2beta1/entries:list',
                  'httpMethod' => 'POST',
                  'parameters' => [],
                ],'write' => [
                  'path'       => 'v2beta1/entries:write',
                  'httpMethod' => 'POST',
                  'parameters' => [],
                ],
              ]
            ]
        );
        $this->monitoredResourceDescriptors = new ServiceLoggingMonitoredResourceDescriptorsResource(
            $this,
            $this->serviceName,
            'monitoredResourceDescriptors',
            [
              'methods' => [
                'list' => [
                  'path'       => 'v2beta1/monitoredResourceDescriptors',
                  'httpMethod' => 'GET',
                  'parameters' => [
                    'pageSize' => [
                      'location' => 'query',
                      'type'     => 'integer',
                    ],
                    'pageToken' => [
                      'location' => 'query',
                      'type'     => 'string',
                    ],
                  ],
                ],
              ]
            ]
        );
        $this->projects_logs = new ServiceLoggingProjectsLogsResource(
            $this,
            $this->serviceName,
            'logs',
            [
              'methods' => [
                'delete' => [
                  'path'       => 'v2beta1/{+logName}',
                  'httpMethod' => 'DELETE',
                  'parameters' => [
                    'logName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],
              ]
            ]
        );
        $this->projects_metrics = new ServiceLoggingProjectsMetricsResource(
            $this,
            $this->serviceName,
            'metrics',
            [
              'methods' => [
                'create' => [
                  'path'       => 'v2beta1/{+projectName}/metrics',
                  'httpMethod' => 'POST',
                  'parameters' => [
                    'projectName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'delete' => [
                  'path'       => 'v2beta1/{+metricName}',
                  'httpMethod' => 'DELETE',
                  'parameters' => [
                    'metricName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'get' => [
                  'path'       => 'v2beta1/{+metricName}',
                  'httpMethod' => 'GET',
                  'parameters' => [
                    'metricName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'list' => [
                  'path'       => 'v2beta1/{+projectName}/metrics',
                  'httpMethod' => 'GET',
                  'parameters' => [
                    'projectName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                    'pageToken' => [
                      'location' => 'query',
                      'type'     => 'string',
                    ],
                    'pageSize' => [
                      'location' => 'query',
                      'type'     => 'integer',
                    ],
                  ],
                ],'update' => [
                  'path'       => 'v2beta1/{+metricName}',
                  'httpMethod' => 'PUT',
                  'parameters' => [
                    'metricName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],
              ]
            ]
        );
        $this->projects_sinks = new ServiceLoggingProjectsSinksResource(
            $this,
            $this->serviceName,
            'sinks',
            [
              'methods' => [
                'create' => [
                  'path'       => 'v2beta1/{+projectName}/sinks',
                  'httpMethod' => 'POST',
                  'parameters' => [
                    'projectName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'delete' => [
                  'path'       => 'v2beta1/{+sinkName}',
                  'httpMethod' => 'DELETE',
                  'parameters' => [
                    'sinkName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'get' => [
                  'path'       => 'v2beta1/{+sinkName}',
                  'httpMethod' => 'GET',
                  'parameters' => [
                    'sinkName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],'list' => [
                  'path'       => 'v2beta1/{+projectName}/sinks',
                  'httpMethod' => 'GET',
                  'parameters' => [
                    'projectName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                    'pageToken' => [
                      'location' => 'query',
                      'type'     => 'string',
                    ],
                    'pageSize' => [
                      'location' => 'query',
                      'type'     => 'integer',
                    ],
                  ],
                ],'update' => [
                  'path'       => 'v2beta1/{+sinkName}',
                  'httpMethod' => 'PUT',
                  'parameters' => [
                    'sinkName' => [
                      'location' => 'path',
                      'type'     => 'string',
                      'required' => true,
                    ],
                  ],
                ],
              ]
            ]
        );
    }
}


/**
 * The "entries" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $entries = $loggingService->entries;
 *  </code>
 */
class ServiceLoggingEntriesResource extends ServiceResource
{
    /**
     * Lists log entries. Use this method to retrieve log entries from Cloud
     * Logging. For ways to export log entries, see [Exporting
     * Logs](/logging/docs/export). (entries.listEntries)
     *
     * @param ListLogEntriesRequest $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingListLogEntriesResponse
     */
    public function listEntries(ServiceLoggingListLogEntriesRequest $postBody, $optParams = [])
    {
        $params = ['postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('list', [$params], ServiceLoggingListLogEntriesResponse::class);
    }

    /**
     * Writes log entries to Cloud Logging. All log entries in Cloud Logging are
     * written by this method. (entries.write)
     *
     * @param WriteLogEntriesRequest $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingWriteLogEntriesResponse
     */
    public function write(ServiceLoggingWriteLogEntriesRequest $postBody, $optParams = [])
    {
        $params = ['postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('write', [$params], ServiceLoggingWriteLogEntriesResponse::class);
    }
}

/**
 * The "monitoredResourceDescriptors" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $monitoredResourceDescriptors = $loggingService->monitoredResourceDescriptors;
 *  </code>
 */
class ServiceLoggingMonitoredResourceDescriptorsResource extends ServiceResource
{
    /**
     * Lists monitored resource descriptors that are used by Cloud Logging.
     * (monitoredResourceDescriptors.listMonitoredResourceDescriptors)
     *
     * @param array $optParams Optional parameters.
     *
     * @opt_param int pageSize Optional. The maximum number of results to return
     * from this request. Fewer results might be returned. You must check for the
     * `nextPageToken` result to determine if additional results are available,
     * which you can retrieve by passing the `nextPageToken` value in the
     * `pageToken` parameter to the next request.
     * @opt_param string pageToken Optional. If the `pageToken` request parameter is
     * supplied, then the next page of results in the set are retrieved. The
     * `pageToken` parameter must be set with the value of the `nextPageToken`
     * result parameter from the previous request.
     * @return ServiceLoggingListMonitoredResourceDescriptorsResponse
     */
    public function listMonitoredResourceDescriptors($optParams = [])
    {
        $params = [];
        $params = array_merge($params, $optParams);

        return $this->call('list', [$params], ServiceLoggingListMonitoredResourceDescriptorsResponse::class);
    }
}

/**
 * The "projects" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $projects = $loggingService->projects;
 *  </code>
 */
class ServiceLoggingProjectsResource extends ServiceResource
{
}

/**
 * The "logs" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $logs = $loggingService->logs;
 *  </code>
 */
class ServiceLoggingProjectsLogsResource extends ServiceResource
{
    /**
     * Deletes a log and all its log entries. The log will reappear if it receives
     * new entries. (logs.delete)
     *
     * @param string $logName Required. The resource name of the log to delete.
     *                        Example: `"projects/my-project/logs/syslog"`.
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingEmpty
     */
    public function delete($logName, $optParams = [])
    {
        $params = ['logName' => $logName];
        $params = array_merge($params, $optParams);

        return $this->call('delete', [$params], ServiceLoggingEmpty::class);
    }
}
/**
 * The "metrics" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $metrics = $loggingService->metrics;
 *  </code>
 */
class ServiceLoggingProjectsMetricsResource extends ServiceResource
{
    /**
     * Creates a logs-based metric. (metrics.create)
     *
     * @param string $projectName The resource name of the project in which to
     *                            create the metric. Example: `"projects/my-project-id"`. The new metric must
     *                            be provided in the request.
     * @param LogMetric $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogMetric
     */
    public function create($projectName, ServiceLoggingLogMetric $postBody, $optParams = [])
    {
        $params = ['projectName' => $projectName, 'postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('create', [$params], ServiceLoggingLogMetric::class);
    }

    /**
     * Deletes a logs-based metric. (metrics.delete)
     *
     * @param string $metricName The resource name of the metric to delete. Example:
     *                           `"projects/my-project-id/metrics/my-metric-id"`.
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingEmpty
     */
    public function delete($metricName, $optParams = [])
    {
        $params = ['metricName' => $metricName];
        $params = array_merge($params, $optParams);

        return $this->call('delete', [$params], ServiceLoggingEmpty::class);
    }

    /**
     * Gets a logs-based metric. (metrics.get)
     *
     * @param string $metricName The resource name of the desired metric. Example:
     *                           `"projects/my-project-id/metrics/my-metric-id"`.
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogMetric
     */
    public function get($metricName, $optParams = [])
    {
        $params = ['metricName' => $metricName];
        $params = array_merge($params, $optParams);

        return $this->call('get', [$params], ServiceLoggingLogMetric::class);
    }

    /**
     * Lists logs-based metrics. (metrics.listProjectsMetrics)
     *
     * @param string $projectName Required. The resource name of the project
     *                            containing the metrics. Example: `"projects/my-project-id"`.
     * @param array $optParams Optional parameters.
     *
     * @opt_param string pageToken Optional. If the `pageToken` request parameter is
     * supplied, then the next page of results in the set are retrieved. The
     * `pageToken` parameter must be set with the value of the `nextPageToken`
     * result parameter from the previous request. The value of `projectName` must
     * be the same as in the previous request.
     * @opt_param int pageSize Optional. The maximum number of results to return
     * from this request. Fewer results might be returned. You must check for the
     * `nextPageToken` result to determine if additional results are available,
     * which you can retrieve by passing the `nextPageToken` value in the
     * `pageToken` parameter to the next request.
     * @return ServiceLoggingListLogMetricsResponse
     */
    public function listProjectsMetrics($projectName, $optParams = [])
    {
        $params = ['projectName' => $projectName];
        $params = array_merge($params, $optParams);

        return $this->call('list', [$params], ServiceLoggingListLogMetricsResponse::class);
    }

    /**
     * Creates or updates a logs-based metric. (metrics.update)
     *
     * @param string $metricName The resource name of the metric to update. Example:
     *                           `"projects/my-project-id/metrics/my-metric-id"`. The updated metric must be
     *                           provided in the request and have the same identifier that is specified in
     *                           `metricName`. If the metric does not exist, it is created.
     * @param LogMetric $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogMetric
     */
    public function update($metricName, ServiceLoggingLogMetric $postBody, $optParams = [])
    {
        $params = ['metricName' => $metricName, 'postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('update', [$params], ServiceLoggingLogMetric::class);
    }
}
/**
 * The "sinks" collection of methods.
 * Typical usage is:
 *  <code>
 *   $loggingService = new ServiceLogging(...);
 *   $sinks = $loggingService->sinks;
 *  </code>
 */
class ServiceLoggingProjectsSinksResource extends ServiceResource
{
    /**
     * Creates a sink. (sinks.create)
     *
     * @param string $projectName The resource name of the project in which to
     *                            create the sink. Example: `"projects/my-project-id"`. The new sink must be
     *                            provided in the request.
     * @param LogSink $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogSink
     */
    public function create($projectName, ServiceLoggingLogSink $postBody, $optParams = [])
    {
        $params = ['projectName' => $projectName, 'postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('create', [$params], ServiceLoggingLogSink::class);
    }

    /**
     * Deletes a sink. (sinks.delete)
     *
     * @param string $sinkName The resource name of the sink to delete. Example:
     *                         `"projects/my-project-id/sinks/my-sink-id"`.
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingEmpty
     */
    public function delete($sinkName, $optParams = [])
    {
        $params = ['sinkName' => $sinkName];
        $params = array_merge($params, $optParams);

        return $this->call('delete', [$params], ServiceLoggingEmpty::class);
    }

    /**
     * Gets a sink. (sinks.get)
     *
     * @param string $sinkName The resource name of the sink to return. Example:
     *                         `"projects/my-project-id/sinks/my-sink-id"`.
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogSink
     */
    public function get($sinkName, $optParams = [])
    {
        $params = ['sinkName' => $sinkName];
        $params = array_merge($params, $optParams);

        return $this->call('get', [$params], ServiceLoggingLogSink::class);
    }

    /**
     * Lists sinks. (sinks.listProjectsSinks)
     *
     * @param string $projectName Required. The resource name of the project
     *                            containing the sinks. Example: `"projects/my-logging-project"`,
     *                            `"projects/01234567890"`.
     * @param array $optParams Optional parameters.
     *
     * @opt_param string pageToken Optional. If the `pageToken` request parameter is
     * supplied, then the next page of results in the set are retrieved. The
     * `pageToken` parameter must be set with the value of the `nextPageToken`
     * result parameter from the previous request. The value of `projectName` must
     * be the same as in the previous request.
     * @opt_param int pageSize Optional. The maximum number of results to return
     * from this request. Fewer results might be returned. You must check for the
     * `nextPageToken` result to determine if additional results are available,
     * which you can retrieve by passing the `nextPageToken` value in the
     * `pageToken` parameter to the next request.
     * @return ServiceLoggingListSinksResponse
     */
    public function listProjectsSinks($projectName, $optParams = [])
    {
        $params = ['projectName' => $projectName];
        $params = array_merge($params, $optParams);

        return $this->call('list', [$params], ServiceLoggingListSinksResponse::class);
    }

    /**
     * Creates or updates a sink. (sinks.update)
     *
     * @param string $sinkName The resource name of the sink to update. Example:
     *                         `"projects/my-project-id/sinks/my-sink-id"`. The updated sink must be
     *                         provided in the request and have the same name that is specified in
     *                         `sinkName`. If the sink does not exist, it is created.
     * @param LogSink $postBody
     * @param array $optParams Optional parameters.
     * @return ServiceLoggingLogSink
     */
    public function update($sinkName, ServiceLoggingLogSink $postBody, $optParams = [])
    {
        $params = ['sinkName' => $sinkName, 'postBody' => $postBody];
        $params = array_merge($params, $optParams);

        return $this->call('update', [$params], ServiceLoggingLogSink::class);
    }
}




class ServiceLoggingEmpty extends Model
{
}

class ServiceLoggingHttpRequest extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $cacheHit;
    public $referer;
    public $remoteIp;
    public $requestMethod;
    public $requestSize;
    public $requestUrl;
    public $responseSize;
    public $status;
    public $userAgent;
    public $validatedWithOriginServer;


    public function setCacheHit($cacheHit)
    {
        $this->cacheHit = $cacheHit;
    }
    public function getCacheHit()
    {
        return $this->cacheHit;
    }
    public function setReferer($referer)
    {
        $this->referer = $referer;
    }
    public function getReferer()
    {
        return $this->referer;
    }
    public function setRemoteIp($remoteIp)
    {
        $this->remoteIp = $remoteIp;
    }
    public function getRemoteIp()
    {
        return $this->remoteIp;
    }
    public function setRequestMethod($requestMethod)
    {
        $this->requestMethod = $requestMethod;
    }
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }
    public function setRequestSize($requestSize)
    {
        $this->requestSize = $requestSize;
    }
    public function getRequestSize()
    {
        return $this->requestSize;
    }
    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;
    }
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }
    public function setResponseSize($responseSize)
    {
        $this->responseSize = $responseSize;
    }
    public function getResponseSize()
    {
        return $this->responseSize;
    }
    public function setStatus($status)
    {
        $this->status = $status;
    }
    public function getStatus()
    {
        return $this->status;
    }
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }
    public function getUserAgent()
    {
        return $this->userAgent;
    }
    public function setValidatedWithOriginServer($validatedWithOriginServer)
    {
        $this->validatedWithOriginServer = $validatedWithOriginServer;
    }
    public function getValidatedWithOriginServer()
    {
        return $this->validatedWithOriginServer;
    }
}

class ServiceLoggingLabelDescriptor extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $description;
    public $key;
    public $valueType;


    public function setDescription($description)
    {
        $this->description = $description;
    }
    public function getDescription()
    {
        return $this->description;
    }
    public function setKey($key)
    {
        $this->key = $key;
    }
    public function getKey()
    {
        return $this->key;
    }
    public function setValueType($valueType)
    {
        $this->valueType = $valueType;
    }
    public function getValueType()
    {
        return $this->valueType;
    }
}

class ServiceLoggingListLogEntriesRequest extends Collection
{
    protected $collection_key         = 'projectIds';
    protected $internal_gapi_mappings = [
    ];
    public $filter;
    public $orderBy;
    public $pageSize;
    public $pageToken;
    public $projectIds;


    public function setFilter($filter)
    {
        $this->filter = $filter;
    }
    public function getFilter()
    {
        return $this->filter;
    }
    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
    }
    public function getOrderBy()
    {
        return $this->orderBy;
    }
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
    }
    public function getPageSize()
    {
        return $this->pageSize;
    }
    public function setPageToken($pageToken)
    {
        $this->pageToken = $pageToken;
    }
    public function getPageToken()
    {
        return $this->pageToken;
    }
    public function setProjectIds($projectIds)
    {
        $this->projectIds = $projectIds;
    }
    public function getProjectIds()
    {
        return $this->projectIds;
    }
}

class ServiceLoggingListLogEntriesResponse extends Collection
{
    protected $collection_key         = 'entries';
    protected $internal_gapi_mappings = [
    ];
    protected $entriesType     = ServiceLoggingLogEntry::class;
    protected $entriesDataType = 'array';
    public $nextPageToken;


    public function setEntries($entries)
    {
        $this->entries = $entries;
    }
    public function getEntries()
    {
        return $this->entries;
    }
    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
}

class ServiceLoggingListLogMetricsResponse extends Collection
{
    protected $collection_key         = 'metrics';
    protected $internal_gapi_mappings = [
    ];
    protected $metricsType     = ServiceLoggingLogMetric::class;
    protected $metricsDataType = 'array';
    public $nextPageToken;


    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
    }
    public function getMetrics()
    {
        return $this->metrics;
    }
    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
}

class ServiceLoggingListMonitoredResourceDescriptorsResponse extends Collection
{
    protected $collection_key         = 'resourceDescriptors';
    protected $internal_gapi_mappings = [
    ];
    public $nextPageToken;
    protected $resourceDescriptorsType     = ServiceLoggingMonitoredResourceDescriptor::class;
    protected $resourceDescriptorsDataType = 'array';


    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
    public function setResourceDescriptors($resourceDescriptors)
    {
        $this->resourceDescriptors = $resourceDescriptors;
    }
    public function getResourceDescriptors()
    {
        return $this->resourceDescriptors;
    }
}

class ServiceLoggingListSinksResponse extends Collection
{
    protected $collection_key         = 'sinks';
    protected $internal_gapi_mappings = [
    ];
    public $nextPageToken;
    protected $sinksType     = ServiceLoggingLogSink::class;
    protected $sinksDataType = 'array';


    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
    public function setSinks($sinks)
    {
        $this->sinks = $sinks;
    }
    public function getSinks()
    {
        return $this->sinks;
    }
}

class ServiceLoggingLogEntry extends Model
{
    protected $internal_gapi_mappings = [
    ];
    protected $httpRequestType     = ServiceLoggingHttpRequest::class;
    protected $httpRequestDataType = '';
    public $insertId;
    public $jsonPayload;
    public $labels;
    public $logName;
    protected $operationType     = ServiceLoggingLogEntryOperation::class;
    protected $operationDataType = '';
    public $protoPayload;
    protected $resourceType     = ServiceLoggingMonitoredResource::class;
    protected $resourceDataType = '';
    public $severity;
    public $textPayload;
    public $timestamp;


    public function setHttpRequest(ServiceLoggingHttpRequest $httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }
    public function getHttpRequest()
    {
        return $this->httpRequest;
    }
    public function setInsertId($insertId)
    {
        $this->insertId = $insertId;
    }
    public function getInsertId()
    {
        return $this->insertId;
    }
    public function setJsonPayload($jsonPayload)
    {
        $this->jsonPayload = $jsonPayload;
    }
    public function getJsonPayload()
    {
        return $this->jsonPayload;
    }
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
    public function getLabels()
    {
        return $this->labels;
    }
    public function setLogName($logName)
    {
        $this->logName = $logName;
    }
    public function getLogName()
    {
        return $this->logName;
    }
    public function setOperation(ServiceLoggingLogEntryOperation $operation)
    {
        $this->operation = $operation;
    }
    public function getOperation()
    {
        return $this->operation;
    }
    public function setProtoPayload($protoPayload)
    {
        $this->protoPayload = $protoPayload;
    }
    public function getProtoPayload()
    {
        return $this->protoPayload;
    }
    public function setResource(ServiceLoggingMonitoredResource $resource)
    {
        $this->resource = $resource;
    }
    public function getResource()
    {
        return $this->resource;
    }
    public function setSeverity($severity)
    {
        $this->severity = $severity;
    }
    public function getSeverity()
    {
        return $this->severity;
    }
    public function setTextPayload($textPayload)
    {
        $this->textPayload = $textPayload;
    }
    public function getTextPayload()
    {
        return $this->textPayload;
    }
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}

class ServiceLoggingLogEntryOperation extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $first;
    public $id;
    public $last;
    public $producer;


    public function setFirst($first)
    {
        $this->first = $first;
    }
    public function getFirst()
    {
        return $this->first;
    }
    public function setId($id)
    {
        $this->id = $id;
    }
    public function getId()
    {
        return $this->id;
    }
    public function setLast($last)
    {
        $this->last = $last;
    }
    public function getLast()
    {
        return $this->last;
    }
    public function setProducer($producer)
    {
        $this->producer = $producer;
    }
    public function getProducer()
    {
        return $this->producer;
    }
}

class ServiceLoggingLogLine extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $logMessage;
    public $severity;
    protected $sourceLocationType     = ServiceLoggingSourceLocation::class;
    protected $sourceLocationDataType = '';
    public $time;

    public function setLogMessage($logMessage)
    {
        $this->logMessage = $logMessage;
    }
    public function getLogMessage()
    {
        return $this->logMessage;
    }
    public function setSeverity($severity)
    {
        $this->severity = $severity;
    }
    public function getSeverity()
    {
        return $this->severity;
    }
    public function setSourceLocation(ServiceLoggingSourceLocation $sourceLocation)
    {
        $this->sourceLocation = $sourceLocation;
    }
    public function getSourceLocation()
    {
        return $this->sourceLocation;
    }
    public function setTime($time)
    {
        $this->time = $time;
    }
    public function getTime()
    {
        return $this->time;
    }
}

class ServiceLoggingLogMetric extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $description;
    public $filter;
    public $name;


    public function setDescription($description)
    {
        $this->description = $description;
    }
    public function getDescription()
    {
        return $this->description;
    }
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }
    public function getFilter()
    {
        return $this->filter;
    }
    public function setName($name)
    {
        $this->name = $name;
    }
    public function getName()
    {
        return $this->name;
    }
}

class ServiceLoggingLogSink extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $destination;
    public $filter;
    public $name;
    public $outputVersionFormat;


    public function setDestination($destination)
    {
        $this->destination = $destination;
    }
    public function getDestination()
    {
        return $this->destination;
    }
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }
    public function getFilter()
    {
        return $this->filter;
    }
    public function setName($name)
    {
        $this->name = $name;
    }
    public function getName()
    {
        return $this->name;
    }
    public function setOutputVersionFormat($outputVersionFormat)
    {
        $this->outputVersionFormat = $outputVersionFormat;
    }
    public function getOutputVersionFormat()
    {
        return $this->outputVersionFormat;
    }
}

class ServiceLoggingMonitoredResource extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $labels;
    public $type;


    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
    public function getLabels()
    {
        return $this->labels;
    }
    public function setType($type)
    {
        $this->type = $type;
    }
    public function getType()
    {
        return $this->type;
    }
}

class ServiceLoggingMonitoredResourceDescriptor extends Collection
{
    protected $collection_key         = 'labels';
    protected $internal_gapi_mappings = [
    ];
    public $description;
    public $displayName;
    protected $labelsType     = ServiceLoggingLabelDescriptor::class;
    protected $labelsDataType = 'array';
    public $type;


    public function setDescription($description)
    {
        $this->description = $description;
    }
    public function getDescription()
    {
        return $this->description;
    }
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
    }
    public function getDisplayName()
    {
        return $this->displayName;
    }
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
    public function getLabels()
    {
        return $this->labels;
    }
    public function setType($type)
    {
        $this->type = $type;
    }
    public function getType()
    {
        return $this->type;
    }
}

class ServiceLoggingRequestLog extends Collection
{
    protected $collection_key         = 'sourceReference';
    protected $internal_gapi_mappings = [
    ];
    public $appEngineRelease;
    public $appId;
    public $cost;
    public $endTime;
    public $finished;
    public $host;
    public $httpVersion;
    public $instanceId;
    public $instanceIndex;
    public $ip;
    public $latency;
    protected $lineType     = ServiceLoggingLogLine::class;
    protected $lineDataType = 'array';
    public $megaCycles;
    public $method;
    public $moduleId;
    public $nickname;
    public $pendingTime;
    public $referrer;
    public $requestId;
    public $resource;
    public $responseSize;
    protected $sourceReferenceType     = ServiceLoggingSourceReference::class;
    protected $sourceReferenceDataType = 'array';
    public $startTime;
    public $status;
    public $taskName;
    public $taskQueueName;
    public $traceId;
    public $urlMapEntry;
    public $userAgent;
    public $versionId;
    public $wasLoadingRequest;

    public function setAppEngineRelease($appEngineRelease)
    {
        $this->appEngineRelease = $appEngineRelease;
    }
    public function getAppEngineRelease()
    {
        return $this->appEngineRelease;
    }
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }
    public function getAppId()
    {
        return $this->appId;
    }
    public function setCost($cost)
    {
        $this->cost = $cost;
    }
    public function getCost()
    {
        return $this->cost;
    }
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }
    public function getEndTime()
    {
        return $this->endTime;
    }
    public function setFinished($finished)
    {
        $this->finished = $finished;
    }
    public function getFinished()
    {
        return $this->finished;
    }
    public function setHost($host)
    {
        $this->host = $host;
    }
    public function getHost()
    {
        return $this->host;
    }
    public function setHttpVersion($httpVersion)
    {
        $this->httpVersion = $httpVersion;
    }
    public function getHttpVersion()
    {
        return $this->httpVersion;
    }
    public function setInstanceId($instanceId)
    {
        $this->instanceId = $instanceId;
    }
    public function getInstanceId()
    {
        return $this->instanceId;
    }
    public function setInstanceIndex($instanceIndex)
    {
        $this->instanceIndex = $instanceIndex;
    }
    public function getInstanceIndex()
    {
        return $this->instanceIndex;
    }
    public function setIp($ip)
    {
        $this->ip = $ip;
    }
    public function getIp()
    {
        return $this->ip;
    }
    public function setLatency($latency)
    {
        $this->latency = $latency;
    }
    public function getLatency()
    {
        return $this->latency;
    }
    public function setLine($line)
    {
        $this->line = $line;
    }
    public function getLine()
    {
        return $this->line;
    }
    public function setMegaCycles($megaCycles)
    {
        $this->megaCycles = $megaCycles;
    }
    public function getMegaCycles()
    {
        return $this->megaCycles;
    }
    public function setMethod($method)
    {
        $this->method = $method;
    }
    public function getMethod()
    {
        return $this->method;
    }
    public function setModuleId($moduleId)
    {
        $this->moduleId = $moduleId;
    }
    public function getModuleId()
    {
        return $this->moduleId;
    }
    public function setNickname($nickname)
    {
        $this->nickname = $nickname;
    }
    public function getNickname()
    {
        return $this->nickname;
    }
    public function setPendingTime($pendingTime)
    {
        $this->pendingTime = $pendingTime;
    }
    public function getPendingTime()
    {
        return $this->pendingTime;
    }
    public function setReferrer($referrer)
    {
        $this->referrer = $referrer;
    }
    public function getReferrer()
    {
        return $this->referrer;
    }
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }
    public function getRequestId()
    {
        return $this->requestId;
    }
    public function setResource($resource)
    {
        $this->resource = $resource;
    }
    public function getResource()
    {
        return $this->resource;
    }
    public function setResponseSize($responseSize)
    {
        $this->responseSize = $responseSize;
    }
    public function getResponseSize()
    {
        return $this->responseSize;
    }
    public function setSourceReference($sourceReference)
    {
        $this->sourceReference = $sourceReference;
    }
    public function getSourceReference()
    {
        return $this->sourceReference;
    }
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }
    public function getStartTime()
    {
        return $this->startTime;
    }
    public function setStatus($status)
    {
        $this->status = $status;
    }
    public function getStatus()
    {
        return $this->status;
    }
    public function setTaskName($taskName)
    {
        $this->taskName = $taskName;
    }
    public function getTaskName()
    {
        return $this->taskName;
    }
    public function setTaskQueueName($taskQueueName)
    {
        $this->taskQueueName = $taskQueueName;
    }
    public function getTaskQueueName()
    {
        return $this->taskQueueName;
    }
    public function setTraceId($traceId)
    {
        $this->traceId = $traceId;
    }
    public function getTraceId()
    {
        return $this->traceId;
    }
    public function setUrlMapEntry($urlMapEntry)
    {
        $this->urlMapEntry = $urlMapEntry;
    }
    public function getUrlMapEntry()
    {
        return $this->urlMapEntry;
    }
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }
    public function getUserAgent()
    {
        return $this->userAgent;
    }
    public function setVersionId($versionId)
    {
        $this->versionId = $versionId;
    }
    public function getVersionId()
    {
        return $this->versionId;
    }
    public function setWasLoadingRequest($wasLoadingRequest)
    {
        $this->wasLoadingRequest = $wasLoadingRequest;
    }
    public function getWasLoadingRequest()
    {
        return $this->wasLoadingRequest;
    }
}

class ServiceLoggingSourceLocation extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $file;
    public $functionName;
    public $line;


    public function setFile($file)
    {
        $this->file = $file;
    }
    public function getFile()
    {
        return $this->file;
    }
    public function setFunctionName($functionName)
    {
        $this->functionName = $functionName;
    }
    public function getFunctionName()
    {
        return $this->functionName;
    }
    public function setLine($line)
    {
        $this->line = $line;
    }
    public function getLine()
    {
        return $this->line;
    }
}

class ServiceLoggingSourceReference extends Model
{
    protected $internal_gapi_mappings = [
    ];
    public $repository;
    public $revisionId;


    public function setRepository($repository)
    {
        $this->repository = $repository;
    }
    public function getRepository()
    {
        return $this->repository;
    }
    public function setRevisionId($revisionId)
    {
        $this->revisionId = $revisionId;
    }
    public function getRevisionId()
    {
        return $this->revisionId;
    }
}

class ServiceLoggingWriteLogEntriesRequest extends Collection
{
    protected $collection_key         = 'entries';
    protected $internal_gapi_mappings = [
    ];
    protected $entriesType     = ServiceLoggingLogEntry::class;
    protected $entriesDataType = 'array';
    public $labels;
    public $logName;
    protected $resourceType     = ServiceLoggingMonitoredResource::class;
    protected $resourceDataType = '';

    public function setEntries($entries)
    {
        $this->entries = $entries;
    }
    public function getEntries()
    {
        return $this->entries;
    }
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }
    public function getLabels()
    {
        return $this->labels;
    }
    public function setLogName($logName)
    {
        $this->logName = $logName;
    }
    public function getLogName()
    {
        return $this->logName;
    }
    public function setResource(ServiceLoggingMonitoredResource $resource)
    {
        $this->resource = $resource;
    }
    public function getResource()
    {
        return $this->resource;
    }
}

class ServiceLoggingWriteLogEntriesResponse extends Model
{
}
