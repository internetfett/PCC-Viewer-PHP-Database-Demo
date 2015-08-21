<?php

namespace PccViewer;

/**
 * Class MarkupLayers
 * Implements the create, update, read, and delete methods needed to work with markup layers.
 */
class MarkupLayers {

    public $viewingSessionId;
    public $layerRecordId;
    public $filenamePrefix;

    function __construct($viewingSessionId, $layerRecordId) {
        $this->viewingSessionId = $viewingSessionId;
        $this->layerRecordId = $layerRecordId;
    }

    /**
     * Routes REST requests to the appropriate handler
     */
    public function processRestRequest() {

        // The input data is a JSON object delivered as a string in the request body:
        if (!isset($HTTP_RAW_POST_DATA)) {
            $HTTP_RAW_POST_DATA = file_get_contents("php://input");
        }

        if (sizeof($HTTP_RAW_POST_DATA)){
            $layerData = json_decode($HTTP_RAW_POST_DATA);
        }

        try {
            $this->filenamePrefix = $this->getPccFilenamePrefix();
        } catch(\Exception $e) {
            $response = new \stdClass();
            $response->errorCode = 'BadGateway';
            $response->errorMessage = '';

            header("HTTP/1.0 502 Bad Gateway");
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':

                if (isset($this->layerRecordId)) {
                    $this->getLayerRecord($this->layerRecordId); // read a single, persisted layer record
                } else {
                    $this->getList(); // read a list of all persisted markup layers
                }

                break;

            case 'POST':

                if (isset($this->layerRecordId) && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) && $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] == 'DELETE') {
                    $this->deleteLayerRecord($this->layerRecordId); // delete a persisted layer record
                }
                else if (isset($this->layerRecordId) && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) && $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] == 'PUT') {
                    $this->updateLayerRecord($this->layerRecordId, $layerData); // update an existing layer record
                } else if (sizeof($layerData)) {
                    $this->createLayerRecord($layerData); // create and persist a new layer record
                }

                break;

            case 'PUT':
                $this->updateLayerRecord($this->layerRecordId, $layerData); // update an existing layer record
                break;

            case 'DELETE':

                if (isset($this->layerRecordId)) {
                    $this->deleteLayerRecord($this->layerRecordId); // delete a persisted layer record
                }

                break;

            default:
                $response = new \stdClass();
                $response->errorCode = 'BadRequest';
                $response->errorMessage = '';

                header("HTTP/1.0 400 Bad Request");
                header('Content-Type: application/json');
                echo json_encode($response);
                return;
                break;
        }
    }

    /**
     * Outputs a list of markup layers in a JSON object.
     */
    public function getList() {

        $list = [];

        try {

            $layerRecordPath = Config::getMarkupLayerRecordsPath();

            $fileList = glob($layerRecordPath . $this->filenamePrefix . '*.json');

            foreach ($fileList as $file) {

                $fileJson = json_decode(file_get_contents($file));

                if ($fileJson === null) {
                    continue; // $fileJson is null because the json cannot be decoded
                }

                $layer = new \stdClass();

                $layer->name = (!empty($fileJson->name)) ?
                    $fileJson->name :
                    '';

                $layer->layerRecordId = str_replace([$this->filenamePrefix, '.json'],[''], basename($file));
                
                $layer->originalXmlName = (!empty($fileJson->originalXmlName)) ?
                    $fileJson->originalXmlName :
                    '';
                
                $list[] = $layer;
            }

            header('Content-Type: application/json');
            echo json_encode($list);

        } catch(\Exception $e) {

            $response = new \stdClass();
            $response->errorCode = 'ServerError';
            $response->errorMessage = $e->getMessage();
            $response->layerRecordId = str_replace('.json', '', basename($file));

            header("HTTP/1.0 580 Server Error");
            header('Content-Type: application/json');
            echo json_encode($response);
        }
    }

    /**
     * Outputs a single layer record as a JSON object.
     * @param $id An identifier for the request layer record.
     */
    public function getLayerRecord($id){

        try {

            $layerRecordPath = Config::getMarkupLayerRecordsPath();
            $file = $layerRecordPath . $this->filenamePrefix . $id . '.json';

            if (file_exists($file)) {
                header('Content-Type: application/json');
                echo file_get_contents($file);
            } else {
                header("HTTP/1.0 404 Not Found");
                return;
            }

        } catch(\Exception $e) {

            $response = new \stdClass();
            $response->errorCode = 'ServerError';
            $response->errorMessage = $e->getMessage();
            $response->layerRecordId = $id;

            header("HTTP/1.0 580 Server Error");
            header('Content-Type: application/json');
            echo json_encode($response);
        }

    }

    /**
     * Creates a persisted layer record based on the input data.
     * @param $data The properties and values that define a layer record.
     */
    public function createLayerRecord($data){

        try {

            $layerRecordPath = Config::getMarkupLayerRecordsPath();

            $filename =  $this->getGUID();

            $jsonData = json_encode($data);

            $file = fopen($layerRecordPath . $this->filenamePrefix . $filename . '.json', "w");

            if (!$file) {
                throw new \Exception("Unable to open file for writing.");
            }

            fwrite($file, $jsonData);
            fclose($file);

            header("HTTP/1.0 201 Created");
            header('Content-Type: application/json');
            echo json_encode(array(
                'layerRecordId' => $filename
            ));

        } catch(\Exception $e) {

            $response = new \stdClass();
            $response->errorCode = 'ServerError';
            $response->errorMessage = $e->getMessage();
            $response->layerRecordId = $filename;

            header("HTTP/1.0 580 Server Error");
            header('Content-Type: application/json');
            echo json_encode($response);
        }

    }

    /**
     * Updates a persisted layer record with the input data
     * @param $layerId The identifier of the layer record to be updated
     * @param $data The updated layer record that will replace the existing one
     */
    public function updateLayerRecord($layerId, $data) {
        try {
            $layerRecordPath = Config::getMarkupLayerRecordsPath();

            $filename = $layerId;

            $jsonData = json_encode($data);

            $filepath = $layerRecordPath . $this->filenamePrefix . $filename . '.json';

            if (!file_exists($filepath)) {
                header("HTTP/1.0 404 Not Found");
                return;
            }

            $file = fopen($filepath, "w");
            fwrite($file, $jsonData);
            fclose($file);

            header("HTTP/1.0 200 OK");

        } catch(\Exception $e) {

            $response = new \stdClass();
            $response->errorCode = 'ServerError';
            $response->errorMessage = $e->getMessage();
            $response->layerRecordId = $layerId;

            header("HTTP/1.0 580 Server Error");
            header('Content-Type: application/json');
            echo json_encode($response);
        }
    }

    /**
     * Deletes a persisted layer record
     * @param $layerId The identifier of the layer record to be deleted
     */
    public function deleteLayerRecord($layerId) {
        try {
            $layerRecordPath = Config::getMarkupLayerRecordsPath();

            $filename = $layerId;

            $filepath = $layerRecordPath . $this->filenamePrefix . $filename . '.json';

            if (!file_exists($filepath)) {
                header("HTTP/1.0 404 Not Found");
                return;
            }

            unlink($filepath);

            header("HTTP/1.0 204 No Content");

        } catch(\Exception $e) {

            $response = new \stdClass();
            $response->errorCode = 'ServerError';
            $response->errorMessage = $e->getMessage();
            $response->layerRecordId = $layerId;

            header("HTTP/1.0 580 Server Error");
            header('Content-Type: application/json');
            echo json_encode($response);
        }
    }

    /**
     * A helper function that generates a random, unique identifier
     * @return string
     */
    protected function getGUID() {

        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = ''
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
        ;
        return $uuid;
    }

    protected function getPccFilenamePrefix() {

        $url = Config::getImagingService() . "/ViewingSession/" . urlencode($this->viewingSessionId);
        $acsApiKey = Config::getApiKey();
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
            throw new \Exception('Bad PCC response.');
        } else {
            $filenamePrefix = $response->externalId . '_' . $response->attachmentIndex . '_';
        }

        return $filenamePrefix;
    }
}
