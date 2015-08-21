<?php

namespace PccViewer;

/**
*----------------------------------------------------------------------
*<copyright file="Config.php" company="Accusoft Corporation">
*CopyrightÂ© 1996-2014 Accusoft Corporation.  All rights reserved.
*</copyright>
*----------------------------------------------------------------------
*/

/**
 * Class Config
 * @package PccViewer
 * Obtains information from a configuration file (i.e."pcc.config")
 */
class Config {

    public static $apiKey = "";
    public static $documentPath = "";
    public static $webServiceHost = "";
    public static $webServicePort = "";
    public static $webServiceScheme = "";
    public static $webServicePath = "";
    public static $webServiceV2Path = "";
    public static $webServiceUrl = "";
    public static $webServiceV2Url = "";
    public static $markupsPath = "";
    public static $imageStampPath = "";
    public static $formDefinitionPath = "";
    public static $markupLayerRecordsPath = "";
    public static $validImageStampTypes = "";
    public static $enableDocumentPath = false;
    private static $parent_tag_name;
    private static $child_tag_name;

    /**
     * replace %VARIABLES% with their values
     */
    static function inlineEnvVariables($str) {
        preg_match_all("/\\%([A-Za-z]*)\\%/", $str, $matches, PREG_OFFSET_CAPTURE);

        $ret = $str;
        for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
            $varname = $matches[1][$i][0];
            $varValue = getenv($varname);
            if ($varValue != null) {
                $ret = substr($ret, 0, $matches[0][$i][1]) .
                        $varValue .
                        substr($ret, $matches[0][$i][1] + strlen($matches[0][$i][0]));
            }
        }

        return $ret;
    }

    /**
     * handles XML element starting
     */
    public static function tagStart($parser, $name, $attrs) {
        if (is_null(self::$parent_tag_name)) {
            self::$parent_tag_name = $name;
        } else {
            if ($name == 'DocumentPath' || $name == 'WebServicePort' ||
                    $name == 'WebServiceHost' || $name == 'WebServiceScheme' || $name == 'WebServicePath' ||
                    $name == 'MarkupsPath' || $name == 'ImageStampPath' || 'ValidImageStampTypes' ||
                    $name == 'EnableDocumentPath' || $name = 'ApiKey' || $name == 'MarkupLayerRecordsPath'
            )
                self::$child_tag_name = $name;
        }
    }

    /**
     * handles XML element stop
     */
    public static function tagEnd($parser, $name) {
        if ($name == 'DocumentPath' || $name == 'WebServicePort' ||
                $name == 'WebServiceHost' || $name == 'WebServiceScheme' || $name == 'WebServicePath' ||
                $name == 'MarkupsPath' || $name == 'ImageStampPath' || 'ValidImageStampTypes' ||
                $name == 'EnableDocumentPath' || $name = 'ApiKey' || $name == 'MarkupLayerRecordsPath'
        )
            self::$child_tag_name = null;
    }

    /**
     * handles XML data
     */
    public static function tagContent($parser, $data) {
        if (self::$parent_tag_name == 'Config') {
            if (self::$child_tag_name == "ApiKey")
                self::$apiKey = $data;
            if (self::$child_tag_name == "DocumentPath")
                self::$documentPath = $data;
            if (self::$child_tag_name == "WebServiceHost")
                self::$webServiceHost = $data;
            if (self::$child_tag_name == "WebServicePort")
                self::$webServicePort = $data;
            if (self::$child_tag_name == "WebServicePath")
                self::$webServicePath = $data;
            if (self::$child_tag_name == "WebServiceV2Path")
                self::$webServiceV2Path = $data;
            if (self::$child_tag_name == "WebServiceScheme")
                self::$webServiceScheme = $data;
            if (self::$child_tag_name == "MarkupsPath")
                self::$markupsPath = $data;
            if (self::$child_tag_name == "ImageStampPath")
                self::$imageStampPath = $data;
            if (self::$child_tag_name == "MarkupLayerRecordsPath")
                self::$markupLayerRecordsPath = $data;
            if (self::$child_tag_name == "ValidImageStampTypes")
                self::$validImageStampTypes = $data;
            if (self::$child_tag_name == "EnableDocumentPath") {
                if (trim(strtolower($data)) == 'false') {
                    self::$enableDocumentPath = false;
                } else {
                    self::$enableDocumentPath = (bool) trim(strtolower($data));
                }
            }
        }
    }

    /**
     * improves path appearance
     */
    static function processPath($path, $curPath) {
        $curPath = str_replace("\\", "/", $curPath);
        if (!(strrpos($curPath, "/") === (strlen($curPath) - 1)))
            $curPath = $curPath . "/";
        if ($path == null)
            return null;
        $path = self::inlineEnvVariables($path);
        $path = str_replace("\\", "/", $path);
        if (strpos($path, "./") === 0)
            $path = $curPath . substr($path, 2);
        if (!(strrpos($path, "/") === (strlen($path) - 1)))
            $path = $path . "/";
        return $path;
    }

    /**
     * parses the pcc.config file and stores the contents
     * @param string $config_path path or name of config file
     */
    public static function parse($config_path) {
        $parser = xml_parser_create();

        //xml_set_object($parser, $this);
        xml_set_element_handler($parser, array(self, 'tagStart'), array(self, 'tagEnd'));
        xml_set_character_data_handler($parser, array(self, 'tagContent'));
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        $xml = file_get_contents($config_path);

        if (!xml_parse($parser, str_replace(array("\n", "\r", "\t"), '', $xml))) {
            echo xml_error_string(xml_get_error_code($parser));
        }

        self::$documentPath = self::processPath(self::$documentPath, realpath(dirname(__FILE__)));
        self::$markupsPath = self::processPath(self::$markupsPath, realpath(dirname(__FILE__)));
        self::$imageStampPath = self::processPath(self::$imageStampPath, realpath(dirname(__FILE__)));
        self::$markupLayerRecordsPath = self::processPath(self::$markupLayerRecordsPath, realpath(dirname(__FILE__)));
        self::$webServiceUrl = self::$webServiceScheme . '://' . self::$webServiceHost . ':' . self::$webServicePort . '/' . self::$webServicePath;
        self::$webServiceV2Url = self::$webServiceScheme . '://' . self::$webServiceHost . ':' . self::$webServicePort . '/' . self::$webServiceV2Path;
    }

    /**
     * gets the API key
     * @return string
     */
    public static function getApiKey() {
        return self::$apiKey;
    }

    /**
     * gets the $path for where the document folder resides
     * @return string
     */
    public static function getDocumentsPath() {
        return self::$documentPath;
    }

    /**
     * gets the $path for where the annotation files resides
     * @return string
     */
    public static function getMarkupsPath() {
        return self::$markupsPath;
    }

    /**
     * gets the $path for where the image stamps files resides
     * @return string
     */
    public static function getImageStampPath() {
        return self::$imageStampPath;
    }

    /**
     * gets the acceptable formats to be included as image stamps
     * @return string
     */
    public static function getValidImageStampTypes() {
        return self::$validImageStampTypes;
    }

    /**
     * gets the $path where the markup layer records reside
     * @return string
     */
    public static function getMarkupLayerRecordsPath() {
        return self::$markupLayerRecordsPath;
    }

    /**
     * gets the URL for the imaging services (PCCIS)
     * @return string
     */
    public static function getImagingService() {
        return self::$webServiceUrl;
    }

    /**
     * gets the URL for the imaging services (PCCIS)
     * @return string
     */
    public static function getImagingServiceV2() {
        return self::$webServiceV2Url;
    }

    /**
     * if enabled, checks if the local file is being opened from the configured
     * Documents path or not
     * @param string $origPath
     * @return boolean
     * @see self::$enableDocumentPath
     */
    public static function isFileSafeToOpen($origPath) {
        if (self::$enableDocumentPath == false) {
            return true;
        }
        $realPath = realpath($origPath);

        if ($realPath == false) {
            return false;
        }

        return self::$isFolderSafeToOpen(dirname($realPath));
    }

    public static function isFolderSafeToOpen($origPath) {
        if (self::$enableDocumentPath == false) {
            return true;
        }
        $fullPath = realpath($origPath);
        $docPath = realpath(self::$documentPath);
        if ($fullPath === false || $docPath === false) {
            return false;
        }
        if (startsWith($fullPath, $docPath)) {
            return true;
        }

        $markupsPath = dirname(realpath(self::$markupsPath));

        if (startsWith($fullPath, $markupsPath)) {
            return true;
        }

        $imageStampPath = dirname(realpath(self::$imageStampPath));

        if (startsWith($fullPath, $imageStampPath)) {
            return true;
        }

        $formDefinitionPath = dirname(realpath(self::$formDefinitionPath));

        if (startsWith($fullPath, $formDefinitionPath)) {
            return true;
        }

        $markupLayerRecordsPath = dirname(realpath(PccConfig::$markupLayerRecordsPath));

        if (startsWith($fullPath, $markupLayerRecordsPath)) {
            return true;
        }

        return false;
    }

}
