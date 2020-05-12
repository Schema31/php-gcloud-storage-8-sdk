<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 7) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <fileKey> <fileVersion> <chainName> <chainSubFile> [<pluginParameters>]\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$fileKey = $argv[3];
$fileVersion = $argv[4];
$chainName = $argv[5];
$chainSubFile = $argv[6];
$pluginParameters = array(); // ATTRIBUTO FACOLTATIVO

/*
 * Es. $pluginParameters:
 * 
 * array(
 *    'nomePlugin' => array(
 *       'parametro1',
 *       'parametro2',
 *       ...........
 *       )
 * )
 */

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$file = $Storage->getFile($fileKey, $fileVersion, $chainName, $chainSubFile, $pluginParameters);

if ($file !== FALSE) {
    echo PHP_EOL;
    print_r($file);
    echo PHP_EOL;
} else {
    echo PHP_EOL;
    echo "Il sistema ha tornato errore: " . $Storage->LastError . PHP_EOL;
    echo PHP_EOL;
}
