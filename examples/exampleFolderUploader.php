<?php

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudStorageSDK\gCloud_Storage;

if ($argc < 4) {
    die("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <repositoryName> <Authentication> <srcFolder>\n\n");
}

$repositoryName = $argv[1];
$Authentication = $argv[2];
$srcFolder = $argv[3];

/*
 * Definiamo il carattere di 'newline' in base alla tipologia di richiesta (WEB /CLI)
 */
define('NEWLINE', php_sapi_name() == 'cli' ? PHP_EOL : '<br/>');

/*
 * Visaulizziamo il messaggio di avvio
 */
echo NEWLINE . NEWLINE . NEWLINE . strftime('%Y-%m-%d %H:%M:%S') . ' - INIZIO!';

/*
 * Includiamo la libreria gCloud_Storage e inizializziamo un'istanza della classe
 */
$Storage = new gCloud_Storage();
$Storage->repositoryName = $repositoryName;
$Storage->Authentication = $Authentication;

/*
 * Verifichiamo che la directory 'files' esista e che non sia vuota! 
 */
if (!file_exists($srcFolder) || !is_dir($srcFolder)) {
    echo NEWLINE . 'Directory "files" non trovata in: ' . $srcFolder;
    die();
}

/*
 * Inizializziamo la libreria per recuperare il mime-type dei file da gestire
 */
$finfo = finfo_open(FILEINFO_MIME_TYPE);

/*
 * Eseguiamo il fetch del contenuto della direcotry
 */
$fileIndex = 0;
foreach (scandir($srcFolder) as $fileName) {

    /*
     * Ignoriamo il puntamento alla directory attuale e alla directory parent
     */
    if (in_array($fileName, array('.', '..'))) {
        continue;
    }

    /*
     * Settiamo il fullPath al file
     */
    $fullPath = $srcFolder . DIRECTORY_SEPARATOR . $fileName;

    /*
     * Incrementiamo il contatore delle occorrenze
     */
    $fileIndex++;

    /*
     * Visualizziamo il nome del file da processare
     */
    echo NEWLINE . strftime('%Y-%m-%d %H:%M:%S') . ' - #' . $fileIndex . ' - file: ' . $fileName;

    /*
     * Se il file non è un 'file regolare' non possiamo eseguire l'upload
     */
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        echo ' - File non valido!!!!';
    }

    /*
     * Recuperiamo il mime-type del file
     */
    $mimeType = finfo_file($finfo, $fullPath);

    /*
     * Eseguiamo l'upload
     */
    $fileKey = $Storage->sendFile($fullPath, $mimeType, $fileName);

    /*
     * Verifichiamo lo stato
     */
    if (!$fileKey || strlen($fileKey) == 0) {
        echo ' - ERRORE - Il server ha risposto: ' . $storage->LastError;
    } else {
        echo ' - OK - fileKey: ' . $fileKey;
    }
}

/*
 * Ok - chiudiamo tutto
 */
finfo_close($finfo);

echo NEWLINE . strftime('%Y-%m-%d %H:%M:%S') . ' - FINE!';
echo NEWLINE . NEWLINE . NEWLINE;
