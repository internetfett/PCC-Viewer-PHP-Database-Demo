<?php
/**
 * ----------------------------------------------------------------------
 * <copyright file="pcc.php" company="Accusoft Corporation">
 * CopyrightÂ© 1996-2014 Accusoft Corporation. All rights reserved.
 * </copyright>
 * ----------------------------------------------------------------------
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
set_time_limit(600);

use \PccViewer\ImagingServiceProxy as ImagingServiceProxy;
use \PccViewer\MarkupLayers as MarkupLayers;
use \PccViewer\Config as PccConfig;

PccConfig::parse(dirname(__FILE__) . "/pcc.config");

function getPage($matches) {
    $page = new ImagingServiceProxy();
    $page->queryParameterWhiteList = array("DocumentID", "Scale", "ContentType", "Quality", "iv");
    $page->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Accusoft-Data-Encrypted", "Accusoft-Data-SK", "Accusoft-Status-Message", "Accusoft-Status-Number");
    echo $page->processRequest($matches);
}

function getPageTile($matches) {
    $pageTile = new ImagingServiceProxy();
    $pageTile->queryParameterWhiteList = array("DocumentID", "Scale", "ContentType", "Quality", "iv");
    $pageTile->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Accusoft-Data-Encrypted", "Accusoft-Data-SK", "Accusoft-Status-Message", "Accusoft-Status-Number");
    echo $pageTile->processRequest($matches);
}

function getPageAttributes($matches) {
    $pageAttributes = new ImagingServiceProxy();
    $pageAttributes->queryParameterWhiteList = array("DocumentID", "ContentType");
    $pageAttributes->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Accusoft-Data-Encrypted", "Accusoft-Status-Message", "Accusoft-Status-Number");
    echo $pageAttributes->processRequest($matches);
}

function documentArt() {
    header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
    header("Pragma: no-cache"); //HTTP 1.0
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

    $viewingSessionId = urldecode($_GET['DocumentID']);
    $annotationID = $_GET['AnnotationID'];

    // Call PCCIS
    // DocumentID query parameter already includes the "u" prefix so no need to add here
    $url = PccConfig::getImagingService() . "/ViewingSession/$viewingSessionId";
    $acsApiKey = PccConfig::getApiKey();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
            "Acs-Api-Key: $acsApiKey\r\n"
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    // make sure target directory exists
    $targetDir = PccConfig::getMarkupsPath();
    $annotationFileName = PccConfig::getMarkupsPath() . $response->externalId . "_" . $response->attachmentIndex . "_" . $annotationID . ".xml";

    if (file_exists($targetDir) === false) {
        @mkdir($targetDir, 0777, true);
    }

    if (!PccConfig::isFileSafeToOpen($annotationFileName)) {
        header('HTTP/1.0 403 Forbidden');
        return;
    }

    $ok = true;

    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        header("Status: 200 OK");
        $data = @file_get_contents('php://input');
        if ($data === false) {
            $ok = false;
        }
        $res = file_put_contents($annotationFileName, $data);
        if ($res === false) {
            $ok = false;
        }
    } else {
        header("Status: 200 OK");
        header('Content-type: application/xml');

        if (file_exists($annotationFileName) === true) {
            $data = file_get_contents($annotationFileName);
            if ($data === false) {
                $ok = false;
            }
            echo $data;
        }
    }

    if ($ok === false) {
        header("Status: 500 Internal Server Error");
        header('Content-type: text/plain');
    }
}

function getDocumentAttributes($matches) {
    $documentAttributes = new ImagingServiceProxy();
    $documentAttributes->queryParameterWhiteList = array("DocumentID", "DesiredPageCountConfidence");
    $documentAttributes->responseHeaderWhiteList = array("Content-Type", "Cache-Control");
    echo $documentAttributes->processRequest($matches);
}

function getDocumentText($matches) {
    $text = new ImagingServiceProxy();
    $text->queryParameterWhiteList = array("DocumentID", "iv");
    $text->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Accusoft-Data-Encrypted", "Accusoft-Data-SK", "Accusoft-Status-Message", "Accusoft-Status-Number");
    echo $text->processRequest($matches);
}

function getMarkupBurner($matches) {
    $markupBurner = new ImagingServiceProxy();
    $markupBurner->queryParameterWhiteList = array("ViewingSession", "iv", "ContentDispositionFilename");
    $markupBurner->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Content-Disposition", "filename");
    echo $markupBurner->processRequest($matches);
}

function saveDocument($matches) {

    $viewingSessionId = $_GET["DocumentID"];
    $urlViewingSessionId = urlencode($viewingSessionId);

    // Call PCCIS
    $url = PccConfig::getImagingService() . "/ViewingSession/$urlViewingSessionId";
    $acsApiKey = PccConfig::getApiKey();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
            "Acs-Api-Key: $acsApiKey\r\n"
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);
    $filePath = $response->origin->sourceDocument;

    $url = PccConfig::getImagingService() . "/ViewingSession/$urlViewingSessionId/SourceFile";

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    $handle = fopen($url, "rb");
    while ($handle && !feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);
}

function getLicense($matches) {
    $license = new ImagingServiceProxy();
    $license->queryParameterWhiteList = array("v", "iv", "p");
    $license->responseHeaderWhiteList = array("Content-Type", "Cache-Control");
    echo $license->processRequest($matches);
}

function getAttachments($matches) {
    $attachments = new ImagingServiceProxy();
    $attachments->queryParameterWhiteList = array();
    $attachments->responseHeaderWhiteList = array("Content-Type", "Cache-Control");
    echo $attachments->processRequest($matches);
}

function getDownloadDocument($matches) {
    $attachments = new ImagingServiceProxy();
    $attachments->queryParameterWhiteList = array("ViewingSessionId", "SourceFile");
    $attachments->responseHeaderWhiteList = array("Content-Type", "Cache-Control");
    echo $attachments->processRequest($matches);
}

function listMarkupJson($matches) {

    $annotationFiles = array('annotationFiles' => array());

    header("Content-Type: application/json");

    $viewingSessionId = $_GET['DocumentID'];

    // Call PCCIS
    $url = PccConfig::getImagingService() . "/ViewingSession/" . urlencode($viewingSessionId);
    $acsApiKey = PccConfig::getApiKey();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
            "Acs-Api-Key: $acsApiKey\r\n"
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    if ($result === 0 || !$response->externalId || !strlen($response->externalId)) {
        header("HTTP/1.1 500 Internal Server Error");
    } else {

        $path2Save = PccConfig::getMarkupsPath();

        $i = 1;
        $fileList = glob($path2Save . '*.xml');
        foreach ($fileList as $file) {
            if ((stristr($file, $response->externalId . '_' . $response->attachmentIndex) == TRUE)) {

                $nameParts = explode('.', array_pop(explode('_', basename($file))));
                $name = substr($nameParts[0], 1);

                $annotationFiles['annotationFiles'][] = array(
                    'annotationLabel' => $name,
                    'annotationName' => basename($file),
                    'ID' => (string) $i++,
                );
            }
        }
    }
    echo json_encode($annotationFiles);

    flush();
}

function getImageStampList() {

    PccConfig::parse("pcc.config");
    $stampPath = PccConfig::getImageStampPath();

    $stampImages = array(
        'imageStamps' => array()
    );
    $acceptableFormats = str_replace('.', '', PccConfig::getValidImageStampTypes());
    $fileList = glob($stampPath . "*.{" . $acceptableFormats . "}", GLOB_BRACE);

    foreach ($fileList as $filepath) {

        $fileName = basename($filepath);

        $stampImages['imageStamps'][] = array(
            'id' => base64_encode($fileName),
            'displayName' => $fileName,
        );
    }

    header("Content-Type: application/json");
    echo json_encode($stampImages);
}

function getImageStamp($matches) {

    $requestedFormat = $_GET['format'];

    $file = base64_decode($matches[1]);
    PccConfig::parse("pcc.config");
    $stampPath = PccConfig::getImageStampPath();
    $filepath = $stampPath . $file;

    if (!file_exists($filepath)) {
        throw new \Exception('Image not found.');
    }

    $fileParts = explode('.', $file);
    $sourceImageFormat = strtolower($fileParts[1]);

    $acceptableFormats = explode(',', str_replace('.', '', PccConfig::getValidImageStampTypes()));

    if (!in_array($sourceImageFormat, $acceptableFormats)) {
        throw new \Exception('Image format is not valid.');
    }

    $lastModifiedTime = filemtime($filepath);

    if ($lastModifiedTime === false) {
        throw new \Exception('Modify date unknown');
    }
    if (array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
        $ifModifiedSinceTime = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
        if ($ifModifiedSinceTime >= $lastModifiedTime) { // Is the Cached version the most recent?
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            return;
        }
    }
    header('Last-Modified: ' . date('r', $lastModifiedTime));
    header('Pragma: public');
    header('Cache-Control: max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

    if ($requestedFormat == 'Base64') {
        $outputFormat = $requestedFormat;
    } else {
        $outputFormat = $sourceImageFormat;
    }

    $ext = strtolower(array_pop(explode('.', $filepath)));

    $mime_types = array(
        // images
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
    );

    if (array_key_exists($ext, $mime_types)) {
        $imageType = $mime_types[$ext];
    } else {
        throw new \Exception('Image type not supported.');
    }

    switch ($outputFormat) {

        case 'gif':
        case 'jpg':
        case 'jpeg':
        case 'png':
            header("Content-Type: $imageType");
            echo file_get_contents($filepath);
            break;

        case 'Base64':
            header("Content-Type: application/json");
            $base64Data = base64_encode(file_get_contents($filepath));
            echo json_encode(array(
                'dataHash' => sha1($base64Data),
                'dataUrl' => 'data: ' . $imageType . ';base64,' . $base64Data,
            ));
            break;

    }

}

/**
 * Returns a viewing session ID and performs a document upload to PCC server
 */
function createSession() {

    $createSession = new \PccViewer\CreateSession();
    $createSession->processRestRequest();

}

function getViewingSessionProperties($viewingSessionId) {
    // Download original file
    $viewingSessionId = $viewingSessionId;

    $acsApiKey = PccConfig::getApiKey();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
            "Acs-Api-Key: $acsApiKey\r\n"
        ),
    );

    $url = PccConfig::getImagingService() . "/ViewingSession/u$viewingSessionId";
    $context = stream_context_create($options);
    $sessionProperties = file_get_contents($url, false, $context);

    // Retrieve HTTP status code
    list($version, $statusCode, $msg) = explode(' ', $http_response_header[0], 3);

    return $sessionProperties;
}

function conversionKickoff($matches) {

    // Download original file
    $viewingSessionId = $_POST['viewingSessionId'];
    $sessionProperties = json_decode(getViewingSessionProperties($viewingSessionId));

    $acsApiKey = PccConfig::getApiKey();
    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => "Accept: application/octet-stream\r\n" .
                        "Acs-Api-Key: $acsApiKey\r\n"
        ),
    );

    $url = PccConfig::getImagingService() . "/ViewingSession/u$viewingSessionId/SourceFile";
    $context = stream_context_create($options);
    $fileContents = file_get_contents($url, false, $context);

    // Retrieve HTTP status code
    list($version, $statusCode, $msg) = explode(' ', $http_response_header[0], 3);

    // Upload file to WorkFile service
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/octet-stream\r\n" .
                        "Accept: application/json\r\n" .
                        "Acs-Api-Key: $acsApiKey\r\n",
            'content' => $fileContents,
        ),
    );

    $url = PccConfig::getImagingService() . "/WorkFile?FileExtension={$sessionProperties->documentExtension}";
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Retrieve HTTP status code
    list($version, $statusCode, $msg) = explode(' ', $http_response_header[0], 3);

    // Return any errors
    if ($statusCode != 200) {
        header("$version $statusCode $msg");
        return;
    }

    $workFileResult = json_decode($result);

    $affinityToken = (!empty($workFileResult->affinityToken)) ?
        $workFileResult->affinityToken :
        null;

    $converterPayload = array(
        "input" => array(
            "src" => array(
                "fileId" => $workFileResult->fileId
            ),
            "dest" => array(
                "format" => "pdf"
            )
        )
    );

    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
        "Acs-Api-Key: $acsApiKey",
        "Accusoft-Affinity-Token: $affinityToken"
    ];

    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => implode('\r\n', $headers),
            'content' => json_encode($converterPayload),
        ),
    );

    // Kickoff conversion.
    $url = PccConfig::getImagingServiceV2() . "/contentConverters";
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Retrieve HTTP status code
    list($version, $statusCode, $msg) = explode(' ', $http_response_header[0], 3);
    header("$version $statusCode $msg");
    // Return the body of the response only if it did not fail.
    if ($statusCode == 200) {
        header("Content-Type: application/json");
        echo $result;
    }
}

function getConversionStatus($matches) {

    $conversion = new ImagingServiceProxy();

    if (!empty($_GET['affinityToken'])) {
        $conversion->requestHeaders = array(
            "Accusoft-Affinity-Token" => rawurldecode($_GET['affinityToken'])
        );
    }

    $conversion->requestHeaderWhiteList = array('Accusoft-Affinity-Token', 'accusoft-affinity-token'); // IE8 sometimes sends headers with lower case capitalization
    $conversion->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Content-Disposition");

    $conversion->processRequest($matches, 'V2');
}

function getWorkFile($matches) {
    $conversion = new ImagingServiceProxy();
    $conversion->queryParameterWhiteList = array("ContentDispositionFilename");

    if (!empty($_GET['affinityToken'])) {
        $conversion->requestHeaders = array(
            "Accusoft-Affinity-Token" => rawurldecode($_GET['affinityToken'])
        );
    }

    $conversion->responseHeaderWhiteList = array("Content-Type", "Cache-Control", "Content-Disposition");
    header('Content-Type: application/octet-stream'); // force browsers to download the output
    $conversion->processRequest($matches);
}

/**
 * Handles REST requests related to markup layers
 */
function markupLayers($matches) {

    if ($matches[1]) {
        $viewingSessionId = $matches[1];
    }

    if ($matches[2]) {
        $layerRecordId = $matches[2];
    }

    $markupLayers = new MarkupLayers($viewingSessionId, $layerRecordId);
    $markupLayers->processRestRequest();
}

// autoloader to handle automatic loading of classes
function __autoload($class_name) {

    $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    require_once("$class_name.php");
}

$urls = array(
    // Resources requested by the HTML5 viewer
    array('regex' => "/^\\/CreateSession/", 'fn' => 'createSession'),
    array('regex' => "/^\\/Page\\/([^\\/]+)\\/(\\d+)$/", 'fn' => 'getPage'),
    array('regex' => "/^\\/Page\\/([^\\/]+)\\/(\\d+)\\/Tile\\/(\\d+)\\/(\\d+)\\/(\\d+)\\/(\\d+)$/", 'fn' => 'getPageTile'),
    array('regex' => "/^\\/Page\\/([^\\/]+)\\/(\\d+)\\/Attributes$/", 'fn' => 'getPageAttributes'),
    array('regex' => "/^\\/Document\\/([^\\/]+)\\/Attributes$/", 'fn' => 'getDocumentAttributes'),
    array('regex' => "/^\\/Document\\/([^\\/]+)\\/Art\\/([^\\/]+)$/", 'fn' => 'documentArt'),
    array('regex' => "/^\\/Document\\/([^\\/]+)\\/(\\d+)-(\\d+)\\/Text$/", 'fn' => 'getDocumentText'),
    array('regex' => "/^\\/AnnotationList\\/([^\\/]+)\\/Art/", 'fn' => 'listMarkupJson'),
    array('regex' => "/^\\/ViewingSession\\/([^\\/]+)\\/SourceFile$/", 'fn' => 'getDocumentAttributes'),
    array('regex' => "/^\\/SaveDocument\\/([^\\/]+)$/", 'fn' => 'saveDocument'),
    array('regex' => "/^\\/License\\/ClientViewer$/", 'fn' => 'getLicense'),
    array('regex' => "/^\\/ViewingSession\\/([^\\/]+)\\/Attachments/", 'fn' => 'getAttachments'),
    array('regex' => "/^\\/ViewingSession\\/([^\\/]+)\\/MarkupBurner$/", 'fn' => 'getMarkupBurner'),
    array('regex' => "/^\\/ViewingSession\\/([^\\/]+)\\/MarkupBurner\\/([^\\/]+)$/", 'fn' => 'getMarkupBurner'),
    array('regex' => "/^\\/ViewingSession\\/([^\\/]+)\\/MarkupBurner\\/([^\\/]+)\\/Document$/", 'fn' => 'getMarkupBurner'),
    array('regex' => "/^\\/ImageStampList/", 'fn' => 'getImageStampList'),
    array('regex' => "/^\\/ImageStamp\\/([^\\/]+)/", 'fn' => 'getImageStamp'),
    array('regex' => '/^\/contentConverters\/$/', 'fn' => 'conversionKickoff'),
    array('regex' => '/^\/contentConverters\/([^\/]+)$/', 'fn' => 'getConversionStatus'),
    array('regex' => "/^\\/WorkFile\\/([^\\/]+)/", 'fn' => 'getWorkFile'),
    array('regex' => "/^\\/MarkupLayers\\/([^\\/]+)\\/([^\\/]+)/", 'fn' => 'markupLayers'),
    array('regex' => "/^\\/MarkupLayers\\/([^\\/]+)/", 'fn' => 'markupLayers'),
);

foreach ($urls as $url) {
    $matches = array();
    if (preg_match($url['regex'], $_SERVER['PATH_INFO'], $matches)) {
        $x = $url['fn'];
        $x($matches);
        break;
    }
}
