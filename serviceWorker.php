<?php

/**
 * Class ServiceWorker
 * this class is designed to communicate with restful APIs
 * @author Dmytro Dzyuba <joomsend@gmail.com>
 * @created 15.08.2017
 * @license MIT
 */
class ServiceWorker
{

    /**
     * @var string base path to the web service
     */
    protected $baseUrl;

    /**
     * @var string specify
     */
    protected $contentType = 'autodetect';

    /**
     * @var resource curl link
     */
    private $curlHandler;

    /**
     * @var array some specific headers, that user wants to send, for example authentication keys
     */
    protected $customHeaders;

    /**
     * @var callable logger
     */
    protected $logger;

    /**
     * @var string request method name
     * Supported methods: GET, POST, PUT, DELETE
     */
    protected $method;
    /**
     * @var int time in seconds. Maximum request time
     */
    protected $timeout = 30;

    /**
     * ServiceWorker constructor.
     *
     * @param string $baseUrl base url of the API
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * ServiceWorker destructor.
     * Close curl connection if it wasn't closed.
     */
    public function __destruct()
    {
        if (!empty($this->curlHandler)) {
            curl_close($this->curlHandler);
            $this->curlHandler = null;
        }
    }

    /**
     * Builds url.
     *
     * @param string $baseUrl any URL
     * @param array $searchParams request parameters
     * @return string final url
     */
    protected function buildUrl($baseUrl, $searchParams = array())
    {
        if ($this->method == 'GET') {
            if (strstr($baseUrl, '?') && count($searchParams)) {
                list($baseUrl, $baseParams) = explode('?', $baseUrl);
                parse_str($baseParams, $baseParams);
                $searchParams = array_merge($baseParams, $searchParams);
            }
            return $baseUrl . '?' . http_build_query($searchParams);
        }

        return $baseUrl;
    }

    /**
     * Perform a remote call.
     *
     * @param string $url url, which should be called
     * @param array $data request parameters
     * @return mixed response
     */
    public function call($url, $data = array())
    {
        $this->curlInit($url, $data);

        $fullResponse = $this->sendRequest();

        $response = $this->parseResponse($fullResponse);

        return $response;
    }

    /**
     * Removes initialized data.
     */
    protected function cleanUp()
    {
        $this->method = null;
        $this->customHeaders = null;
        $this->timeout = 30;
        $this->contentType = 'autodetect';
    }

    /**
     * Creates new record.
     *
     * @param array $data record fields
     * @return mixed
     */
    public function create($data)
    {
        return $this->setMethod('POST')
            ->call($this->baseUrl, $data);
    }

    /**
     * Initialize curl options.
     *
     * @param string $url url, which should be called
     * @param array $data request parameters
     * @throws ServiceWorkerException in case of error
     */
    protected function curlInit($url, $data = array())
    {
        if (!$curlHandler = curl_init()) {
            throw new ServiceWorkerException('Your php is configured without curl extension.');
        }
        curl_setopt($this->curlHandler, CURLOPT_HEADER, true);
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandler, CURLOPT_URL, $url);

        if ($this->method == 'POST') {
            curl_setopt($this->curlHandler, CURLOPT_POST, true);
            curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, $data);
        } elseif (in_array($this->method, array('PUT', 'DELETE'))) {
            curl_setopt($this->curlHandler, CURLOPT_CUSTOMREQUEST, $this->method);
            curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $this->fileUpload($this->curlHandler);

        if (!empty($this->customHeaders)) {
            curl_setopt($this->curlHandler, CURLOPT_HTTPHEADER, $this->customHeaders);
        }

        if (!is_null($this->timeout)) {
            curl_setopt($this->curlHandler, CURLOPT_TIMEOUT, $this->timeout);
        }

        $requestData = array(
            'Url' => $url,
            'Method' => $this->method,
            'Data' => $data,
            'Headers' => $this->customHeaders ? $this->customHeaders : ''
        );

        $this->logData('Send request.', $requestData);
    }

    /**
     * Deletes the record by ID or by additional parameters.
     *
     * @param int $id ID of the record
     * @param array $searchParams search parameters
     * @return mixed
     */
    public function delete($id = null, $searchParams = array())
    {
        $url = $this->baseUrl;

        if (!is_null($id)) {
            $url .= '/' . $id;
        }

        return $this->setMethod('DELETE')
            ->call($url, $searchParams);
    }

    /**
     * Doesn't parse the content. Returns raw result
     * Can be extended for higher complexity
     *
     * @param string $rawContent raw response string
     * @return mixed
     */
    public function defaultParser($rawContent)
    {
        return $rawContent;
    }

    /**
     * Initialize file-uploading options in curl handler.
     *
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
     * Returns information about several records.
     *
     * @param array $searchParams search parameters
     * @return mixed
     */
    public function getAll($searchParams = array())
    {
        $url = $this->buildUrl($this->baseUrl, $searchParams);

        return $this->setMethod('GET')
            ->call($url);
    }

    /**
     * Returns information about 1 record by its ID.
     *
     * @param int $id ID of the record
     * @return mixed
     */
    public function getOne($id)
    {
        $url = $this->baseUrl . '/' . $id;

        return $this->setMethod('GET')
            ->call($url);
    }

    /**
     * Parse string as json.
     *
     * @param string $rawContent raw response string
     * @return array|int parsed response
     * @throws ServiceWorkerException
     */
    public function jsonParser($rawContent)
    {
        $content = json_decode($rawContent);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new ServiceWorkerException('Unable to parse json content. Error message: ' . json_last_error_msg());
        }
        return $content;
    }

    /**
     * Log the message.
     *
     * @param string $message
     * @param array $data additional parameters
     * @return boolean true on success
     */
    public function logData($message, $data = array())
    {
        if (!empty($this->logger)) {
            if (!empty($data)) {
                $message .= " Parameters " . print_r($data, true);
            }
            call_user_func($this->logger, $message);
            return true;
        }
        return false;
    }

    /**
     * Check the response code.
     * It could came more complex logic, e.g. different response code base on request method, but most of web services
     * in my practice returned only 200 on success no matter what, so I keep it simple.
     *
     * @param array $responseInfo response parameters
     * @return int response code
     * @throws ServiceWorkerException on failure
     */
    protected function parseCode($responseInfo)
    {
        $httpCode = $responseInfo['http_code'];
        if ($httpCode != 200) {
            throw new ServiceWorkerException('Server returned response code "' . $httpCode . '". Expected response code is 200.');
        }
        return $httpCode;
    }

    /**
     * Detects in which way parse the content.
     *
     * @param string $rawContent raw response body
     * @return mixed parsed response
     */
    public function parseContent($rawContent)
    {
        switch ($this->contentType) {
            case 'json':
                return $this->jsonParser($rawContent);
            case 'xml':
                return $this->xmlParser($rawContent);
            case 'autodetect':
                try {
                    return $this->jsonParser($rawContent);
                } catch (ServiceWorkerException $e) {
                    try {
                        return $this->xmlParser($rawContent);
                    } catch (ServiceWorkerException $e) {
                        return $this->defaultParser($rawContent);
                    }
                }
            default:
                return $this->defaultParser($rawContent);
        }
    }

    /**
     * Build response based on the information from curl.
     *
     * @param string $fullResponse response string which contains body and header
     * @return mixed parsed response
     */
    protected function parseResponse($fullResponse)
    {
        $responseInfo = curl_getinfo($this->curlHandler);

        $headerSize = $responseInfo['header_size'];

        $data = array(
            'Response code' => $this->parseCode($responseInfo),
            'Request time' => $responseInfo['total_time'],
            'Response Url' => $responseInfo['url'],
            'Response Headers' => substr($fullResponse, 0, $headerSize)
        );

        $this->logData('Successfully got the response.', $data);

        $body = substr($fullResponse, $headerSize);

        return $this->parseContent($body);
    }

    /**
     * Sends the actual request.
     *
     * @return mixed raw response
     * @throws ServiceWorkerException in case of failure
     */
    private function sendRequest()
    {
        $rawResponse = curl_exec($this->curlHandler);

        if (curl_errno($this->curlHandler)) {
            throw new ServiceWorkerException(curl_error($this->curlHandler));
        }

        return $rawResponse;
    }

    /**
     * Sets content type.
     *
     * @param string $contentType Currently it supports json and xml. It can be autodetect to make the script decide.
     * @return ServiceWorker
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Sets custom headers.
     *
     * @param array $customHeaders you may need this if you send custom headers with keys for authentication
     * @return ServiceWorker
     */
    public function setCustomHeaders($customHeaders)
    {
        $this->customHeaders = $customHeaders;
        return $this;
    }

    /**
     * Registers callable method or function for logging.
     *
     * @param callable $logger method or function which adds data to the log file
     * @return ServiceWorker
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Sets timeout.
     *
     * @param int $timeout maximum request time in seconds
     * @return ServiceWorker
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Sets request method.
     *
     * @param string $methodName request method. Currently supported 'GET', 'POST', 'PUT' and 'DELETE'
     * @return ServiceWorker
     * @throws ServiceWorkerException
     */
    public function setMethod($methodName)
    {
        $expectedMethod = array(
            'GET', 'POST', 'PUT', 'DELETE'
        );
        $methodName = strtoupper($methodName);
        if (!in_array($methodName, $expectedMethod)) {
            throw new ServiceWorkerException('Method ' . $methodName . ' is not supported.');
        }
        $this->method = $methodName;
        return $this;
    }

    /**
     * Updates the record by Id.
     *
     * @param array $data list of parameters to update
     * @param int $id ID of the record
     * @return mixed response
     */
    public function update($data, $id)
    {
        $url = $this->baseUrl . '/' . $id;

        return $this->setMethod('PUT')
            ->call($url, $data);
    }

    /**
     * Parse string as xml.
     *
     * @param string $rawContent raw response string
     * @return SimpleXMLElement parsed response
     * @throws ServiceWorkerException
     */
    public function xmlParser($rawContent)
    {
        $content = simplexml_load_string($rawContent);
        if (libxml_get_last_error() !== false) {
            libxml_clear_errors();
            throw new ServiceWorkerException('Unable to parse xml content.');
        }
        return $content;
    }
}