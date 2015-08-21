<?php
////error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
error_reporting(1);
require 'viewer-webtier/pccConfig.php';
require 'viewer-webtier/utils.php';

//--------------------------------------------------------------------
//
//  For this sample, the location to look for source documents
//  specified only by name and the PCC Imaging Services (PCCIS) URL
//  are configured in PCC.config.
//
//--------------------------------------------------------------------
header('Content-Type: application/json');
 
PccConfig::parse("viewer-webtier/pcc.config");

$viewingSessionId = "";
$document = null;

$documentQueryParameter = stripslashes($_FILES['theFile']['name']);
if (!empty($documentQueryParameter)) {
    $folder = PccConfig::getDocumentsPath();

    if (!is_writable ( $folder ) ) {
        header('HTTP/1.0 403 Forbidden');
        echo('<h1>403 Forbidden</h1>');
        return;
    }

    if (strstr($documentQueryParameter, "http://") || strstr($documentQueryParameter, "https://")) {
        $document = $documentQueryParameter;
        
    } else {
        $filenam = basename($documentQueryParameter);
        $filename = uniqid().$filenam;
        
        $document = Utils::combine($folder, $filename);
    }
    
    $extension = pathinfo($document, PATHINFO_EXTENSION);
    $retval = move_uploaded_file($_FILES['theFile']['tmp_name'], $document);
    $correctPath = PccConfig::isFileSafeToOpen($document);
    if (!$correctPath) {
        header('HTTP/1.0 403 Forbidden');
        echo('<h1>403 Forbidden</h1>');
        return;
    }
    
    //$data = array('viewingSessionId' => $viewingSessionId);
    $data = array('filename' => $filename);
    $common = array();
    $jsonString = json_encode($data);
    
    //$format = $_REQUEST["f"];
    $format = $_GET["f"];
    
    if($format == "jsonp"){
        header('Content-Type: text/html');
       echo "<script> window.res = " .$jsonString . ";</script>";
       // echo $jsonString;
    }
    else {
        header('Content-Type: Application/json');
        echo $jsonString;
    }
}
else {
    $data = array('error' => 'document parameter was not provided');
    echo json_encode($data);
}
?>