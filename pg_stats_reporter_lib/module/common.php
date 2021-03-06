<?php
/*
 * common
 *
 * Copyright (c) 2012-2018, NIPPON TELEGRAPH AND TELEPHONE CORPORATION
 */

/* create message file list */
function createMessageFileList($file_dir, &$msg_file_list)
{
    $msg_len = strlen(MESSAGE_PREFIX);

    if (!($dir = opendir($file_dir)))
        return false;

    /* TODO: Check out message_message_ja.xml */
    while ($fn = readdir($dir)) {
        $path_parts = pathinfo($file_dir . $fn);
        if (strncmp(MESSAGE_PREFIX, $path_parts["filename"], $msg_len) == 0
            && strcmp("." . $path_parts["extension"], MESSAGE_SUFFIX) == 0) {
            $lang = str_replace(MESSAGE_PREFIX, "", $path_parts["filename"]);
            $msg_file_list[$lang] = $file_dir . $fn;
        }
    }
    closedir($dir);
    return true;
}

/* read message file */
function readMessageFile($language, $msg_file_list,
                         &$help_message, &$error_message)
{
    global $help_list;

    $locale_list = array_keys($msg_file_list);

    /*
     * if php-intl extension is available,
     * searches the locale list for the best match to the language.
     */
    if (extension_loaded('intl')) {
        $locale = locale_lookup($locale_list, $language, false, "en");
        $msgfile = $msg_file_list[$locale];
    } else {
        if (array_key_exists($language, $msg_file_list))
            $msgfile = $msg_file_list[$language];
        else
            $msgfile = $msg_file_list["en"];
    }

    if (!file_exists($msgfile)) {
        $msg = "message file(" . $msgfile . ") is not found.";
        if (!empty($_SERVER['DOCUMENT_ROOT']))
            die($msg);
        else
            elog(ERROR, $msg);
    }

    $xml = simplexml_load_file($msgfile);
    if ($xml == false) {
        $msg = "Access denied or invalid XML format (" . $msgfile . ")";
        if (!empty($_SERVER['DOCUMENT_ROOT']))
            die($msg);
        else
            elog(ERROR, $msg);
    }

    // make help message
    $err_val = $xml->xpath("/document/help/div[@id=\"error\"]");
    if (count($err_val) == 0) {
        $err_val[0] = "No help item found";
    }

    foreach ($help_list as $id_key => $id_val) {
        $val = $xml->xpath("/document/help/div[@id=\"" . $id_val . "\"]");
        if (count($val) == 0) {
            $help_message[$id_key] = "<div id=\"" . $id_val . "\">" . $err_val[0] . "</div>";
        } else {
            $help_message[$id_key] = $val[0]->asXML();
        }
    }
    // get error message
    foreach ($xml->error->p as $error) {
        $key = $error['id'];
        $error_message["$key"] = $error;
    }
    return true;
}

function getSnapshotID($conn, $targetData, &$snapids, &$snapdates)
{
    $queryString = "SELECT min(snapid), max(snapid), to_char(min(time), 'YYYYMMDD-HH24MI'), to_char(max(time), 'YYYYMMDD-HH24MI') FROM statsrepo.snapshot WHERE instid = $1 AND ";
    $setdate = false;
    if (isset($targetData["begin_date"]) || isset($targetData["end_date"])) {
        $setdate = true;
        if (!isset($targetData["begin_date"]))
            $targetData["begin_date"] = "0001-01-01 00:00:00";
        if (!isset($targetData["end_date"]))
            $targetData["end_date"] = "9999-12-31 23:59:59";
    } else {
        if (!isset($targetData["begin_id"]))
            $targetData["begin_id"] = 0;
        if (!isset($targetData["end_id"]))
            $targetData["end_id"] = PHP_INT_MAX;
    }
    /** prepare query params **/
    if ($setdate) {
        $queryString .= "time BETWEEN $2 AND $3";
        $queryParams = array($targetData["instid"], $targetData["begin_date"], $targetData["end_date"]);
        $result = pg_query_params($conn, $queryString, $queryParams);
        if (!$result)
            return false;
    } else {
        $queryString .= "snapid BETWEEN $2 AND $3";
        $queryParams = array($targetData["instid"], $targetData["begin_id"], $targetData["end_id"]);
        $result = pg_query_params($conn, $queryString, $queryParams);
        if (!$result)
            return false;
    }

    $resultData = pg_fetch_array($result, NULL, PGSQL_NUM);
    $snapids = array_slice($resultData, 0, 2);
    $snapdates = array_slice($resultData, 2);

    pg_free_result($result);

    return true;
}

/* delete configuration file cache and report cache */

/* convert the version string of PostgreSQL to version number */
function convertPGVersionNum($version_str)
{
    $vmin = 0;
    $vrev = 0;

    $ver_array = explode(".", $version_str);
    $ver_array_size = count($ver_array);

    if ($ver_array_size == 3) {
        $vmaj = $ver_array[0];
        $vmin = $ver_array[1];
        $vrev = $ver_array[2];
    } else if ($ver_array_size == 2) {
        $vmaj = $ver_array[0];
        if ($vmaj >= 10) {
            $vrev = $ver_array[1];
        } else {
            $vmin = preg_replace('/^([0-9]+).*/', '\1', $ver_array[1]);
        }
    } else {
        $vmaj = preg_replace('/^([0-9]+).*/', '\1', $ver_array[0]);
    }
    return ($vmaj * 100 + $vmin) * 100 + $vrev;
}


