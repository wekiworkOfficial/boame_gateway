<?php
namespace WekiWork;

use GuzzleHttp\Psr7\Request;

/**
 * @package Simple HTTP Request Handler for Boame Api
 * @author Amadi ifeanyi <amadiify.com> <wekiwork.com>
 */

class Http
{
    // url
    public static $endpoint;
    // instance
    private static $instance;
    // client
    private static $client;
    // headers
    private static $headers = [];
    // trash
    private $trash = [];
    // files
    private $attachment = ['multipart'=>[],'query'=>[]];
    // ready state
    private static $readyState = [];
    // using same origin
    private $usingSameOrigin = false;
    // same origin url
    private $sameOriginUrl = null;
    // same origin data
    public $sameOriginData = [];
    // same origin response
    private $sameOrginResponse = null;

    // create instance
    public static function createInstance()
    {
        if (is_null(self::$instance))
        {
            self::$instance = new self; // create instance
            self::$client = new \GuzzleHttp\Client(); // set client
            self::setHeader(self::autoHeaders()); // set auto headers
        }
    }
    
    // create request
    public static function __callStatic($method, $data)
    {
        // create instance
        self::createInstance();

        // switch
        return self::manageSwitch($method, $data);
    }

    // add body
    private function addBodyToRequest($data)
    {
        if (count($data) == 1 && is_string($data[0]))
        {
            self::$instance->attachment['multipart'][] = [
                'contents'  => isset($_POST[$data[0]]) ? $_POST[$data[0]] : $data[0],
                'name'      => $data[0]
            ];
        }
        elseif (count($data) == 1 && is_array($data[0]))
        {
            foreach ($data[0] as $key => $val)
            {
                self::$instance->attachment['multipart'][] = [
                    'name'      => $key,
                    'contents'  => $val
                ];
            }
        }
        elseif (count($data) > 1)
        {
            foreach ($data as $index => $key)
            {
                if (isset($_POST[$key]))
                {
                    self::$instance->attachment['multipart'][] = [
                        'name'      => $key,
                        'contents'  => $_POST[$key]
                    ];
                }
            }
        }
        else
        {
            if (count($_POST) > 0)
            {
                foreach ($_POST as $key => $val)
                {
                    self::$instance->attachment['multipart'][] = [
                        'name'      => $key,
                        'contents'  => $val
                    ];
                }
            }
        }
    }

    // add params
    private function addQueryToRequest($data)
    {
        if (count($data) == 1 && is_string($data[0]))
        {
            self::$instance->attachment['query'] = isset($_GET[$data[0]]) ? $_GET[$data[0]] : $data[0];
        }
        elseif (count($data) == 1 && is_array($data[0]))
        {
            self::$instance->attachment['query'] = http_build_query($data[0]);
        }
        elseif (count($data) > 1)
        {
            $get = [];

            foreach ($data as $index => $key)
            {
                if (isset($_GET[$key])) $get[$key] = $_GET[$key];
            }

            if (count($get) > 0)
            {
                self::$instance->attachment['query'] = http_build_query($get);
            }
        }
        else
        {
            self::$instance->attachment['query'] = http_build_query($_GET);
        }
    }

    // add file
    private function addFileToRequest($data)
    {
        self::setHeader([
            'X-File-Agent' => 'Moorexa GuzzleHttp'
        ]);
        // attach file
        call_user_func_array([self::$instance, 'attachFile'], $data);
    }

    // manage switch
    private static function manageSwitch($method, $data)
    {
        // get method
        switch (strtolower($method))
        {
            case 'attach':
            case 'attachment':
                self::$instance->addFileToRequest($data);
            break;

            case 'body':
                self::$instance->addBodyToRequest($data);
            break;

            case 'query':
                self::$instance->addQueryToRequest($data);
            break;

            case 'multipart':
                self::$instance->addFileToRequest($data);
                self::$instance->addBodyToRequest($data);
                //self::$instance->addQueryToRequest($data);
            break;

            case 'header':
                // set header
                call_user_func_array('\WekiWork\Http::setHeader', $data);
            break;

            default:
                return self::$instance->sendRequest($method, $data[0]);
        }

        // return instance
        return self::$instance;
    }

    // attach a file
    public function attachFile()
    {
        $files = func_get_args();

        if (count($files) == 0 && count($_FILES) > 0)
        {
            $files = array_keys($_FILES);
        }

        // check if file exists.
        array_walk($files, function($file, $key){
            if (is_string($file))
            {
                $key = 'file';

                if (file_exists($file))
                {
                    // create resource
                    $handle = fopen($file, 'r');
                    // get base 
                    $base = basename($file);
                    $key = substr($base, 0, strpos($base,'.'));

                    // add to attachment
                    $this->attachment['multipart'][] = [
                        'name' => $key,
                        'contents' => $handle,
                        'filename' => $base
                    ];
                }
                else
                {

                    if (isset($_FILES[$file]))
                    {
                        $files = $_FILES[$file];

                        if (!is_array($files['name']))
                        {
                            // get handle
                            $handle = fopen($files['tmp_name'], 'r');

                            // attach file
                            $this->attachment['multipart'][] = [
                                'name'      => $file,
                                'contents'  => $handle,
                                'filename'  => $files['name']
                            ];
                        }
                        else
                        {
                            foreach ($files['name'] as $index => $name)
                            {
                                // get handle
                                $handle = fopen($files['tmp_name'][$index], 'r');

                                // attach file
                                $this->attachment['multipart'][] = [
                                    'name'      => $file . '[]',
                                    'contents'  => $handle,
                                    'filename'  => $name
                                ];
                            }
                        }
                    }
                }
            }
        });
    }

    // caller method
    public function __call($method, $data)
    {
        // set the instance
        if (is_null(self::$instance)) self::$instance = $this;

        // switch
        return self::manageSwitch($method, $data);
    }

    // check ready state
    public static function onReadyStateChange($callback)
    {
        self::$readyState[] = $callback;
    }

    // headers
    private function sendRequest($method, $path)
    {
        // endpoint
        $path = rtrim(self::$endpoint, '/') . '/' . $path;

        $client = self::$client;
        $headers = self::$headers;

        // cookie jar
        $jar = new \GuzzleHttp\Cookie\CookieJar();

        // remove content type
        if (isset($headers['content-type'])) unset($headers['content-type']);
        if (isset($headers['Content-Type'])) unset($headers['Content-Type']);

        // new header
        $newHeaders = [];

        // get defined headers
        $definedHeaders = explode(',', HEADERS);

        // manage headers
        foreach ($definedHeaders as $header) :

            // check headers
            if (isset($headers[$header])) $newHeaders[$header] = $headers[$header];

            // check next
            if (isset($headers[ucwords($header)])) $newHeaders[$header] = $headers[ucwords($header)];

        endforeach;
        
        // add request body
        $requestBody = [
            'headers'   => $newHeaders,
            'debug'     => false,
            'jar'       => $jar
        ];

        // merge 
        $requestBody = array_merge($requestBody, $this->attachment);

        // reset
        $this->attachment = ['multipart' => [], 'query' => []];

        try
        {
            // send request
            $send = $client->request($method, $path, $requestBody);

            // response
            $response = new class ($send)
            {
                public $guzzle; // guzzle response
                public $status; // response status
                public $statusText; // response status
                public $responseHeaders; // response headers
                public $text; // response body text
                public $json; // response body json

                // constructor
                public function __construct($response)
                {
                    $this->guzzle = $response;
                    $this->status = $response->getStatusCode();
                    $this->responseHeaders = $response->getHeaders(); 
                    $this->statusText = $response->getReasonPhrase();

                    // get body
                    $body = $response->getBody()->getContents();
                    $this->text = $body;

                    // get json 
                    $json = is_string($body) ? json_decode($body) : null;

                    // set the json data
                    if (!is_null($json) && is_object($json)) $this->json = $json;
                }
            };

            return $response;

        }
        catch(\GuzzleHttp\Exception\ServerException $exception)
        {
            return new class($exception)
            {
                public $json = null;
                public $text;
                public $trace;
                public $status; // response status
                public $message; // response status
                public $responseHeaders; // response headers

                // constructor
                public function __construct($exception)
                {
                    $this->text = $exception->getMessage();
                    $this->trace = $exception->getTraceAsString();
                    $this->message = $exception->getMessage();
                }
            };
        }
        catch(\GuzzleHttp\Exception\ConnectException $exception)
        {
            return new class($exception)
            {
                public $json = null;
                public $text;
                public $trace;
                public $status; // response status
                public $message; // response status
                public $responseHeaders; // response headers

                // constructor
                public function __construct($exception)
                {
                    $this->text = $exception->getMessage();
                    $this->trace = $exception->getTraceAsString();
                    $this->message = $exception->getMessage();
                }
            };
        }
    }

    // get all auto headers
    public static function autoHeaders()
    {
        $headers = [];

        // get default headers
        if (file_exists(__DIR__ . '/config.xml'))
        {
            $config = simplexml_load_file(__DIR__ . '/config.xml');

            if ($config !== false)
            {
                $arr = toArray($config);

                if (isset($arr['request']))
                {
                    if (isset($arr['request']['identifier']))
                    {
                        $arr = $arr['request']['identifier'];
                        
                        if (is_array($arr) && isset($arr[0]))
                        {
                            array_map(function($a) use (&$headers){
                                if (isset($a['header']))
                                {
                                    $header = trim(strtolower($a['header']));
                                    $valStored = trim($a['value']);

                                    $headers[$header] = $valStored;
                                }
                            }, $arr);
                        }
                        else
                        {
                            $headers[$arr['header']] = $arr['value'];
                        }
                    }
                }
            }
        }

        return $headers;
    }

    // set header
    public static function setHeader($header)
    {
        $current = self::$headers;

        if (is_array($header))
        {
            $current = array_merge($current, $header);
            self::$headers = $current;
        }
        else
        {
            $args = func_get_args();
            $headers = [];

            foreach ($args as $index => $header)
            {
                $toArray = explode(':', $header);
                $key = trim($toArray[0]);
                $val = trim($toArray[1]);
                $headers[$key] = $val;
            }

            $current = array_merge($current, $headers);
            self::$headers = $current;
        }

        // clean up
        $current = null;
    }

    // get all headers
    public static function getHeaders()
    {
        $headers = getallheaders();
        $newHeader = [];

        $headers = array_merge($headers, self::$headers);

        foreach ($headers as $header => $value)
        {
            $newHeader[strtolower($header)] = $value;
        }   

        return $newHeader;
    }

    // has header
    public static function hasHeader(string $header, &$value=null) :bool
    {
        $headers = self::getHeaders();

        if (isset($headers[strtolower($header)]))
        {
            $value = $headers[strtolower($header)];

            return true;
        }

        return false;
    }

    // create same origin
    public static function sameOrigin($callback = null)
    {
        // create object
        $http = new Http;
        $http->sameOriginUrl = false; // app url
        $http->usingSameOrigin = true;

        $sameOrginResponse = function(&$http)
        {
            return new class($http){
                public $status = 0;
                public $json = null;
                public $text = null;
    
                public function __construct($http)
                {
                    $sameOriginData = $http->sameOriginData;
    
                    if (isset($sameOriginData['status']))
                    {
                        // set status
                        $this->status = $sameOriginData['status'];
                        // set text response
                        $this->text = $sameOriginData['text'];
                        // set json
                        $this->json = $sameOriginData['json'];
                    }
                }
            };
        };

        if (is_callable($callback) && !is_null($callback))
        {
            // call closure function
            call_user_func_array($callback, [&$http]);

            return call_user_func_array($sameOrginResponse, [&$http]);
        }
        else
        {
            $http->sameOrginResponse = $sameOrginResponse;
        }

        return $http;
    }
}