<?php
class Mysql {
	
	private static $instances = array();
	
	private $writeHandler = null;
	private $readHandler = null;
	
	public function __construct(){
		$this->connectWrite();
	}
	
	public static function init(  ) {
		$hash = md5( getmypid() . BaseConfig::DB_USER . BaseConfig::DB_PASSWORD . BaseConfig::DB_DATABASE );
		
		if( !isset(self::$instances[$hash]) )
			self::$instances[$hash] = new Mysql();
		
		return self::$instances[$hash];
	}
	
	private function connectWrite() {
		$this->writeHandler = new mysqli( BaseConfig::DB_MASTER_HOST, BaseConfig::DB_USER, BaseConfig::DB_PASSWORD, BaseConfig::DB_DATABASE );
	}
	
	private function connectRead() {
		$temp = array("master01","slave01");
		$readSelection = (rand()&1);
		if ( ($readSelection == 0 && $this->writeHandler == null) || $readSelection != 0 ) {
			$slaveDelay = ( file_exists( Config::LOGS."/slaveStatus_".BaseConfig::$slavesPool[$readSelection].".log" ) ? file_get_contents( Config::LOGS."/slaveStatus_".BaseConfig::$slavesPool[$readSelection].".log" ) : 0 );
			if ( $readSelection > 0 && ( $slaveDelay == "NULL" || trim($slaveDelay) == "" || $slaveDelay == null || (int) $slaveDelay > 0) ) {
				$readSelection = 0;
			}
			$this->readHandler = new mysqli( BaseConfig::$slavesPool[$readSelection], BaseConfig::DB_USER, BaseConfig::DB_PASSWORD, BaseConfig::DB_DATABASE );
		} elseif ( $readSelection == 0 && $this->writeHandler != null ) {
			$this->readHandler = $this->writeHandler;
		}
	}
	
	public function read( $query, $forceMaster = false ) {
		if ( $forceMaster ) {
			return $this->write( $query );
		} else {
			# if not connected
			if ( $this->readHandler == null ) {
				$this->connectRead();
			}
			
			return $this->readHandler->query($query);
		}
	}
	
	public function write( $query ) {
		return $this->writeHandler->query( $query );
	}

	public function result( $query, $field = false, $forceMaster = true ){
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