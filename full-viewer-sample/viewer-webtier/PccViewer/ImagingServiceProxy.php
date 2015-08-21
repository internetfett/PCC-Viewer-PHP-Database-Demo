<?php

namespace PccViewer;

/**
*----------------------------------------------------------------------
*<copyright file="imagingServiceProxy.php" company="Accusoft Corporation">
*CopyrightÂ© 1996-2014 Accusoft Corporation.  All rights reserved.
*</copyright>
*----------------------------------------------------------------------
*/

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
set_time_limit(600);

class ImagingServiceProxy {

    public $requestTypeWhiteList = array();
    public $queryParameterWhiteList = array();
    public $requestHeaderWhiteList = array();
    public $requestHeaders = array();
    public $responseHeaderWhiteList = array();

    function __construct() {
        $this->requestTypeWhiteList = array("GET", "POST");
    }

    function processRequest($matches, $serviceVersion = 'V1') {
        // Verify that the request type is acceptable.
        if (!is_null($this->requestTypeWhiteList)) {
            $requestIsAcceptable = false;
            foreach ($this->requestTypeWhiteList as $requestType) {
                if ($_SERVER['REQUEST_METHOD'] == $requestType) {
                    $requestIsAcceptable = true;
                    break;
                }
            }

            if (!$requestIsAcceptable) {
                throw new Exception("The request type is not acceptable.");
            }
        }

        if ($serviceVersion == 'V2') {
            $imagingServiceUri = Config::getImagingServiceV2() . $_SERVER['PATH_INFO'];
        } else {
            $imagingServiceUri = Config::getImagingService() . $_SERVER['PATH_INFO'];
        }

        // Add only the white-listed query parameters to the outgoing request.
        $queryParameters = "";
        if (!is_null($this->queryParameterWhiteList)) {
            foreach ($this->queryParameterWhiteList as $key) {
                $data = $_GET[$key];
                if (!is_null($data)) {
                    if (!empty($queryParameters)) {
                        $queryParameters .= "&";
                    }
                    $queryParameters .= $key . '=' . urlencode($data);
                }
            }
        }
        if (!empty($queryParameters)) {
            $imagingServiceUri .= '?' . $queryParameters;
        }

        // Add only the white-listed request header items to the outgoing request.
        $headerList = '';
        $body = '';
        if (sizeof($this->requestHeaderWhiteList)) {
            $requestHeaders = getallheaders();
            foreach ($this->requestHeaderWhiteList as $key) {
                $data = $requestHeaders[$key];
                if (!is_null($data)) {
                    $headerList .= "$key: $data\r\n";
                }
            }
        }
        if (sizeof($this->requestHeaders)) {
            foreach ($this->requestHeaders as $key => $value) {
                $headerList .= "$key: $value\r\n";
            }
        }
        $acsApiKey = Config::getApiKey();
        $headerList .= "Acs-Api-Key: $acsApiKey\r\n";

        if (($_SERVER['REQUEST_METHOD'] == 'POST') || ($_SERVER['REQUEST_METHOD'] == 'PUT')) {
            $body = @file_get_contents('php://input');
        }

        $options = array(
            'http' => array(
                'method' => $_SERVER['REQUEST_METHOD'],
                'header' => $headerList,
                'content' => $body,
            ),
        );

        $context = stream_context_create($options);
        $result = file_get_contents($imagingServiceUri, false, $context);

        // Retrieve HTTP status code
        list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);

        // Add only the white-listed response header items to the response (plus the status code)
        if ($status_code == 0) {
            // The imaging service currently returns 0 status sometimes.
            $status_code = 200;
        }

        header("$version $status_code $msg");

        if (!is_null($this->responseHeaderWhiteList)) {
            foreach ($this->responseHeaderWhiteList as $key) {
                foreach ($http_response_header as $value) {
                    if (preg_match("/^$key:/i", $value)) {
                        // Successful match
                        header($value, TRUE);
                    }
                }
            }
        }

        // Return the body of the response only if it did not fail.
        if ($status_code == 200) {
            echo $result;
        }
    }
}
