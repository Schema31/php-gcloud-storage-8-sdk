<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 3) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> [<offset>] [<limit>] [<sortBy>] [<getVersions>] [<isDeleted>] [<fileName>] \n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$offset = (array_key_exists(3, $argv) ? $argv[3] : 0); // ATTRIBUTO FACOLTATIVO
$limit = (array_key_exists(4, $argv) ? $argv[4] : 100); // ATTRIBUTO FACOLTATIVO
$sortBy = (array_key_exists(5, $argv) ? $argv[5] : 'dateAsc'); // ATTRIBUTO FACOLTATIVO
$getVersions = (array_key_exists(6, $argv) ? $argv[6] : FALSE); // ATTRIBUTO FACOLTATIVO
$isDeleted = (array_key_exists(7, $argv) ? $argv[7] : FALSE); // ATTRIBUTO FACOLTATIVO
$fileName = (array_key_exists(8, $argv) ? $argv[8] : NULL); // ATTRIBUTO FACOLTATIVO

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$response = $Storage->getResourcesList($offset, $limit, $sortBy, $getVersions, $isDeleted, $fileName);

if ($response !== FALSE) {
    echo PHP_EOL;
    print_r($response);
    echo PHP_EOL;
} else {
    echo PHP_EOL;
    echo "Il sistema ha tornato errore: " . $Storage->LastError . PHP_EOL;
    echo PHP_EOL;
}
