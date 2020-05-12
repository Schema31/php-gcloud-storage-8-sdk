<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 6) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <filePath> <documentId> <subject>\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$filePath = $argv[3];
$documentId = $argv[4];
$subject = $argv[5];

/**
 * Il file da conservare esiste?
 */
if (!file_exists($filePath) || !is_readable($filePath) || !is_file($filePath)) {
    die("\n\nAttenzione!\n\nFile non valido!\n\n");
}

/**
 * Settiamo i dettagli del file da inviare in conservazione
 */
$myDocument = new stdClass();
$myDocument->filePath = $filePath;
$myDocument->fileName = basename($filePath);
$myDocument->mimeType = mime_content_type($filePath);
$myDocument->documentId = $documentId; // Dimensione fissa (20 caratteri)
$myDocument->subject = $subject;

/**
 * Il metodo 'buildPdV' consente di creare dei bundle di documenti, aggiungiamo quindi il file ad una lista.
 */
$files = array($myDocument);

/**
 * Inizializziamo un'istanza della classe gCloud_Storage
 */
$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

/**
 * Generiamo il PdV
 */
$PdV = $Storage->buildPdV($files);
if (!$PdV) {
    die("\n\nAttenzione!\n\nSi è verificato un errore nella costruzione dl PdV: {$Storage->LastError}\n\n");
}

/**
 * Creiamo localmente un file temporaneo per il PdV
 */
$PdVFilePath = tempnam(sys_get_temp_dir(), 'gCloudPdV_');

/**
 * Effettuiamo il salvataggio del PdV su gCloud
 */
$fileKey = $Storage->sendFile($PdVFilePath, 'application/xml', 'gCloud_Storage_PdV.xml');

/**
 * Rimuoviamo il file temporaneo
 */
@unlink($PdVFilePath);

/**
 * Verifichiamo l'esito del salvataggio
 */
if (!$fileKey) {
    die("\n\nAttenzione!\n\nSi è verificato un errore nel salvataggio su gCloud: {$Storage->LastError}\n\n");
}

/**
 * Fatto!
 */
echo "\n";
echo "Il sistema ha ritornato la seguente fileKey: {$fileKey}\n";
echo "\n";
