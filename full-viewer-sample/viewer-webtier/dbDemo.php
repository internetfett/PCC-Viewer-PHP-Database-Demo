<?php
/**
*----------------------------------------------------------------------
*<copyright file="dbDemo.php" company="Accusoft Corporation">
*Copyright© 2015 Accusoft Corporation. All rights reserved.
*</copyright>
*----------------------------------------------------------------------
*/

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

/*
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
*/

require_once("PccViewer/Config.php");

use \PccViewer\Config as PccConfig;

PccConfig::parse(dirname(__FILE__) . "/pcc.config");

function initializeDB($conn) {
	// Because this is SQLite, make sure our tables exist before we attempt to use them
	// Create table statement with IF NOT EXISTS; transaction is ignored if the table is already present however,
	//	this will NOT validate that the assumed schema is correct.
	$conn->exec("CREATE TABLE IF NOT EXISTS document(id integer primary key, docID varchar(128) not null unique)");
	$conn->exec("CREATE TABLE IF NOT EXISTS annotation(id integer primary key, documentID integer not null, annotations text not null, username varchar(256), foreign key (documentID) references document(id))");
}

function getExternalDocId($session) {

    header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
    header("Pragma: no-cache"); //HTTP 1.0
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

    $viewingSessionId = $session;

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

	return $response->externalId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' ) {

    $queryArray = [];
    parse_str($_SERVER["QUERY_STRING"], $queryArray);

    $viewingSessionId = $queryArray["DocumentID"];
    $externalID = getExternalDocId($viewingSessionId);
    $strAnnotation = file_get_contents('php://input');

	try {
		// Create (connect to) SQLite database in file
		// Because this is SQLite, if the database file does not exist, it will be created
		$db = new PDO('sqlite:../annotations.db');
        // Set the PDO adapter to throw exceptions if something goes wrong
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		initializeDB($db);
		
			//Convert the JSON string into a PHP object
			$annotations = json_decode($strAnnotation, true);
			
			if (count($annotations) > 0){
				//	passed at least one mark, check for a username
				$user = $annotations[0]["data"]["user"];
			}else{
				//Passed some potentially invalid data that cannot be decoded to JSON or does not have a user
				//Check params for username
				$user = $queryArray["username"];
			}			

        // There is always one annotation in each annotation file, otherwise there would be no POST
        // Assuming that, we can reference the first annotation and pull out the user that we stored in the

		//Prepare insert statements
		//Instead of recreating this string with literals every time we receive new data, prepare once
		//and reuse for subsequent requests.

        // Does this document already exist?
        $selectDocumentStmt = "SELECT docID from document where docID = ?";
		$insertDocumentStmt = "INSERT INTO document (docID) VALUES (?)";

        // Does this user already have an annotation set for this document?
        $selectAnnotationStmt = "select annotations from annotation as a join document as d on a.documentID = d.id where a.username = ? and d.docID = ?";
        $insertAnnotationStmt = "INSERT INTO annotation (documentID, annotations, username) VALUES ((select id from document where docID = ?), ?, ?)";
        $updateAnnotationStmt = "update annotation set annotations = ? where username = ? and  documentID = (select id from document where docID= ?)";

		//Prepare the defined statement for use
		//Incidentally, using prepared statements avoids the risk of SQL injection. See: http://xkcd.com/327/
		$insertDoc = $db->prepare($insertDocumentStmt);
        $selectDocument = $db->prepare($selectDocumentStmt);
		$insertAnnotation = $db->prepare($insertAnnotationStmt);
        $selectAnnotation = $db->prepare($selectAnnotationStmt);
        $updateAnnotation = $db->prepare($updateAnnotationStmt);

		//Only need to insert the document and annotation file values once
        $selectDocument->execute(array($externalID));
        $selectAnnotation->execute(array($user, $externalID));

        $transactionResult = false;
        $results = $selectDocument->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            // Document already exists, don't try to reinsert
            $results = $selectAnnotation->fetchAll(PDO::FETCH_ASSOC);
            if(count($results) > 0) {
                // There is already an annotation set from the indicated user, update their annotation
                $updateAnnotation->execute(array($strAnnotation, $user, $externalID));
                $transactionResult = true;
            } else {
                // Document exist but this user did not have an annotation set
                // Insert
                $insertAnnotation->execute(array($externalID, $strAnnotation, $user));
                $transactionResult = true;
            }
        } else {
            // Document did not exist and due to constraints, there were no annotaions either
            // Insert both
            $insertDoc->execute(array($externalID));
            $insertAnnotation->execute(array($externalID, $strAnnotation, $user));
            $transactionResult = true;
        }


		//All done, remove the DB write lock
		$db = null;
	}
	catch(Exception $e) {
		// Print PDOException message
		echo $e->getMessage();
	}

	if ($transactionResult === true) {
		echo "Successful insert";
	} else {
		echo "Failed insert";
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' ) {
	//If the client is asking for marks made by a user, find what they're looking for and return it
	$username = $_GET['username'];

    $externalID = getExternalDocId($_GET['documentID']);

	try {
		//DB connection
		$db = new PDO('sqlite:../annotations.db');
        // Set the PDO adapter to throw exceptions if something goes wrong
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		initializeDB($db);

		//For demo purposes, insert at least one annotation into the database
		$count = $db->exec("Select count(*) from annotation");

		// In this sample, each may only have a single set of annotations per unique document ID
        $getAnnotationStmt = "select annotations from annotation as a join document as d on a.documentID = d.id where a.username = ? and d.docID = ?";

		$getAnnotation = $db->prepare($getAnnotationStmt);
		$getAnnotation->execute(array($username, $externalID));

		$results = $getAnnotation->fetchAll(PDO::FETCH_ASSOC);

        //die(var_dump($results[0]["annotations"]));

        if (count($results) > 0) {

            //Prep the result
            $annotationList = '[';

            $annotationList =$results[0]["annotations"];

            //Terminate the JSON
            //$annotationList .= ']';

            //Return the result to the client
            echo $annotationList;

        } else {
            echo "[]";
        }
	}
	catch(PDOException $e) {
		// Print PDOException message
		echo $e->getMessage();
	}

}
