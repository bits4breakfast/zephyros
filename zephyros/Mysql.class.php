<?php
define('first', 0);
define('all', 1);
define('last', 2);
define('notnull', 'ActiveRecord_notnull');
define('isnull', 'ActiveRecord_isnull');
define('now','ActiveRecord_now');
define('current_date','ActiveRecord_current_date');

class Mysql {

	private static $instances = array();
	private static $now = null;

	private $writeHandler = null;
	private $readHandler = null;
	
	private $cache = array();
	private $hits = 0;

	public function __construct() {
		$this->connectWrite();
	}

	public static function init() {
		$hash = md5( getmypid() . BaseConfig::DB_USER . BaseConfig::DB_PASSWORD . BaseConfig::DB_DATABASE );

		if ( !isset(self::$instances[$hash]) )
			self::$instances[$hash] = new Mysql();

		return self::$instances[$hash];
	}

	public static function release() {
		self::$instances = array();
	}

	private function connectWrite() {
		$this->writeHandler = new mysqli( BaseConfig::DB_MASTER_HOST, BaseConfig::DB_USER, BaseConfig::DB_PASSWORD, BaseConfig::DB_DATABASE );
		$this->writeHandler->set_charset("utf8");
	}

	private function connectRead() {
		if ( isset(BaseConfig::$slavesPool) ) {
			$readSelection = ( rand() & (count(BaseConfig::$slavesPool)-1) );
			if ( ($readSelection == 0 && $this->writeHandler == null) || $readSelection != 0 ) {
				$slaveDelay = ( file_exists( Config::LOGS_PATH.'/'."slaveStatus_".BaseConfig::$slavesPool[$readSelection].".log" ) ? file_get_contents( Config::LOGS_PATH.'/'."slaveStatus_".BaseConfig::$slavesPool[$readSelection].".log" ) : 0 );
				if ( $readSelection > 0 && ( $slaveDelay == "NULL" || trim($slaveDelay) == "" || $slaveDelay == null || (int) $slaveDelay > 0) ) {
					$readSelection = 0;
				}
				$this->readHandler = new mysqli( BaseConfig::$slavesPool[$readSelection], BaseConfig::DB_USER, BaseConfig::DB_PASSWORD, BaseConfig::DB_DATABASE );
				$this->readHandler->set_charset("utf8");
			} elseif ( $readSelection == 0 && $this->writeHandler != null ) {
				$this->readHandler = $this->writeHandler;
			}
		} else {
			if ( $this->writeHandler != null ) {
				$this->readHandler = $this->writeHandler;
			} else {
				$this->connectWrite();
				$this->readHandler = $this->writeHandler;
			}
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

			$timeStart = microtime( true );
			$result = $this->readHandler->query($query);
			$timeEnd = microtime( true );

			if ( $result === false ) {
				Logger::logException(
					new Exception(
						sprintf( 'Database error n° %s: %s.',$this->writeHandler->errno, $this->writeHandler->error. " SQL=$query" )
					)
				);
			} else {
				Logger::logQuery( round(($timeEnd - $timeStart) * 1000, 0), $query );
			}

			return $result;
		}
	}

	public function write( $query ) {
		$timeStart = microtime( true );
		$result = $this->writeHandler->query( $query );
		$timeEnd = microtime( true );

		if ( $result === false ) {
			Logger::logException(
				new Exception(
					sprintf( 'Database error n° %s: %s.',$this->writeHandler->errno, $this->writeHandler->error. " SQL=$query" )
				)
			);
		} else {
			Logger::logQuery( round(($timeEnd - $timeStart) * 1000, 0), $query );
		}

		return $result;
	}

	public function upsert( $table, $data, $incrementColumns = null ) {
		$fields = '';
		$values = '';
		$update = '';
		foreach ( $data as $key => $value ) {
			$fields .= '`'.$key.'`,';
			if ( $value === now || $value === current_date ) {
				$values .= "'" . self::nowAsUTC() . "',";
			} else if ( $value === null || $value == isnull ) {
				$values .= 'NULL,';
			} else {
				$values .= '"'.$this->escape($value).'",';
			}
			
			if ( isset($incrementColumns[$key]) ) {
				$update .= '`'.$key.'` = `'.$key.'` + '.((float)$value).',';
			} else if ( $value === null || $value == isnull ) {
				$update .= '`'.$key.'` = NULL,';
			} else if ( $value === now|| $value === current_date ) {
				$update .= '`'.$key."` = '" . self::nowAsUTC() . "',";
			} else {
				$update .= '`'.$key.'` = "'.$this->escape($value).'",';
			}
		}
		$fields = substr($fields, 0, -1);
		$values = substr($values, 0, -1);
		$update = substr($update, 0, -1);

		return $this->write( 'INSERT INTO '.$table.' ('.$fields.') VALUES ('.$values.') ON DUPLICATE KEY UPDATE '.$update );
	}

	public function insert( $table, $data ) {
		$fields = '';
		$values = '';
		foreach ( $data as $key => $value ) {
			if ( !is_numeric($key) && $fields !== false ) {
				$fields .= '`'.$key.'`,';
			} else {
				$fields = false;
			}
			if ( $value === now || $value === current_date ) {
				$values .= '"'.self::nowAsUTC().'",';
			} else {
				$values .= '"'.$this->escape($value).'",';
			}
		}
		if ( $fields != '' && $fields !== false ) {
			$fields = substr($fields, 0, -1);
		}
		$values = substr($values, 0, -1);

		return $this->write( 'INSERT INTO '.$table.( $fields != '' ? ' ('.$fields.')' : '' ).' VALUES ('.$values.')' );
	}

	public function update( $table, $data, $fields, $limit = 1 ) {
		$query = 'UPDATE '.$table.' SET';

		foreach ( $data as $key => $value ) {
			if ( $value === now || $value === current_date ) {
				$update .= '`'.$this->escape($key).'` = "'.self::nowAsUTC().'",';
			} else {
				$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'",';
			}
		}
		$query = substr($query, 0, -1);

		$query .= ' WHERE';
		if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::nowAsUTC().'" AND';	
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::nowAsUTC().'") AND';	
				} else {
					$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'" AND';
				}
			}
			$query = substr($query, 0, -4);
		} else {
			$query .= ' '.$fields;
		}
		
		if ( $limit > 0 ) {
			$query .= ' LIMIT '.$limit;
		}
		
		return $this->write($query);
	}

	public function delete( $table, $fields ) {
		$query = 'DELETE FROM '.$table.' WHERE';
		if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::nowAsUTC().'" AND';	
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::nowAsUTC().'") AND';	
				}  else {
					$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'" AND';
				}
			}
			$query = substr($query, 0, -4);
		} else {
			$query .= ' '.$fields;
		}
		return $this->write($query);
	}
	
	public function select( $table, $restrictions, $limit = 1 ) {
		$query = 'SELECT * FROM '.$table.' WHERE';
		if ( is_array($restrictions) ) {
			foreach ( $restrictions as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::nowAsUTC().'" AND';	
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::nowAsUTC().'") AND';	
				} else {
					$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'" AND';
				}
			}
			$query = substr($query, 0, -4);
		} else {
			$query .= ' '.$restrictions;
		}
		
		if ( $limit > 0 ) {
			$query .= ' LIMIT '.$limit;
		}
		
		if ( $limit == 1 ) {
			return $this->read( $query )->fetch_object();
		} else {
			return $this->read( $query );
		}
	}
	
	public static function fetch_all( $query, $result_type = 'ASSOC' ) {
		$rows = array();
		if ( $result_type == 'ASSOC' ) {
			while ( $row = $query->fetch_assoc()) {
				$rows[] = $row;
			}
		} else if ( $result_type == 'OBJ' ) {
			while ( $row = $query->fetch_object()) {
				$rows[] = $row;
			}
		} else {
			while ( list($row) = $query->fetch_row() ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}
	
	public function count( $table, $fields ) {
		$query = 'SELECT COUNT(*) FROM '.$table.' WHERE';
		if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::nowAsUTC().'" AND';	
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::nowAsUTC().'") AND';	
				} else {
					$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'" AND';
				}
			}
			$query = substr($query, 0, -4);
		} else {
			$query .= ' '.$fields;
		}
		
		return (int) $this->result( $query );
	}

	public function result( $query, $field = false, $forceMaster = true ) {
		$fingerPrint = md5($query);
		if ( !isset($this->cache[$fingerPrint]) ) {
			$query = $this->read($query, $forceMaster);

			if ( $query and $query->num_rows ) {
				if ( $field ) {
					$temp = $query->fetch_assoc();
					$this->cache[$fingerPrint] = $temp[$field];
				} else {
					$temp = $query->fetch_row();
					$this->cache[$fingerPrint] = $temp[0];
				}
			} else {
				$this->cache[$fingerPrint] = false;
			}	
		} else {
			++$this->hits;
		}
		
		return $this->cache[$fingerPrint];
	}

	public function affected_rows() {
		return $this->writeHandler->affected_rows;
	}

	public function escape( $string ) {
        if ($string == null || is_scalar($string))
		    return $this->writeHandler->real_escape_string( $string );
        else
            throw new InvalidArgumentException('escape function only accepts scalar values. Passed value was: ' . var_export($string, true));
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
	
	public function read_errno() {
		return $this->readHandler->errno;
	}

	public function write_error() {
		return $this->writeHandler->error;
	}
	
	public function write_errno() {
		return $this->writeHandler->errno;
	}

	public function __destruct() {
		if ( $this->writeHandler != null ) {
			@$this->writeHandler->close();
		}
		if ( $this->readHandler != null ) {
			@$this->readHandler->close();
		}
	}
    
    
    public static function nowAsUTC() {
    	if ( self::$now == null ) {
			self::$now = new DateTime(null, new DateTimeZone('UTC'));
		}
		
		return self::$now->format('Y-m-d H:i:s');
	}
}

?>