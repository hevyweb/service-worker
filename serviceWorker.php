<?php

/**
 * Class ServiceWorker this class is designed to communicate with restful APIs
 * @author Dmytro Dzyuba <joomsend@gmail.com>
 * @created 15.08.2017
 * @license MIT
 */

class ServiceWorker{

    /**
     * @var string base path to the web service
     */
    private $baseUrl;

    /**
     * @var callable the thing which parse the response from server
     */
    private $responseParser;

    /**
     * @var string request method name
     * Supported methods: GET, POST, PUT, DELETE
     */
    private $method;

    /**
     * @var callable logger
     */
    private $logger;

    /**
     * @var array some specific headers, that user wants to send, for example authentication keys
     */
    private $customHeaders;

    /**
     * @var int time in seconds. Maximum request time.
     */
    private $timeout;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Builds url
     *
     * @param string $baseUrl any URL
     * @param array $searchParams request parameters
     * @return string final url
     */
    protected function buildUrl($baseUrl, $searchParams = array())
    {
        if ($this->method == 'GET'){
            if (strstr($baseUrl, '?') && count($searchParams)){
                list($baseUrl, $baseParams) = explode('?', $baseUrl);
                parse_str($baseParams, $baseParams);
                $searchParams = array_merge($baseParams, $searchParams);
            }
            return $baseUrl . '?' . http_build_query($searchParams);
        }

        return $baseUrl;
    }

    protected function cleanUp()
    {
        /**
         * @TODO implement later
         */
    }

    public function create($data)
    {
        /**
         * @TODO implement later
         */
    }

    /**
     *
     * @param string $rawResponse some string, that should be parsed
     * @return mixed
     */
    public function defaultParser($rawResponse)
    {
        /**
         * @TODO implement later
         */
        return $rawResponse;
    }

    public function delete($id, $searchParams = array())
    {
        /**
         * @TODO implement later
         */
    }

    public function getAll($searchParams = array())
    {
        $this->setMethod('GET');
        $url = $this->buildUrl($searchParams);
        $this->sendRequest($url);
    }

    public function getOne($id)
    {
        /**
         * @TODO implement later
         */
    }

    /**
     * Registers callable method or function for logging
     *
     * @param callable $logger method or function which adds data to the log file
     * @return ServiceWorker
     */
    public function registerLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Register callable method of function for response parsing
     *
     * @param callable $responseParser method or function which parse the response
     * @return ServiceWorker
     */
    public function registerParser(callable $responseParser){
        $this->responseParser = $responseParser;
        return $this;
    }

    /**
     * Sets request method
     * @param string $methodName
     * @return ServiceWorker
     * @throws ServiceWorkerException
     */
    private function setMethod($methodName)
    {
        $expectedMethod = array(
            'GET', 'POST', 'PUT', 'DELETE'
        );
        $methodName = strtoupper($methodName);
        if (!in_array($methodName, $expectedMethod)){
            throw new ServiceWorkerException('Method ' . $methodName . ' is not supported.');
        }
        $this->method = $methodName;
        return $this;
    }

    private function sendRequest($url, $searchParams = array())
    {
        /**
         * @TODO implement later
         */
    }

    public function update($data, $id){}

    /**
     * @param string $url url, which should be called
     * @param array $data request parameters
     * @throws ServiceWorkerException in case of error
     * @return resource
     */
    protected function curlInit($url, $data)
    {
        if (!$curlHandler = curl_init()){
            throw new ServiceWorkerException('Your php is configured without curl extension.');
        }
        curl_setopt($curlHandler, CURLOPT_HEADER, true);
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandler, CURLOPT_URL, $url);

        if ($this->method == 'POST') {
            curl_setopt($curlHandler, CURLOPT_POST, true);
            curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $data);
        } elseif (in_array($this->method, array('PUT', 'DELETE'))) {
            curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, $this->method);
            curl_setopt($curlHandler, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $this->fileUpload($curlHandler);

        if (!empty($this->customHeaders)) {
            curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $this->customHeaders);
        }
        if (!is_null($this->timeout)) {
            curl_setopt($curlHandler, CURLOPT_TIMEOUT, $this->timeout);
        }

        return $curlHandler;
    }

    /**
     * Initialize file-uploading options in curl handler
     * @param resource $curlHandler
     * @return ServiceWorker
     */
    protected function fileUpload($curlHandler)
    {
        if (defined('CURLOPT_SAFE_UPLOAD') && in_array($this->method, array('POST', 'PUT'))) {
            curl_setopt($curlHandler, CURLOPT_SAFE_UPLOAD, true);
        }

        return $this;
    }

    /**
     * Perform a remote call
     *
     * @param string $url url, which should be called
     * @param array $data request parameters
     * @return mixed response
     */
    public function call($url, $data = array())
    {
        $curlHandler = $this->curlInit(($url, $data);

        $fullResponse = curl_exec($curlHandler);

        $response = $this->parseResponse($curlHandler, $fullResponse);

        curl_close($curlHandler);

        return $response;
    }
    /**
     * Build response based on the information from curl
     * @param resource $curlHandler
     * @param string $fullResponse
     * @return mixed
     */
    protected function parseResponse($curlHandler, $fullResponse)
    {
        /**
         * @TODO implement logger here
         */

        $responseInfo = curl_getinfo($curlHandler);
        $httpCode = $responseInfo['http_code'];
        $headerSize = $responseInfo['header_size'];

        $header = substr($fullResponse, 0, $headerSize);
        $body = substr($fullResponse, $headerSize);

        $responseInfo['total_time'];
        $responseInfo['url'];
        if (curl_errno($curlHandler)) {
            curl_error($curlHandler);
        }

        if ($this->responseParser){
            $response = $this->responseParser($body);
        } else {
            $response = $this->defaultParser($body);
        }

        return $response;
    }
}