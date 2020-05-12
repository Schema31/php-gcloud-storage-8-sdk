<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 6) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <fileKey> <fileVersion> <fileMimeType>\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$fileKey = $argv[3];
$fileVersion = $argv[4];
$fileMimeType = $argv[5];

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$response = $Storage->editFile($fileKey, $fileVersion, NULL, $fileMimeType);

if ($response !== FALSE) {
    echo PHP_EOL;
    print_r($response);
    echo PHP_EOL;
} else {
    echo PHP_EOL;
    echo "Il sistema ha tornato errore: " . $Storage->LastError . PHP_EOL;
    echo PHP_EOL;
}
