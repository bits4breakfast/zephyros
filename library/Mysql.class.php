<?
error_reporting(E_ALL);

define('MYSQL_HOST','master01');
define('MYSQL_USER','ehrdb');
define('MYSQL_PASS','hicsuntl3on3s');
define('MYSQL_DB','ehbox');

// definisco il percorso alla libreria nel caso non lo sia gia'
if( !defined('LIB') )
	define('LIB', '/var/www/ehbox/trunk/library');
	
define('COOKIELESS_DOMAIN', 'http://static.ehbox.imac');
define('COOKIELESS_DOMAIN_UNSECURED', 'http://static.ehbox.imac');

require_once(LIB.'/core/logger/MySQLLogger.class.php');
require_once(LIB."/core/GlobalElements.class.php");


/**
 *	Connessione a database MySQL.
 *
 *	@author Matteo Galli <matt@epoquehotels.com>
 *	@author Daniele Contini <danno@epoquehotels.com>
 *	@todo Unificare le funzioni di esecuzione query
 */
class Mysql {
	
	private static $singletonInstances = array();	///< Array of MySQL Resources. Singleton instance pointers
	
	private $writeHandler = null;
	private $readHandler = null;
	private $readSelection = null;

	private $handler=null;
	
	private $server;
	private $username;
	private $password;
	private $database;
	
	private $forceSlave = false;
	
	public static $requiresNewLink = false;
	
	
	/**
	 *	Costruttore della classe.
	 *	@todo Rendere private in caso di approvazione singleton e previa modifica del resto del codice.
	 */
	public function __construct($server=MYSQL_HOST, $username=MYSQL_USER, $password=MYSQL_PASS, $database=MYSQL_DB){
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->connect();
	}
	
	/**
	 *	MySQL Singleton Constructor.
	 *
	 *	Restituisce un istanza unica per ogni combinazione di parametri. Se non esiste, la crea e la memorizza.
	 *	ATTENZIONE! L'ordine dei parametri e' invertito, dal piu' soggetto a cambiamenti a quello piu' costante
	 */
	public static function create($database=MYSQL_DB, $username=MYSQL_USER, $password=MYSQL_PASS, $server=MYSQL_HOST) {
		$hash = md5(getmypid().$database.$username.$password.$server);
		
		if( !isset(self::$singletonInstances[$hash]) )
			self::$singletonInstances[$hash] = new Mysql($server, $username, $password, $database);
		
		return self::$singletonInstances[$hash];
	}
	
	/**
	 *	MySQL Constructor.
	 *
	 *	Forza l'istanziamento di un nuovo oggetto nel caso eccezionale in cui e' necessario aggirare il singleton
	 *	(indispensabile nel momento in cui il costruttore diventasse private)
	 *	ATTENZIONE! L'ordine dei parametri e' invertito, dal piu' soggetto a cambiamenti a quello piu' costante
	 */
	public static function forceNew($database=MYSQL_DB, $username=MYSQL_USER, $password=MYSQL_PASS, $server=MYSQL_HOST) {
		return new Mysql($server, $username, $password, $database);
	}
	
	public function setForceSlave() {
		$this->forceSlave = true;
	}
	
	private function connect() {
		$this->handler = mysql_connect($this->server,$this->username,$this->password,self::$requiresNewLink);
		mysql_select_db($this->database,$this->handler);
	}
	
	private function connectWrite() {
		$this->writeHandler = mysql_connect("master01",$this->username,$this->password,self::$requiresNewLink);
		mysql_select_db($this->database,$this->writeHandler);
	}
	
	private function connectRead() {
		if ($this->forceSlave) {
			$this->readHandler = mysql_connect("slave01",$this->username,$this->password,self::$requiresNewLink);
		} else {
			$temp = array("master01","slave01");
			$this->readSelection = (rand()&1);
			if (($this->readSelection == 0 && $this->writeHandler == null) || $this->readSelection != 0) {
				$slaveDelay = file_get_contents("/var/www/logs/slaveStatus.log");
				if ($this->readSelection > 0 && ( $slaveDelay == "NULL" || trim($slaveDelay) == "" || $slaveDelay == null || (int) $slaveDelay > 0)) {
					$this->readSelection = 0;
				}
				$this->readHandler = mysql_connect($temp[$this->readSelection],$this->username,$this->password,self::$requiresNewLink);
			} elseif ($this->readSelection == 0 && $this->writeHandler != null) {
				$this->readHandler = $this->writeHandler;
			}
		}
		mysql_select_db($this->database,$this->readHandler);
	}
	
	public function read($query,$forceMaster=false) {
		if ( $forceMaster ) {
			return $this->write($query);
		} else {
			# if not connected
			if($this->readHandler == null)
				$this->connectRead();
			
			$result = mysql_query($query, $this->readHandler);
			
			/*
try {
				$timeStart = microtime(true);
				
				$timeEnd = microtime(true);
				
				if( mysql_errno($this->readHandler) )
					throw new MySQLErrorException($query, $this->readHandler);
				
				if( 0 )
					throw new MySQLQueryException($query, $this->readHandler, $timeEnd-$timeStart);
			}
			catch(MySQLErrorException $e) {
				$e->log();
			}
*/
			
			return $result;
		}
	}
	
	public function write($query) {
		# if not connected
		if ($this->writeHandler == null)
			$this->connectWrite();
			
		$result = mysql_query($query, $this->writeHandler);
		
		/*
try {
			$timeStart = microtime(true);
			
			$timeEnd = microtime(true);
			
			if( mysql_errno($this->writeHandler) )
				throw new MySQLErrorException($query, $this->writeHandler);
			
			if( 0 )
				throw new MySQLQueryException($query, $this->writeHandler, $timeEnd-$timeStart);
		}
		catch(MySQLErrorException $e) {
			$e->log();
		}
*/
		
		return $result;
	}
	
	public function query($query) {
		# if not connected
		if($this->handler == null) {
			$this->connect();
		}
		
		$result = mysql_query($query, $this->handler);
		
		/*
try {
			$timeStart = microtime(true);
			
			$timeEnd = microtime(true);
			
			if( mysql_errno($this->handler) )
				throw new MySQLErrorException($query, $this->handler);
			
			if( 0 )
				throw new MySQLQueryException($query, $this->handler, $timeEnd-$timeStart);
		}
		catch(MySQLErrorException $e) {
			$e->log();
		}
*/
		
		return $result;
	}

	public function result($query, $field=false,$forceMaster=true){
		$resource = $this->read($query,$forceMaster);
		if($resource and mysql_num_rows($resource))
			return $field ? mysql_result($resource,0,$field) : mysql_result($resource,0);
		else
			return false;
	}

	public function __destruct() {
		if ( $this->writeHandler != null ) {
			@mysql_close($this->writeHandler);
		}
		if ( $this->readHandler != null ) {
			@mysql_close($this->readHandler);
		}
	}
}

?>