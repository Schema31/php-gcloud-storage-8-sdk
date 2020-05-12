<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

/**
 * L'invio di file multipli va effettuato solo qualora si necessiti di operare su 
 * repository nei quali è definita almeno una chain in cui il primo plugin necessita 
 * di elaborare parallelamente più di un file
 */
if ($argc < 3) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> [<targetFileKey>]\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$targetFileKey = (array_key_exists(3, $argv) ? $argv[3] : NULL); // ATTRIBUTO FACOLTATIVO
$pluginParameters = array(); // ATTRIBUTO FACOLTATIVO

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$localFiles = array(
    0 => array(
        'path' => 'file1.txt.php',
        'publicName' => 'file di prova 1',
        'mime' => 'text/plain'
    ),
    1 => array(
        'path' => 'file2.txt.php',
        'publicName' => 'file di prova 2',
        'mime' => 'text/plain'
    )
);

$fileKeys = $Storage->sendMultipleFiles($localFiles, $targetFileKey, $pluginParameters);

if ($fileKeys != FALSE) {
    echo "\n";
    echo "Il sistema ha ritornato le seguenti fileKey:\n";
    print_r($fileKeys);
    echo "\n";
} else {
    echo "\n";
    echo "Il sistema ha tornato errore: " . $Storage->LastError . "\n";
    echo "\n";
}
