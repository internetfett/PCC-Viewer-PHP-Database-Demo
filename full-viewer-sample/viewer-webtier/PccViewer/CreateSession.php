<?php

namespace PccViewer;

/**
 * Class CreateSession
 * @package PccViewer
 * Establishes a viewing session with a PCC Server and handles the document upload to the same server.
 */
class CreateSession
{

    /**
     * Routes REST requests to the appropriate handler
     */
    public function processRestRequest() {
        if ($_REQUEST['document']) {
            $this->fromDocument($_REQUEST['document']);
        } elseif ($_REQUEST['viewingSessionId']) {
            $this->fromViewingSession($_REQUEST['viewingSessionId']);
        } else {
            header("HTTP/1.0 400 Invalid Request");
            return;
        }
    }

    /**
     * Uses a document id (filename or URL) to locate the file and upload it to PCC.
     * @param $documentQueryParameter The full or partial identifier for a document.
     */
    public function fromDocument($documentQueryParameter) {

        if (empty($documentQueryParameter)) {
            header("HTTP/1.0 400 Invalid Request");
            return;
        }

        if (strstr($documentQueryParameter, "http://") || strstr($documentQueryParameter, "https://")) {
            $documentId = $documentQueryParameter;
        } else {
            $filename = basename($documentQueryParameter);
            $folder = dirname($documentQueryParameter);
            if ($folder == ".") {
                $folder = Config::getDocumentsPath();
            } else {
                $folder = $folder . "/";
            }

            $documentId = Utils::combine($folder, $filename);
        }

        $fileExtension = pathinfo($documentId, PATHINFO_EXTENSION);

        $correctPath = Config::isFileSafeToOpen($documentId);

        if (!$correctPath) {
            header('HTTP/1.0 403 Forbidden');
            echo('<h1>403 Forbidden</h1>');
            return;
        }

        $fileStream = fopen($documentId, "rb");

        if (!$fileStream) {
            header("HTTP/1.0 404 Not Found");
            echo('Document not found.');
            return;
        }

        $this->fromStream($fileStream, $documentId, $fileExtension);

        fclose($fileStream);

    }

    /**
     * If a 'viewingSessionId' value exists, there is viewing session
     * already so we don't need to do anything else. This case is true
     * when viewing attachments of email message document types (.EML and .MSG).
     * @param $viewingSessionId The id of a viewing session
     */
    public function fromViewingSession($viewingSessionId) {
        $viewingSessionId = stripslashes($viewingSessionId);

        // // Request properties about the viewing session from PCCIS.
        // // The properties will include an identifier of the source document
        // // from which the attachment was obtained. The name of the attachment
        // // is also available. These values are used to just to provide
        // // contextual information to the user.
        // //   GET http://localhost:18681/PCCIS/V1/ViewingSession/u{Viewing Session ID}
        // $url = Config::getImagingService() . "/ViewingSession/u" . urlencode($viewingSessionId);
        // $result = file_get_contents($url);
        // $response = json_decode($result);

        // $document = $response->origin->sourceDocument . ":{" . $response->attachmentDisplayName . "}";

        header('Content-Type: application/json');
        echo json_encode(array(
            'viewingSessionId' => $viewingSessionId
        ));
    }

    /**
     * Takes a valid file stream
     * @param $fileStream A valid file stream
     * @param $documentId An identifier for the document. Example: a file name
     * @param $fileExtension The document's file extension
     */
    public function fromStream($fileStream, $documentId, $fileExtension) {

        $viewingSessionId = $this->getViewingSessionId($documentId, $fileExtension);

        $fileContents = stream_get_contents($fileStream);

        if (!$fileContents) {
            $this->endViewingSession($viewingSessionId, $documentId);
            header("HTTP/1.0 404 Not Found");
            echo('Document not found.');
            return;
        }

        $this->uploadDocument($viewingSessionId, $fileContents, $fileExtension);

        header('Content-Type: application/json');
        echo json_encode(array(
            'viewingSessionId' => $viewingSessionId
        ));

    }

    /**
     * Establishes a viewing session ID with a PCC server.
     * @param $documentId An identifier for the document. Example: a file name
     * @param $fileExtension The document's file extension
     * @return string
     */
    protected function getViewingSessionId($documentId, $fileExtension)
    {
        $acsApiKey = Config::getApiKey();
        $documentHash = Utils::getHashString($documentId);

        // Set viewing session properties using JSON.
        $data = array(
            // Store some information in PCCIS to be retrieved later.
            'externalId' => $documentHash,
            'tenantId' => 'My User ID',
            // Specify the extension of the document. Although this is not required by the PCC RESTful API,
            // the web tier file HttpViewingSessionClone.cs uses this value. If this value is not set properly,
            // then the viewing session clone service is not guaranteed to work.
            'documentExtension' => $fileExtension,
            // The following are examples of arbitrary information as key-value
            // pairs that PCCIS will associate with this document request.
            'origin' => array(
                'ipAddress' => $_SERVER['REMOTE_ADDR'],
                'hostName' => $_SERVER['REMOTE_HOST']),
                //'sourceDocument' => $document),
            // Specify rendering properties.
            'render' => array(
                'flash' => array(
                    'optimizationLevel' => 1),
                'html5' => array(
                    'alwaysUseRaster' => false))
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n" .
                            "Acs-Api-Key: $acsApiKey\r\n" .
                            "Accusoft-Affinity-Hint: $documentHash\r\n",
                'content' => json_encode($data),
            ),
        );

        // Request a new viewing session from PCCIS.
        //   POST http://localhost:18681/PCCIS/V1/ViewingSession
        //
        $url = Config::getImagingService() . "/ViewingSession";
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);

        // Store the ID for this viewing session that is returned by PCCIS.
        $viewingSessionId = $response->viewingSessionId;

        return $viewingSessionId;

    }

    /**
     * Performs the transfer of the document's data to a PCC server.
     * @param $viewingSessionId The session ID received from a previous response from a PCC server
     * @param $documentContents The raw document data contained within a document file
     * @param $fileExtension The document's file extension
     */
    protected function uploadDocument($viewingSessionId, $documentContents, $fileExtension) {

        $options = array(
            'http' => array(
                'method' => 'PUT',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
                'content' => $documentContents,
            ),
        );

        // Upload File to PCCIS.
        //   PUT http://localhost:18681/PCCIS/V1/ViewingSessions/u{Viewing Session ID}/SourceFile?FileExtension={File Extension}
        // Note the "u" prefixed to the Viewing Session ID. This is required when providing
        //   an unencoded Viewing Session ID, which is what PCCIS returns from the initial POST.
        //
        $url = Config::getImagingService() . "/ViewingSession/u$viewingSessionId/SourceFile?FileExtension=$fileExtension";
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);

        $data = array(
            'viewer' => 'HTML5'
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'content' => json_encode($data),
            ),
        );

        // Start Viewing Session in PCCIS.
        //   POST http://localhost:18681/PCCIS/V1/ViewingSessions/u{Viewing Session ID}/Notification/SessionStarted
        //
        $url = Config::getImagingService() . "/ViewingSession/u$viewingSessionId/Notification/SessionStarted";
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    /**
     * Ends a current viewing session with a PCC server.
     * @param $viewingSessionId The session ID received from a previous response from a PCC server
     * @param $documentId An identifier for the document. Example: a file name
     */
    protected function endViewingSession($viewingSessionId, $documentId) {

        $url = Config::getImagingService() . "/ViewingSession/u$viewingSessionId/Notification/SessionStopped";

        $data = array(
            'endUserMessage' => "Document not found: $documentId",
            'httpStatus' => 504
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'content' => json_encode($data),
            ),
        );

        $context = stream_context_create($options);
        file_get_contents($url, false, $context);

    }
}
