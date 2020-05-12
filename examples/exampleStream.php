<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 4) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <fileKey> [<fileVersion>] [<chainName> <chainSubFile>]\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$fileKey = $argv[3];
$fileVersion = (array_key_exists(4, $argv) ? $argv[4] : NULL); // ATTRIBUTO FACOLTATIVO
$chainName = (array_key_exists(5, $argv) ? $argv[5] : NULL);  // ATTRIBUTO FACOLTATIVO
$chainSubFile = (array_key_exists(6, $argv) ? $argv[6] : NULL); // ATTRIBUTO FACOLTATIVO
$pluginParameters = array(); // ATTRIBUTO FACOLTATIVO
$byteRangeStart = NULL; // ATTRIBUTO FACOLTATIVO
$byteRangeEnd = NULL; // ATTRIBUTO FACOLTATIVO
$callBackFunction = NULL; // ATTRIBUTO FACOLTATIVO
$ifModifiedSince = NULL; // ATTRIBUTO FACOLTATIVO
$eTag = NULL; // ATTRIBUTO FACOLTATIVO

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$Storage->streamFile($fileKey, $fileVersion, $chainName, $chainSubFile, $pluginParameters, $byteRangeStart, $byteRangeEnd, $callBackFunction, $ifModifiedSince, $eTag);