<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 4) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <fileKey> [<fileVersion>] [<chainName> <chainSubFile>] [<outputFolder>]\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$fileKey = $argv[3];
$fileVersion = (array_key_exists(4, $argv) ? $argv[4] : NULL); // ATTRIBUTO FACOLTATIVO
$chainName = (array_key_exists(5, $argv) ? $argv[5] : NULL);  // ATTRIBUTO FACOLTATIVO
$chainSubFile = (array_key_exists(6, $argv) ? $argv[6] : NULL); // ATTRIBUTO FACOLTATIVO
$safeFolder = (array_key_exists(7, $argv) ? $argv[7] : NULL); // ATTRIBUTO FACOLTATIVO
$pluginParameters = array(); // ATTRIBUTO FACOLTATIVO
$byteRangeStart = NULL; // ATTRIBUTO FACOLTATIVO
$byteRangeEnd = NULL; // ATTRIBUTO FACOLTATIVO
$ifModifiedSince = NULL; // ATTRIBUTO FACOLTATIVO
$eTag = NULL; // ATTRIBUTO FACOLTATIVO

$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

$file = $Storage->getFile($fileKey, $fileVersion, $chainName, $chainSubFile, $pluginParameters, $byteRangeStart, $byteRangeEnd, $ifModifiedSince, $eTag);
if (!$file || strlen($file) == 0) {
    echo PHP_EOL;
    echo "Il sistema ha tornato errore: " . $Storage->LastError . PHP_EOL;
    echo PHP_EOL;
    die;
}

/**
 * Se non è stata indicata una direcotry di destinazione, visualizziamo il contenuto del file
 */
if (strlen($safeFolder) == 0) {
    echo PHP_EOL;
    echo $file;
    echo PHP_EOL;
    die;
}

/**
 * Se non è stata indicata una direcotry di destinazione, verifichiamo che sia scrivibile
 */
if (!is_dir($safeFolder) || !is_writable($safeFolder)) {
    echo PHP_EOL;
    echo "La directory {$safeFolder} non esiste o non è scrivibile";
    echo PHP_EOL;
    die;
}

/**
 * Settiamo il nome del file in cui salvare la risorsa 
 */
$outputFilePath = $safeFolder . DIRECTORY_SEPARATOR . $Storage->fileDetails->fileName;

/**
 * Salviamo il file su disco
 */
if (file_put_contents($outputFilePath, $file) === FALSE) {
    echo PHP_EOL;
    echo "Non è stato possibile scrivere il contenuto del file in {$outputFilePath}";
    echo PHP_EOL;
    die;
}

/**
 * OK
 */
echo PHP_EOL;
echo "File salvato in {$outputFilePath}";
echo PHP_EOL;
die;
