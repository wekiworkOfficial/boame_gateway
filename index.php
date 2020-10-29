<?php

// include composer autoloader
include_once 'vendor/autoload.php';

// define headers
define('HEADERS', 'x-authorization-token,x-query-limits,x-account-id,x-auth-token,x-request-action,x-request-platform,content-type,Content-Type,X-Request-Method,x-request-method,x-requestid,x-account-types,x-date-range');

// include the http handler
include_once 'http.php';

// include helper function
include_once 'helper_func.php';

// set the response type
header('Content-Type: application/json');

// allow origins
header('Access-Control-Allow-Origin: *');

// allow request headers
header('Access-Control-Allow-Headers: ' . HEADERS);

// get the request
if (!isset($_GET['app'])) return json_error('Missing Service Type');

// get the request
$requests = explode('/', filter_var($_GET['app'], FILTER_SANITIZE_STRING));

// get the service type
$serviceType = isset($requests[0]) ? $requests[0] : null;

// can we continue
if ($serviceType === null) return json_error('Invalid Service Type "'.$serviceType.'"');

// read services.xml
$services = json_decode(file_get_contents(__DIR__ . '/services.json'));

// @var string $endpoint
$endpoint = null;

// get endpoint
foreach ($services->services as $service) :

    // do we have such a request type?
    if ($service->name == $serviceType) :

        // set the endpoint
        $endpoint = $service->endpoint;

        // break out
        break;

    endif;

endforeach;

// do we have an endpoint
if ($endpoint == null) return json_error('No endpoint avaliable for "'.$serviceType.'"');

// get request
$requests = array_splice($requests, 1);

// get headers
$headers = function_exists('getallheaders') ? getallheaders() : [];

// check for X-Request-Method
if (isset($headers['X-Request-Method']) || isset($headers['x-request-method'])) :

    // get method
    $method = isset($headers['X-Request-Method']) ? $headers['X-Request-Method'] : $headers['x-request-method'];

    // change request method
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);

endif;

// set headers
WekiWork\Http::setHeader($headers);

// set the endpoint
WekiWork\Http::$endpoint = $endpoint;

// get the request method
$method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'get';

// make request
$send = $_SERVER['REQUEST_METHOD'] == 'GET' ? call_user_func([WekiWork\Http::class, $method], implode('/', $requests)) : call_user_func([WekiWork\Http::multipart(), $method], implode('/', $requests));

// log error
json_has_error($send, ['endpoint' => $endpoint, 'method' => $method, 'request' => implode('/', $requests)]);

// are we good ?
if ($send->status !== 200) return json_error("Process failed. It's possible that this service is down and under maintenance, please try again sooner.");

// send output
json($send->json);