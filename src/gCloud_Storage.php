<?php

namespace Schema31\GCloudStorageSDK;

/**
 * Classe per interagire con l'API REST di gCloud Storage
 *
 * @version 1.1.40
 * @package GCloud-SDK-PHP
 */
/**
 * Dipendiamo dalla HTTP_Request2 di PEAR, caricata tramite composer
 */
require_once 'HTTP/Request2.php';

/**
 * Definizione della classe principale
 * 
 * @author Andrea Brancatelli <abrancatelli@schema31.it>
 * @package gCloud_Storage
 */
class gCloud_Storage {

    /**
     * Attuale versione della libreria
     *
     * @var string
     * @access public
     */
    const VERSION = "gCloud_Storage 1.1.40 [Composer]";

    /**
     * Mime-type standard da utilizzare quando non specificato
     */
    const DEFAULT_MIME_TYPE = "application/octet-stream";

    /**
     * Repository su cui operare
     *
     * @var string 
     * @access public
     */
    public $repositoryName = "";

    /**
     * Chiave di autenticazione
     *
     * @var string 
     * @access public
     */
    public $Authentication = "";

    /**
     * gCloud Storage Base Path (hardcoded a "storage.gcloud.schema31.it)
     *
     * @var string 
     * @access public
     */
    public $gCloudStorageHost = "storage.gcloud.schema31.it";

    /**
     * Protocollo da utilizzare per accedere alle API (http / https)
     *
     * @var string 
     * @access public
     */
    public $protocol = "https";

    /**
     * L'ultimo errore ricevuto
     *
     * @var string 
     * @access public
     */
    public $LastError = "";

    /**
     * Variabili di appoggio contenenti la risposta del server
     */
    public $ResponseBody = "";
    public $ResponseHeaders = "";
    public $ResponseStatus = "";

    /**
     * Numero di tentativi per cui riprovare ad eseguire l'operazione richiesta in caso di errori
     *
     * @var int 
     * @access public
     */
    public $maxAutoRetry = 1;

    /**
     * Contatore delle iterazioni (maxAutoRetry) eseguite.
     * Default 1
     *
     * @var int 
     * @access private
     */
    private $lastAutoRetry = 0;

    /**
     * Tempo di attesa tra un tentativo e l'altro in caso di 'autoRetry' (espesso in microsecondi).
     * default 1 secondo
     *
     * @var int 
     * @access public
     */
    public $autoRetrySleep = 1000000;

    /**
     * Contenitore per i dettagli di un file.
     * Viene inizializzato dal metodo 'getFile' per fare in modo che, a fronte di un download, 
     * sia possibile accedere anche ai metadati del file, oltre che al suo contenuto.
     *
     * @var stdClass
     * @access public
     */
    public $fileDetails = NULL;

    /*
     * Le chain possono inserire ulteriori informazioni negli header di 
     * risposta, predisponiamo una varibile di appoggio per contenerli
     */
    public $extraInfo = array();

    /**
     * script timeout (espesso in secondi)
     *
     * @var int 
     * @access public
     */
    const SCRIPT_TIMEOUT = 300;

    /**
     * Mappatura codici di ritorno
     */
    //private $putStatusCodeOK = array(200);
    private $postStatusCodeOK = array(201);
    private $getStatusCodeOK = array(200, 206);
    private $deleteStatusCodeOK = array(200);
    private $patchStatusCodeOK = array(204);
    private $skipRetryStatusCode = array(501);

    /**
     * Costruttore della classe gCloud_Storage
     */
    function __construct() {

        /*
         *  se la configurazione prevede un 'default_socket_timeout' minore di 'SCRIPT_TIMEOUT', usa 'SCRIPT_TIMEOUT'
         */
        if (ini_get('default_socket_timeout') < self::SCRIPT_TIMEOUT) {
            ini_set("default_socket_timeout", self::SCRIPT_TIMEOUT);
        }

        /*
         *  se la configurazione prevede un 'max_execution_time' minore di 'SCRIPT_TIMEOUT', usa 'SCRIPT_TIMEOUT'
         */
        if (ini_get('max_execution_time') < self::SCRIPT_TIMEOUT) {
            ini_set("max_execution_time", self::SCRIPT_TIMEOUT);
        }
        
        /**
         *  se la versione è minore di 5.6 usa http
         */
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            $this->protocol = "http";
        }
    }

    /**
     * Wrapper generale per tutti i metodi esposti dalla classe.
     * Gestisce le iterazioni 'autoRetry' ed i relativi 'autoRetrySleep' in base al valore ritornato ($response) dal metodo richiesto.
     * Se $response é FALSE, viene eseguito nuovamente il metodo richiesto, fino al raggiungimento del numero massimo di iterazioni 
     * eseguibili, aspettando 'autoRetrySleep' tra un'iterazione e l'altra
     * @return mixed output del metodo chiamato
     * @access public
     */
    public function __call($method, $arguments) {

        $response = FALSE;

        /*
         * Inizializziamo il contatore delle iterazioni
         */
        $this->lastAutoRetry = 0;

        /*
         * Assicuriamoci che il parametro '$protocol' sia valorizzato correttamente
         */
        if (!in_array($this->protocol, array('http', 'https'))) {
            $this->protocol = 'https';
        }

        /*
         *  gestione iterazioni
         */
        while ($response === FALSE && $this->lastAutoRetry <= (int) $this->maxAutoRetry) {

            try {

                /*
                 * inizializza gli attributi di appoggio
                 */

                $this->LastError = "";
                $this->ResponseBody = "";
                $this->ResponseHeaders = "";
                $this->ResponseStatus = "";
                $this->extraInfo = NULL;
                $this->fileDetails = NULL;

                /*
                 * esegue il metodo richiesto
                 */
                $response = call_user_func_array(array($this, $method), $arguments);

                /*
                 * CONVENZIONE!!!!
                 * Utilizziamo lo status code http 501 (Not Implemented) per indicare che 
                 * la richiesta, nonostante sia andata in errore, non deve essere ripetuta!
                 */
                if ($response === FALSE && in_array($this->ResponseStatus, $this->skipRetryStatusCode)) {
                    /*
                     *  esito negativo e lo status code è uno di quelli per i quali non bisogna eseguire nuovamente l'operazione
                     */
                    break;
                } elseif ($response === FALSE) {

                    /*
                     *  esito negativo - prova ad eseguire nuovamente l'operazione richiesta
                     */
                    $this->lastAutoRetry++;
                    usleep((int) $this->autoRetrySleep);
                } else {
                    /*
                     *  OK - esce dal ciclo e ritorna l'esito
                     */

                    $this->setResponseExtraInfo();

                    break;
                }
            } catch (Exception $e) {

                /*
                 *  catch eccezione - prova ad eseguire nuovamente l'operazione richiesta
                 */
                $response = FALSE;
                $this->LastError = 'Caught exception: ' . $e->getMessage();

                $this->lastAutoRetry++;
                usleep((int) $this->autoRetrySleep);
            }
        }

        return $response;
    }

    /**
     * Torna le informazioni su un dato repository
     * @return mixed torna i dettagli sul repository richiesto oppure FALSE in caso di errore
     * @access protected
     */
    protected function detailRepository() {

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/repository";

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_GET)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion());

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->getStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /*
         * Ritorna i dettagli sul file richesto
         */
        return $response;
    }

    /**
     * Ritorna l'elenco dei file presenti nel repository selezionato
     * @param int $offset indice da cui iniziare l'estrazione dei dati
     * @param int $limit numero di record da estrarre
     * @param string $sortBy tipologia ordinamento (fileKeyAsc | fileKeyDesc | dateAsc | dateDesc | fileSizeAsc | fileSizeDesc | fileNameAsc | fileNameDesc)
     * @param boolean $getVersions gestione file versionati (TRUE ritorna anche le vecchie versioni per la stessa fileKey, FALSE ritorna solo la versione corrente per la stessa fileKey)
     * @param boolean $isDeleted gestione file cancellati (TRUE ritorna i file cancellati, FALSE ritorna i file non cancellati)
     * @param string $fileName filtra per fileName
     * @return boolean|array torna un array di oggetti di tipo stdClass
     * @access protected
     */
    protected function getResourcesList($offset = NULL, $limit = NULL, $sortBy = NULL, $getVersions = NULL, $isDeleted = NULL, $fileName = NULL) {

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resources";

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_GET)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion())
                ->setHeader('offset', (strlen($offset) > 0 ? $offset : 0))
                ->setHeader('limit', (strlen($limit) > 0 ? $limit : 50))
                ->setHeader('sortBy', (strlen($sortBy) > 0 ? $sortBy : 'dateAsc'))
                ->setHeader('getVersions', ($getVersions == TRUE ? 1 : 0))
                ->setHeader('isDeleted', ($isDeleted == TRUE ? 1 : 0))
                ->setHeader('searchTarget', 'fileName')
                ->setHeader('fileName', $fileName);

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->getStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_array($response) || (is_object($response) && property_exists($response, 'Error'))) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /*
         * Ritorna l'elenco dei file
         */
        return $response;
    }

    /**
     * Carica un documento su gCloud Storage
     *
     * @param string $localFile Path del file da inviare su questo filesystem
     * @param string $mime Mime part del file da inviare (default application/octet-stream)
     * @param string $publicName Nome del file da inviare a gCloud Storage (di default prende il basename di $localFile)
     * @param string $fileKey Riferimento ad una fileKey già esistente per effettuare la "sovrascrittura" di un file
     * @param array $pluginParameters parametri di configurazione aggiuntivi per l'esecuzione delle eventuali chain di validazione / input
     * @return boolean|string torna la fileKey ritornata da gCloud Storage oppure FALSE in caso di errore
     * @access protected
     */
    protected function sendFile($localFile = NULL, $mime = self::DEFAULT_MIME_TYPE, $publicName = NULL, $fileKey = NULL, $pluginParameters = array(), $additionals = array()) {

        /**
         * Verifico se il file esiste
         */
        if (strlen($localFile) == 0 || !file_exists($localFile)) {
            $this->LastError = "File not found: {$localFile}";
            return FALSE;
        }

        /**
         * Se non ci viene passato un publicName esplicito lo ricaviamo dal nome del file da inviare
         */
        $publicName = (strlen($publicName) > 0 ? $publicName : basename($localFile));

        /**
         * Normalizziamo il fileName
         */
        $publicName = $this->normalizeFileName($publicName);

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource";

        /**
         * E' stata indicata una fileKey?
         */
        $url .= (strlen($fileKey) > 0 ? "/fileKey/{$fileKey}" : "");

        /*
         * Gestisco i parametri di configurazione aggiuntivi
         */
        foreach ($pluginParameters as $pluginName => $parameters) {

            $url .= "/{$pluginName}:";

            foreach ($parameters as $parameter) {

                if (is_string($parameter) || is_numeric($parameter)) {
                    $url .= "{$parameter}~";
                }
            }

            /*
             *  Rimuoviamo l'ultimo '~' (se presente)
             */
            $url = rtrim($url, '~');
        }

        /**
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_POST)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion())
                ->setHeader('Expect', "")
                ->addUpload('somefile', $localFile, $publicName, $mime);
        
        $this->addAdditionalsToRequest($request, $additionals);

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->postStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /**
         * Ok - ritorno la fileKey 
         */
        return $response->Result[0]->fileKey;
    }

    /**
     * Carica N documenti su gCloud Storage
     * 
     * @param array $localFiles Array contenente i file per cui effettuare l'upload
     * @param string $fileKey Riferimento ad una fileKey già esistente per effettuare la "sovrascrittura" di un file
     * @return boolean|array torna le fileKey ritornate da gCloud Storage oppure FALSE in caso di errore
     * @param array $pluginParameters parametri di configurazione aggiuntivi per l'esecuzione delle eventuali chain di validazione / input
     * @access protected
     */
    protected function sendMultipleFiles($localFiles = array(), $fileKey = NULL, $pluginParameters = array(), $additionals = array()) {

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource";

        /**
         * E' stata indicata una fileKey?
         */
        $url .= (strlen($fileKey) > 0 ? "/fileKey/{$fileKey}" : "");

        /*
         * Gestisco i parametri di configurazione aggiuntivi
         */
        foreach ($pluginParameters as $pluginName => $parameters) {

            $url .= "/{$pluginName}:";

            foreach ($parameters as $parameter) {

                if (is_string($parameter) || is_numeric($parameter)) {
                    $url .= "{$parameter}~";
                }
            }

            /*
             *  Rimuoviamo l'ultimo '~' (se presente)
             */
            $url = rtrim($url, '~');
        }

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_POST)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion())
                ->setHeader('Expect', "");
        
        $this->addAdditionalsToRequest($request, $additionals);

        /**
         * Fetch dell'array contenente i riferimenti ai file da inviare
         */
        foreach ($localFiles as $index => $localFile) {

            /*
             * estrae il file path
             */
            if (!array_key_exists('path', $localFile) || !file_exists($localFile['path'])) {
                $this->LastError = "Malformed file list";
                return FALSE;
            }
            $localFilePath = $localFile['path'];

            /*
             * estrae il mime-type
             */
            $mime = (array_key_exists('mime', $localFile) && strlen($localFile['mime']) > 0 ? $localFile['mime'] : self::DEFAULT_MIME_TYPE);

            /*
             * Se non ci viene passato un publicName esplicito lo ricaviamo dal nome del file da inviare
             */
            $publicName = ( array_key_exists('publicName', $localFile) && strlen($localFile['publicName']) > 0 ? $localFile['publicName'] : basename($localFilePath) );

            /**
             * Normalizziamo il fileName
             */
            $publicName = $this->normalizeFileName($publicName);

            /*
             *  Aggiunge all'oggetto HTTP_Request2 il file
             */
            $request->addUpload("fileUpload_{$index}", $localFilePath, $publicName, $mime);
        }

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->postStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /**
         * Ok - ritorno le fileKey 
         */
        return $response->Result;
    }

    /**
     * Torna le informazioni su un dato file
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @param string $chain nome della chain (di tipo output) da applicare
     * @param int $subFile indice del file prodotto della chain (di tipo output)
     * @return mixed torna i dettagli sulla risorsa richiesta oppure FALSE in caso di errore
     * @access protected
     */
    protected function detailFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource/fileKey/{$fileKey}";

        /*
         * se non è stata indicata una versione specifica o è stato passato il carattere '_', usa l'ultima versione disponibile
         */
        $url .= (strlen($fileVersion) > 0 && $fileVersion != '_' ? "/fileVersion/$fileVersion" : '/_' );

        /*
         *  se si sta richedendo un file generato da una chain, è necessario indicare sia il nome della chain che l'indice del file di output
         */
        if ((strlen($chain) > 0 && strlen($subFile) == 0) || (strlen($chain) == 0 && strlen($subFile) > 0)) {
            $this->LastError = "No chain-name / chain-fileIndex supplied";
            return FALSE;
        } elseif (strlen($chain) > 0 && strlen($subFile) > 0) {
            $url .= "/chain/$chain/chainFileIndex/$subFile";
        }

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_GET)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion());

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->getStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /*
         * Ritorna i dettagli sul file richesto
         */
        return $response;
    }

    /**
     * Scarica un documento da gCloud Storage
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @param string $chain nome della chain (di tipo output) da applicare
     * @param int $subFile indice del file prodotto della chain (di tipo output)
     * @param array $pluginParameters parametri di configurazione aggiuntivi per l'esecuzione dell' eventuale chain di output
     * @return mixed torna l'url per accedere alla risorsa richiesta oppure FALSE in caso di errore
     * @access protected
     */
    protected function prepareResourceGetURL($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL, $pluginParameters = array()) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /*
         *  se si sta richedendo un file generato da una chain, è necessario indicare sia il nome della chain che l'indice del file di output
         */
        if ((strlen($chain) > 0 && strlen($subFile) == 0) || (strlen($chain) == 0 && strlen($subFile) > 0)) {
            $this->LastError = "No chain-name / chain-fileIndex supplied";
            return FALSE;
        }
        /*
         * se non è stata indicata una versione specifica o è stato passato il carattere '_', usa l'ultima versione disponibile
         */
        $fileVersion = (strlen($fileVersion) > 0 && $fileVersion != '_' ? $fileVersion : '_' );


        /*
         * anche in presenza degli attributi $chain e $subFile, vengono estratte le informazioni relative alla risorsa 'master' in quanto:
         * 1. la risorsa 'master' è utilizzata come riferimento anche per tutte le eventuali risorse generate da una chain
         * 2. la risorsa a cui si sta puntando potrebbe essere generata da una chain di tipo 'OUTPUT', quindi fisicamente non presente a sistema
         */
        $response = $this->detailFile($fileKey, $fileVersion);

        if ($response === FALSE) {
            /*
             *  file non valido - l'attributo 'LastError' è stato settato nel metodo 'detailFile'
             */
            return FALSE;
        }

        /*
         *  rendo accessibile i dettagli relativi alla risorsa 'master'
         */
        $this->fileDetails = $response;

        /**
         * Preparo il base url
         */
        $url = "$response->friendlyUrl";

        /*
         * Se la chiamata al metodo 'detailFile' è stata effettuata indicando una fileVersion 
         * specifica (non '_'), la fileVersion sarà già stata inclusa nel friendlyUrl, non va 
         * pertanto aggiunta nuovamente all'URL 
         */
        $url .= ($fileVersion == "_" ? "/_" : "");

        /*
         *  aggiungo al base url i riferimenti alla chain (se indicata) e gli eventuali parametri aggiuntivi per le plugin
         */
        if (strlen($chain) > 0 && strlen($subFile) > 0) {

            /*
             * Aggiorno il base url
             */
            $url .= "/$chain/$subFile";

            /*
             * Gestisco i parametri di configurazione aggiuntivi
             */
            foreach ($pluginParameters as $pluginName => $parameters) {

                $url .= "/{$pluginName}:";

                foreach ($parameters as $parameter) {

                    if (is_string($parameter) || is_numeric($parameter)) {
                        $url .= "{$parameter}~";
                    }
                }

                /*
                 *  Rimuoviamo l'ultimo '~' (se presente)
                 */
                $url = rtrim($url, '~');
            }
        }

        return $url;
    }

    /**
     * Scarica un documento da gCloud Storage
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @param string $chain nome della chain (di tipo output) da applicare
     * @param int $subFile indice del file prodotto della chain (di tipo output)
     * @param array $pluginParameters parametri di configurazione aggiuntivi per l'esecuzione dell' eventuale chain di output
     * @param int $byteRangeStart indice del byte da cui iniziare la lettura
     * @param int $byteRangeEnd indice del byte in cui terminare la lettura (NULL = fine del file)
     * @param datetime $ifModifiedSince (eg. Tue, 23 Jun 2015 08:38:21 GMT)
     * @param string $eTag (if-none-match)
     * @return mixed torna il contenuto della risorsa richiesta oppure FALSE in caso di errore
     * @access protected
     */
    protected function getFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL, $pluginParameters = array(), $byteRangeStart = NULL, $byteRangeEnd = NULL, $ifModifiedSince = NULL, $eTag = NULL, $additionals = array()) {

        /*
         * Estraiamo il friendly url
         */
        $url = $this->prepareResourceGetURL($fileKey, $fileVersion, $chain, $subFile, $pluginParameters);
        if ($url === FALSE) {
            return FALSE;
        }

        /*
         * Validazione parametri richiesta byte-range
         */
        if (!is_null($byteRangeStart)) {

            if ($byteRangeStart != (int) $byteRangeStart || $byteRangeStart < 0) {
                $this->LastError = "Invalid 'byteRangeStart' value supplied";
                return FALSE;
            } elseif (strlen($byteRangeEnd) > 0 && ($byteRangeEnd != (int) $byteRangeEnd || $byteRangeEnd < 0)) {
                $this->LastError = "Invalid 'byteRangeEnd' value supplied";
                return FALSE;
            }

            $byteRange = "bytes={$byteRangeStart}-{$byteRangeEnd}";
        } else {
            $byteRange = FALSE;
        }

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_GET)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion());
        
        $this->addAdditionalsToRequest($request, $additionals);

        if ($byteRange) {
            $request->setHeader('Range', $byteRange);
        }

        if (strlen($ifModifiedSince) > 0 && DateTime::createFromFormat('D, d M Y H:i:s e', $ifModifiedSince)) {
            $request->setHeader('If-Modified-Since', $ifModifiedSince);
        }

        if (strlen($eTag) > 0) {
            $request->setHeader('If-None-Match', $eTag);
        }

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->getStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /*
         * Ritorna il contenuto del file richiesto
         */
        return $this->ResponseBody;
    }

    /**
     * Esegue l'output di un documento su gCloud Storage settando gli opportuni header http
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @param string $chain nome della chain (di tipo output) da applicare
     * @param int $subFile indice del file prodotto della chain (di tipo output)
     * @param array $pluginParameters parametri di configurazione aggiuntivi per l'esecuzione dell' eventuale chain di output
     * @param int $byteRangeStart indice del byte da cui iniziare la lettura
     * @param int $byteRangeEnd indice del byte in cui terminare la lettura (NULL = fine del file)
     * @param datetime $ifModifiedSince (eg. Tue, 23 Jun 2015 08:38:21 GMT)
     * @param string $eTag (if-none-match)
     * @return boolean true in assenza di errori, altrimenti false
     * @access protected
     */
    protected function streamFile($fileKey = NULL, $fileVersion = NULL, $chain = NULL, $subFile = NULL, $pluginParameters = array(), $byteRangeStart = NULL, $byteRangeEnd = NULL, $callBackFunction = NULL, $ifModifiedSince = NULL, $eTag = NULL, $additionals = array()) {

        /*
         * Estraiamo il friendly url
         */
        $url = $this->prepareResourceGetURL($fileKey, $fileVersion, $chain, $subFile, $pluginParameters);
        if ($url === FALSE) {
            return FALSE;
        }

        /*
         * Validazione parametri richiesta byte-range
         */
        if (!is_null($byteRangeStart)) {

            if ($byteRangeStart != (int) $byteRangeStart || $byteRangeStart < 0) {
                $this->LastError = "Invalid 'byteRangeStart' value supplied";
                return FALSE;
            } elseif (strlen($byteRangeEnd) > 0 && ($byteRangeEnd != (int) $byteRangeEnd || $byteRangeEnd < 0)) {
                $this->LastError = "Invalid 'byteRangeEnd' value supplied";
                return FALSE;
            }

            $byteRange = "bytes={$byteRangeStart}-{$byteRangeEnd}";
        } else {
            $byteRange = FALSE;
        }

        /*
         * Prepariamo il context per la richiesta
         */
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'max_redirects' => 20,
                'header' => array(
                    "Content-type: application/x-www-form-urlencoded",
                    "User-Agent: " . $this->getVersion(),
                    "Authentication: {$this->Authentication}",
                    "repositoryName: {$this->repositoryName}"
                )
            )
        );
                    
        if (is_array($additionals) && count($additionals) > 0) {
            foreach ($additionals as $headerName => $headerValue) {
                $opts['http']['header'][] = "X-$headerName: {$headerValue}";
            }            
        }

        if ($byteRange) {
            $opts['http']['header'][] = "Range: {$byteRange}";
        }

        if (strlen($ifModifiedSince) > 0 && DateTime::createFromFormat('D, d M Y H:i:s e', $ifModifiedSince)) {
            $opts['http']['header'][] = "If-Modified-Since: {$ifModifiedSince}";
        }

        if (strlen($eTag) > 0) {
            $opts['http']['header'][] = "If-None-Match:{$eTag}";
        }

        $_default_opts = stream_context_get_params(stream_context_get_default());

        $context = stream_context_create(array_merge_recursive($_default_opts['options'], $opts));

        /*
         * Apriamo lo stream
         */
        $fp = fopen($url, 'rb', false, $context);

        /*
         * http://php.net/manual/en/reserved.variables.httpresponseheader.php
         * 
         * The $http_response_header array is similar to the get_headers() function. 
         * When using the HTTP wrapper, $http_response_header will be populated with the 
         * HTTP response headers. $http_response_header will be created in the local scope. 
         */

        /*
         * Stream valido?
         */
        if ($fp === FALSE || !is_array($http_response_header)) {
            header("HTTP/1.0 404 Not Found");
            return FALSE;
        }

        /*
         * Come prima cosa, formattiamo gli header ricevuti
         */
        $this->ResponseHeaders = array();
        foreach ($http_response_header as $header) {
            $this->parseHeaderLine($header);
        }

        /*
         * Utilizziamo una callback per gestire la risposta?
         * i casi sono 3:
         * 1. si e la callback è una funzione
         * 2. si e la callback è un metodo di una classe
         * 3. no, procediamo autonomamente
         */

        if (is_string($callBackFunction) && strlen($callBackFunction) > 0 && function_exists($callBackFunction)) {
            /*
             * La callback è una funzione.
             * richiamiamo la funzione passandogli come parametri una reference dello stream e gli header ricevuti (sia raw che formattati)
             */

            call_user_func_array($callBackFunction, array(&$fp, &$http_response_header, &$this->ResponseHeaders));
        } elseif (is_array($callBackFunction) && array_key_exists(0, $callBackFunction) && is_object($callBackFunction[0]) && array_key_exists(1, $callBackFunction) && method_exists($callBackFunction[0], $callBackFunction[1])) {

            /*
             * La callback è un metodo di una classe
             * richiamiamo il metodo passandogli come parametri una reference dello stream e gli header ricevuti (sia raw che formattati)
             */
            $callbackClass = $callBackFunction[0];
            $callbackMethod = $callBackFunction[1];

            call_user_func(array($callbackClass, $callbackMethod), array(&$fp, &$http_response_header, &$this->ResponseHeaders));
        } else {

            /*
             * Nessuna callback 
             * 
             * Ritorniamo tutti gli header ricevuti da Storage, ad eccezione del cookie balancerID
             */
            foreach ($http_response_header as $header) {

                if (strpos($header, 'Set-Cookie') > -1) {
                    /*
                     * SKIP 'Set-Cookie' header!!!!
                     */
                    continue;
                }
                /*
                 * Return unchanged header
                 */
                header($header);
            }

            /*
             * Svuotiamo forzatamente l'output buffer 
             * (usiamo la soppressione degli errori perchè potrebbe non essere attivo)
             */
            @ob_end_clean();

            /*
             * Avviamo il buffering dell'output
             */
            ob_start();

            while (!feof($fp)) {

                /*
                 * Ritorniamo subito i dati
                 */
                echo fread($fp, 2048);

                /*
                 * Effettuiamo il flush
                 */
                ob_flush();
            }
        }

        /*
         * Chiudiamo lo stream (se non è già stato chiuso nella callback)
         */
        if (is_resource($fp)) {
            fclose($fp);
        }

        return TRUE;
    }

    /**
     * Ripristina un file precedentemente cancellato su gCloud Storage
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @return boolean TRUE (oppure FALSE in caso di errore)
     * @access protected
     */
    protected function undeleteFile($fileKey = NULL, $fileVersion = NULL) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource/fileKey/{$fileKey}";

        /*
         * Gestisce l'eventuale parametro $fileVersion
         */
        $url .= (strlen($fileVersion) > 0 ? "/fileVersion/{$fileVersion}" : "");

        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod('PATCH')
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('undeleteFile', 1)
                ->setHeader('User-Agent', $this->getVersion());

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->patchStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Modifica un file su gCloud Storage
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @param string $fileName il nuovo nome da associare al file
     * @param string $fileMimeType il nuovo mime-type da associare al file
     * @return boolean TRUE (oppure FALSE in caso di errore)
     * @access protected
     */
    protected function editFile($fileKey = NULL, $fileVersion = NULL, $fileName = NULL, $fileMimeType = NULL) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource/fileKey/{$fileKey}";

        /*
         * Gestisce l'eventuale parametro $fileVersion
         */
        $url .= (strlen($fileVersion) > 0 ? "/fileVersion/{$fileVersion}" : "");

        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod('PATCH')
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion());

        // rinominare il file?
        if (strlen($fileName) > 0) {
            $request
                    ->setHeader('editFileName', 1)
                    ->setHeader('fileName', $fileName);
        }

        // rettificare il mime type?
        if (strlen($fileMimeType) > 0) {
            $request
                    ->setHeader('editFileMimeType', 1)
                    ->setHeader('fileMimeType', $fileMimeType);
        }

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->patchStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Cancella un file da gCloud Storage
     * @param string $fileKey identificativo del file
     * @param int $fileVersion versione del file
     * @return mixed torna i dettagli sulla risorsa cancellata oppure FALSE in caso di errore
     * @access protected
     */
    protected function deleteFile($fileKey = NULL, $fileVersion = NULL) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/resource/fileKey/{$fileKey}";

        /*
         * Gestisce l'eventuale parametro $fileVersion
         */
        $url .= (strlen($fileVersion) > 0 ? "/fileVersion/{$fileVersion}" : "");

        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);
        $request->setMethod(\HTTP_Request2::METHOD_DELETE)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('User-Agent', $this->getVersion());

        /**
         * Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->deleteStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /*
         * Ritorna la risposta
         */
        return $response;
    }

    /**
     * Ritorna lo short-url per accedere ad una risorsa memorizzata all'interno di un repository privato
     * @param string $fileKey
     * @param int $fileVersion (facoltativo - se omesso ritorna l'ultima versione per la fileKey passata come parametro)
     * @param int $expiresAt (facoltativo - indica il numero di secondi per cui il link generato sarà valido)
     * @param string $whitelist (facoltativo - lista di indirizzi IP, separati da punto e virgola, abilitati ad accedere al link generato )
     * @return boolean|string url della risorsa oppure FALSE in caso di errore
     * @access protected
     */
    protected function getShortUrl($fileKey = NULL, $fileVersion = NULL, $expiresAt = NULL, $whitelist = NULL) {

        /*
         * E' stata indicata una fileKey?
         */
        if (strlen($fileKey) == 0) {
            $this->LastError = "No fileKey supplied";
            return FALSE;
        }

        /**
         * Preparo il base url
         */
        $url = "{$this->protocol}://{$this->gCloudStorageHost}/api/shorturl";

        /*
         * Creiamo un'istanza dell'oggetto Request2 definendo tutti i parametri
         */
        $request = new \HTTP_Request2($url);
        $request->setConfig('follow_redirects', TRUE);
        $request->setConfig('max_redirects', 20);
        $request->setConfig('strict_redirects', TRUE);        
        $request->setMethod(\HTTP_Request2::METHOD_POST)
                ->setHeader('Authentication', $this->Authentication)
                ->setHeader('repositoryName', $this->repositoryName)
                ->setHeader('fileKey', $fileKey)
                ->setHeader('User-Agent', $this->getVersion())
                ->setHeader('Expect', "");

        /*
         *  è stata indicata una fileVersion?
         */
        if (strlen($fileVersion) > 0) {
            $request->setHeader('fileVersion', $fileVersion);
        }

        /*
         *  è stata indicata una durata massima per la validità dell'url?
         */
        if (strlen($expiresAt) > 0) {
            $request->setHeader('expiresAt', $expiresAt);
        }

        /*
         *  è stata indicata una white-list di indirizzi IP, i quali potranno accedere alla risorsa?
         */
        if (strlen($whitelist) > 0) {
            $request->setHeader('whitelist', $whitelist);
        }

        /*
         *  Catturo la response della richiesta
         */
        $HTTPRequest2Response = $request->send();
        $this->ResponseBody = $HTTPRequest2Response->getBody();
        $this->ResponseHeaders = $HTTPRequest2Response->getHeader();
        $this->ResponseStatus = $HTTPRequest2Response->getStatus();

        /*
         * Validazione http status code
         */
        if (!in_array($this->ResponseStatus, $this->postStatusCodeOK)) {
            $this->LastError = "Response status code: {$this->ResponseStatus}";
            return FALSE;
        }

        /**
         * Gestiamo il contenuto della risposta
         */
        $response = json_decode($this->ResponseBody);
        if (!is_object($response) || property_exists($response, 'Error')) {
            $this->LastError = $this->ResponseBody;
            return FALSE;
        }

        /*
         * ritorna lo short url
         */
        return $response->shortUrl;
    }

    /**
     * Costruisce il Paccchetto di Versamento per la tipologia 'Documento Generico'.
     * @param array $srcFiles lista dei file da includere nel pacchetto di versamento; per ogni file è obbligatorio indicare almeno in path.
     * @return XML
     */
    public function buildPdV($srcFiles = array()) {

        /**
         * Innanzitutto verifichiamo che siano stati indicati 1 o più file e che i parametri siano validi
         */
        if (!is_array($srcFiles) || count($srcFiles) == 0) {
            $this->LastError = "No files supplied";
            return FALSE;
        }

        /**
         * Predisponiamo la lista 'formattata' dei file da gestire
         */
        $files = array();
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        foreach ($srcFiles as $srcIndex => $srcFile) {

            /**
             * E' stato indicato un path valido?
             */
            $filePath = $srcFile->filePath;
            if (!isset($filePath) || strlen($filePath) == 0) {
                $this->LastError = "Malformed input parameters";
                return FALSE;
            } elseif (!is_file($filePath)) {
                $this->LastError = "File not found: {$filePath}";
                return FALSE;
            }

            /**
             * Recuperiamo fileName e mimeType,  poi aggiungiamo l'elemento alla lista di quelli da processare 
             */
            $file = new stdClass();
            $file->filePath = $filePath;
            $file->fileName = isset($srcFile->fileName) && strlen($srcFile->fileName) > 0 ? $srcFile->fileName : basename($srcFile->fileName);
            $file->mimeType = isset($srcFile->mimeType) && strlen($srcFile->mimeType) > 0 ? $srcFile->mimeType : finfo_file($finfo, $srcFile->filePath);

            /**
             * Recuperiamo i metadati base
             */
            $file->documentId = isset($srcFile->documentId) && strlen($srcFile->documentId) == 20 ? $srcFile->documentId : ('gCloud_' . time() . '_' . rand(10, 99));
            $file->subject = isset($srcFile->subject) && strlen($srcFile->subject) > 0 ? $srcFile->subject : "{$file->documentId} - ({$srcIndex})";
            $files[] = $file;
        }
        finfo_close($finfo);

        /**
         * Ok, procediamo!
         * Inizializziamo l'oggetto DOMDocument
         */
        $outputXml = new DOMDocument("1.0", "UTF-8");

        /**
         * Creiamo il nodo root
         */
        $rootElement = $outputXml->createElement('pdv');
        $outputXml->appendChild($rootElement);

        /**
         * Creiamo il nodo contenitore per i documenti
         */
        $objStored = $outputXml->createElement('obj-stored');
        $rootElement->appendChild($objStored);

        /**
         * Creiamo il nodo contenitore per i metadati
         */
        $idv = $outputXml->createElement('ipdv');
        $rootElement->appendChild($idv);

        /**
         * Inizializziamo le variabili di appoggio per concatenare gli hash dei documenti e i metadati RAW
         */
        $hashObjects = '';
        $metaRawContent = '';

        /**
         * Iteriamo sui documenti
         */
        foreach ($files as $file) {

            /**
             * Recuperiamo il contenuto del file
             */
            $fileContent = file_get_contents($file->filePath);

            /**
             * Calcoliamo l'hash del documento
             */
            $hash = hash("sha256", $fileContent);

            /**
             * Creiamo il nodo contenente il documento
             */
            $docNode = $outputXml->createElement("document", base64_encode($fileContent));
            $docNode->setAttribute("type", $file->mimeType);
            $docNode->setAttribute("name", $file->fileName);
            $docNode->setAttribute("hashType", 'SHA256');
            $docNode->setAttribute("hash", $hash);

            /**
             * Aggiungiamo il nodo
             */
            $objStored->appendChild($docNode);

            /**
             * Creiamo il nodo contenente i metadati
             */
            $metaNode = $outputXml->createElement("documento");
            $metaNode->setAttribute("IDDocumento", $file->documentId);

            $metaNodeDatachiusura = $outputXml->createElement("datachiusura", date('Y-m-d'));
            $metaNode->appendChild($metaNodeDatachiusura);

            $metaNodeOggettoDocumento = $outputXml->createElement("oggettodocumento", $file->subject);
            $metaNode->appendChild($metaNodeOggettoDocumento);

            $metaNodeSoggettoProduttore = $outputXml->createElement("soggettoproduttore");
            $metaNodeSoggettoProduttoreNome = $outputXml->createElement("nome", "Schema31 S.p.A.");
            $metaNodeSoggettoProduttore->appendChild($metaNodeSoggettoProduttoreNome);
            $metaNodeSoggettoProduttoreCognome = $outputXml->createElement("cognome", "Schema31 S.p.A.");
            $metaNodeSoggettoProduttore->appendChild($metaNodeSoggettoProduttoreCognome);
            $metaNodeSoggettoProduttoreCF = $outputXml->createElement("codicefiscale", "05334830485");
            $metaNodeSoggettoProduttore->appendChild($metaNodeSoggettoProduttoreCF);
            $metaNode->appendChild($metaNodeSoggettoProduttore);

            $metaNodeSoggettoDestinatario = $outputXml->createElement("destinatario");
            $metaNodeSoggettoDestinatarioNome = $outputXml->createElement("nome", "Andrea");
            $metaNodeSoggettoDestinatario->appendChild($metaNodeSoggettoDestinatarioNome);
            $metaNodeSoggettoDestinatarioCognome = $outputXml->createElement("cognome", "Brancatelli");
            $metaNodeSoggettoDestinatario->appendChild($metaNodeSoggettoDestinatarioCognome);
            $metaNodeSoggettoDestinatarioCF = $outputXml->createElement("codicefiscale", "BRNNDR79D12L424O");
            $metaNodeSoggettoDestinatario->appendChild($metaNodeSoggettoDestinatarioCF);
            $metaNode->appendChild($metaNodeSoggettoDestinatario);

            $metaNodeMoreInfo = $outputXml->createElement("MoreInfo");
            $metaNodeMoreInfoMimeType = $outputXml->createElement("mime-type", $file->mimeType);
            $metaNodeMoreInfo->appendChild($metaNodeMoreInfoMimeType);
            $metaNodeMoreInfoDocumentClass = $outputXml->createElement("document-class", "DocumentoGenerico");
            $metaNodeMoreInfo->appendChild($metaNodeMoreInfoDocumentClass);
            $metaNodeMoreInfoEmbedded = $outputXml->createElement("Embedded");
            $metaNodeMoreInfo->appendChild($metaNodeMoreInfoEmbedded);
            $metaNode->appendChild($metaNodeMoreInfo);

            /**
             * Aggiungiamo il nodo
             */
            $idv->appendChild($metaNode);

            /**
             * Concateniamo gli HASH dei documenti e i metadati RAW
             */
            $hashObjects .= $hash;
            $metaRawContent .= $metaNode->C14N();
        }

        /*
         * Aggiungiamo gli hash 'complessivi' al PdV
         */
        $idv->appendChild($outputXml->createElement("hash-ipdv", hash("sha256", $metaRawContent)));
        $idv->appendChild($outputXml->createElement("hash-object", hash("sha256", $hashObjects)));
        $idv->appendChild($outputXml->createElement("store-time", '12 years'));

        /**
         * Finalizziamo il PdV
         */
        return $outputXml->C14N();
    }

    /*
     * Utilizziamo lo stesso metodo della classe 'HTTP_Request2_Response' 
     * (per uniformare le risposte) per effettuare il parsing degli header di 
     * risposta quando non utilizziamo la classe 'HTTP_Request2'
     */

    private function parseHeaderLine($headerLine) {

        $headerLine = trim($headerLine, "\r\n");

        $lastHeader = NULL;

        if ('' == $headerLine) {
            // empty string signals the end of headers, process the received ones
            if (!empty($this->ResponseHeaders['set-cookie'])) {
                $cookies = is_array($this->ResponseHeaders['set-cookie']) ?
                        $this->ResponseHeaders['set-cookie'] :
                        array($this->ResponseHeaders['set-cookie']);
                foreach ($cookies as $cookieString) {
                    $this->parseCookie($cookieString);
                }
                unset($this->ResponseHeaders['set-cookie']);
            }
            foreach (array_keys($this->ResponseHeaders) as $k) {
                if (is_array($this->ResponseHeaders[$k])) {
                    $this->ResponseHeaders[$k] = implode(', ', $this->ResponseHeaders[$k]);
                }
            }
        } elseif (preg_match('!^([^\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]+):(.+)$!', $headerLine, $m)) {
            // string of the form header-name: header value
            $name = strtolower($m[1]);
            $value = trim($m[2]);
            if (empty($this->ResponseHeaders[$name])) {
                $this->ResponseHeaders[$name] = $value;
            } else {
                if (!is_array($this->ResponseHeaders[$name])) {
                    $this->ResponseHeaders[$name] = array($this->ResponseHeaders[$name]);
                }
                $this->ResponseHeaders[$name][] = $value;
            }
            $lastHeader = $name;
        } elseif (preg_match('!^\s+(.+)$!', $headerLine, $m) && $lastHeader) {
            // continuation of a previous header
            if (!is_array($this->ResponseHeaders[$lastHeader])) {
                $this->ResponseHeaders[$lastHeader] .= ' ' . trim($m[1]);
            } else {
                $key = count($this->ResponseHeaders[$lastHeader]) - 1;
                $this->ResponseHeaders[$lastHeader][$key] .= ' ' . trim($m[1]);
            }
        }
    }

    /**
     * Popola l'attributo contenente le info aggiuntive ritornate da gCloud Storage
     */
    private function setResponseExtraInfo() {

        foreach ($this->ResponseHeaders as $headerName => $headerValue) {

            if (strtolower(substr($headerName, 0, 18)) == 'responseextrainfo_') {
                $this->extraInfo[substr($headerName, 18)] = $headerValue;
            }
        }
    }

    /*
     * Effettua il quoting dei doppi apici per compatibilità con '/usr/share/pear/HTTP/Request2/MultipartBody.php'
     * 
     * $_headerParam = "--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n";
     * $_headerUpload = "--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n";
     * 
     */

    private function normalizeFileName($fileName) {

        return str_replace('"', '\"', $fileName);
    }

    protected function getVersion() {
        return static::VERSION;
    }
    
    protected function addAdditionalsToRequest($request, $additionals = array()) {
        if (is_array($additionals) && count($additionals) > 0) {
            foreach ($additionals as $headerName => $headerValue) {
                $request->setHeader("X-$headerName", $headerValue);
            }
        }
    }

}
