# php-gcloud-storage-8-sdk

Classe PHP per l'interazione con gCloud Storage

Attualmente implementa i metodi: sendFile(), sendMultipleFiles(), detailFile(), getFile(), streamFile(), deleteFile() e getShortUrl().

### Requisiti

- PHP 8.0 o versione successiva

### Referenza d'uso:

Classe: gCloud_Storage

Proprietà: 
 - repositoryName : il nome del repository con cui interagire
 - Authentication : la chiave di sicurezza per l'interazione con gCloud Storage
 - LastError      : se valorizzata contiene l'ultimo messaggio d'errore ritornato da gCloud Storage
 - maxAutoRetry  : indica il numero di tentativi per cui riprovare ad eseguire l'operazione richiesta in caso di errori (default = 1)
 - autoRetrySleep : indica il tempo di attesa tra un tentativo e l'altro in caso di 'autoRetry', espresso in microsecondi (default = 1 secondi)
 - ResponseBody : risposta della chiamata REST
 - ResponseStatus : status code della chiamata REST

Metodi:

 - sendFile($localFile = NULL, $mime = self::DEFAULT_MIME_TYPE, $publicName = NULL, $fileKey = NULL)

	Questa funzione invia un file esistente sul filesystem local del server, nominato
   $localFile, avente tipo (opzionale) $mime verso gCloud Storage. Di default "invia" come nome
   del file a gCloud Storage il basename($localFile), ma questo comportamento può essere impedito
   impostando il $publicName. Inoltre se invece di caricare un documento nuovo stiamo
   aggiornando un documento già esistente, invieremo la $fileKey del documento di riferimento.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - sendMultipleFiles($localFiles = array(), $fileKey = NULL) {

	Questa funzione invia N file esistenti sul filesystem locale del server, ricevuti come parametro 
   in un array avente, per ogni elemento, le seguenti chiavi:
    * 'path' (path del file locale)
    * 'publicName' (nome del file)
    * 'mime' (mime type del file)
    Si raccomanda l'utilizzo di questa funzione esclusivamente per consentire l'esecuzione delle chain,
    nelle quali il primo plugin da eseguire necessita di processare parallelamente più di un file alla volta.
    Come anche per la funzione 'sendFile', è popssibile passare come secondo parametro una fileKey valida, 
    al fine di aggiornare un documento già esistente.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - detailFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL)

	Questa funzione ritorna i dettagli su un dato file.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - getFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL, $pluginParameters = array(), $byteRangeStart = NULL, $byteRangeEnd = NULL)

	Questa funzione ritorna il file avente $fileKey presente su gCloud Storage. La funzione ritorna
   una stringa binaria con il contenuto del file.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - streamFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL, $pluginParameters = array(), $byteRangeStart = NULL, $byteRangeEnd = NULL, $callBackFunction = NULL)

	Questa funzione effettua l'output di un file, impostando anche i necessari header http.

        E' possibile indicare un metodo di callbak per gestire in maniera personalizzata lo 
    stream del contenuto del file.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - getShortUrl($fileKey = NULL, $fileVersion = NULL, $expiresAt = NULL, $whitelist = NULL)

	Questa funzione ritorna un link per accere direttamente ad un file memorizzato all'interno di un repository privato.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.


 - deleteFile($fileKey = NULL, $fileVersion = NULL)

	Questa funzione cancella il file avente $fileKey presente su gCloud Storage. La funzione ritorna
   un oggetto con i dettagli sul file cancellato.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.


 - detailRepository()

        Questa funzione ritorna i dettagli su un dato repository.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - getResourcesList($offset = NULL, $limit = NULL, $sortBy = NULL, $getVersions = NULL, $isDeleted = NULL, $fileName = NULL)

        Questa funzione ritorna l'elenco dei file in un dato repository.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

        Le proprietà:
            - $offset e $limit consentono di indicare l'intervallo di selezione.
            - $sortBy consente di indicare il criterio di ordinamento
            - $getVersions consente di indicare se ritornare anche le versioni 'precedenti' per la stessa fileKey o solamente l'ultima
            - $isDeleted consente di indicare se ritornare i file validi o quelli cancellati
            - $fileName consente di indicare un filtro di ricerca basato sul nome del file
        
 - undeleteFile($fileKey = NULL, $fileVersion = NULL)

	Questa funzione consente di ripristinare un file precedentemente cancellato.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.

 - editFile($fileKey = NULL, $fileVersion = NULL, $fileName = NULL, $fileMimeType = NULL)

	Questa funzione consente di rettificare il nome o il mime-type di un file.

	La funzione assume che si siano correttamente impostate le proprietà $repositoryName
   e $Authentication della classe.


