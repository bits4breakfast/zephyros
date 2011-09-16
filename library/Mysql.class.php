<?php
$environment = getenv('ENVIRONMENT');
if ( $environment == 'test' ) {
	define('TEST_ENVIRONMENT',true);
	define('PROD_ENVIRONMENT',false);
	error_reporting(E_ALL);
} else {
	define('TEST_ENVIRONMENT',false);
	define('PROD_ENVIRONMENT',true);
}

class Mysql {
	
	private static $instances = array();
	
	private $writeHandler = null;
	private $readHandler = null;
	private $readSelection = null;

	private $handler=null;
	
	private $server;
	private $username;
	private $password;
	private $database;
	
	private $forceSlave = false;
	
	public function __construct( $server = BaseConfig::DB_HOST, $username = BaseConfig::DB_USER, $password = BaseConfig::DB_PASSWORD, $database = BaseConfig::DB_DATABASE ){
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->connectWrite();
	}
	
	public static function init( $server = BaseConfig::DB_HOST, $username = BaseConfig::DB_USER, $password = BaseConfig::DB_PASSWORD, $database = BaseConfig::DB_DATABASE ) {
		$hash = md5(getmypid().$database.$username.$password.$server);
		
		if( !isset(self::$instances[$hash]) )
			self::$instances[$hash] = new Mysql($server, $username, $password, $database);
		
		return self::$instances[$hash];
	}
	
	public function setForceSlave() {
		$this->forceSlave = true;
	}
	
	private function connect() {
		$this->handler = new mysqli($this->server,$this->username,$this->password,$this->database);
	}
	
	private function connectWrite() {
		$this->writeHandler = new mysqli('master01',$this->username,$this->password,$this->database);
	}
	
	private function connectRead() {
		if ($this->forceSlave) {
			$this->readHandler = new mysqli('slave01',$this->username,$this->password,$this->database);
		} else {
			$temp = array("master01","slave01");
			$this->readSelection = (rand()&1);
			if (($this->readSelection == 0 && $this->writeHandler == null) || $this->readSelection != 0) {
				$slaveDelay = file_get_contents("/var/www/logs/slaveStatus.log");
				if ($this->readSelection > 0 && ( $slaveDelay == "NULL" || trim($slaveDelay) == "" || $slaveDelay == null || (int) $slaveDelay > 0)) {
					$this->readSelection = 0;
				}
				$this->readHandler = new mysqli($temp[$this->readSelection],$this->username,$this->password,$this->database);
			} elseif ($this->readSelection == 0 && $this->writeHandler != null) {
				$this->readHandler = $this->writeHandler;
			}
		}
	}
	
	public function read($query,$forceMaster=false) {
		if ( $forceMaster ) {
			return $this->write($query);
		} else {
			# if not connected
			if($this->readHandler == null)
				$this->connectRead();
			
			return $this->readHandler->query($query);
		}
	}
	
	public function write($query) {			
		return $this->writeHandler->query($query);
	}
	
	public function query($query) {
		# if not connected
		if($this->handler == null) {
			$this->connect();
		}
		
		return $this->handler->query($query);
	}

	public function result($query, $field=false,$forceMaster=true){
		$query = $this->read($query,$forceMaster);
		
		if ( $query and $query->num_rows ) {
			if ( $field ) {
				$temp = $query->fetch_assoc();
				return $temp[$field];
			} else {
				$temp = $query->fetch_row();
				return $temp[0];
			}
		}
		
		return false;
	}
	
	public function affected_rows() {
		return $this->writeHandler->affected_rows;
	}
	
	public function escape( $string ) {
		return $this->writeHandler->real_escape_string( $string );
	}
	
	public function last_id() {
		return (int) $this->writeHandler->insert_id;
	}
	
	public function start_transaction() {
		$this->writeHandler->autocommit( FALSE );
	}
	
	public function autocommit( $mode ) { // FALSE => START TRANSACTION
		$this->writeHandler->autocommit( $mode );
	}
	
	public function commit() {
		$this->writeHandler->commit();
	}
	
	public function rollback() {
		$this->writeHandler->rollback();
	}
	
	public function read_error() {
		return $this->readHandler->error;
	}
	
	public function write_error() {
		return $this->writeHandler->error;
	}

	public function __destruct() {
		if ( $this->writeHandler != null ) {
			@$this->writeHandler->close();
		}
		if ( $this->readHandler != null ) {
			@$this->readHandler->close();
		}
	}
}

?>