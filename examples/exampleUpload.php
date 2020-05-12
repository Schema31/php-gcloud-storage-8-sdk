<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 4) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <fileName> [<mimeType>] [<publicName>] [<targetFileKey>]\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$filename = $argv[3];
$mimeType = (array_key_exists(4, $argv) ? $argv[4] : NULL); // ATTRIBUTO FACOLTATIVO
$publicName = (array_key_exists(5, $argv) ? $argv[5] : NULL); // ATTRIBUTO FACOLTATIVO
$targetFileKey = (array_key_exists(5, $argv) ? $argv[6] : NULL); // ATTRIBUTO FACOLTATIVO
$pluginParameters = array(); // ATTRIBUTO FACOLTATIVO

if (!file_exists($filename)) {
    die("\nIl file " . $filename . " non esiste!\n\n");
} else {
    echo "\nInvio il file " . $filename . "\n";
}

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$fileKey = $Storage->sendFile($filename, $mimeType, $publicName, $targetFileKey, $pluginParameters);

if ($fileKey !== FALSE) {
    echo "\n";
    echo "Il sistema ha ritornato la seguente fileKey: " . $fileKey . "\n";
    echo "\n";
} else {
    echo "\n";
    echo "Il sistema ha tornato errore: " . $Storage->LastError . "\n";
    echo "\n";
}
