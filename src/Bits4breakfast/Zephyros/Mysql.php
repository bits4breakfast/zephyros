<?php
namespace Bits4breakfast\Zephyros;

class Mysql {

	const first = 0;
	const all = 1;
	const last = 2;
	const random = 3;
	const notnull = 'zephyros_ActiveRecord_notnull';
	const isnull = 'zephyros_ActiveRecord_isnull';
	const now = 'zephyros_ActiveRecord_now';
	const current_date = 'zephyros_ActiveRecord_current_date';

	private static $instances = array();
	private static $now = null;

	private $connections = array();
	private $container = null;

	private $use_shard = '';

	private $cache = array();

	public function __construct( ServiceContainer $container ) {
		$shards = array_keys((array)$container->config()->get('database.shards'));
		$this->use_shard = $shards[0];
		$this->container = $container;

		$this->connect('read');
	}

	public static function init( ServiceContainer $container ) {
		$hash = md5( getmypid() . $container->config()->get('database.username') . $container->config()->get('database.password') . $container->config()->get('database.database') );

		if ( !isset(self::$instances[$hash]) )
			self::$instances[$hash] = new Mysql($container);

		return self::$instances[$hash];
	}

	public static function release() {
		self::$instances = array();
	}

	public function pick( $shard ) {
		$this->use_shard = $shard;
		return $this;
	}

	public function ensure_connection( $read_or_write = 'write' ) {
		if ( !isset($this->connections[$this->use_shard][$read_or_write]) ) {
			$this->connect( $read_or_write );
		}
		return $this;
	}

	public function connect( $read_or_write ) {
		$available_shards = $this->container->config()->get('database.shards');
		$host = $available_shards[$this->use_shard]['master'];
		
		if ( $read_or_write == 'read' && isset($available_shards[$this->use_shard]['replicas']) && !empty($available_shards[$this->use_shard]['replicas']) ) {
			shuffle($available_shards[$this->use_shard]['replicas'] );
			$replicas = array_merge( array( $host ), $available_shards[$this->use_shard]['replicas'] );
			$host = $replicas[rand(0,count($replicas)-1)];
		}

		$database = ( isset($available_shards[$this->use_shard]['database']) ? $available_shards[$this->use_shard]['database'] : $this->container->config()->get('database.database') );
		
		$this->connections[$this->use_shard][$read_or_write] = new \mysqli( $host, $this->container->config()->get('database.username'), $this->container->config()->get('database.password'), $database );
		$this->connections[$this->use_shard][$read_or_write]->set_charset( 'utf8' );
	}

	public function read( $query, $forceMaster = false ) {
		$timeStart = microtime();
		if ( $forceMaster ) {
			if ( !isset($this->connections[$this->use_shard]['write']) ) {
				$this->connect( 'write' );
			}

			$handler = $this->connections[$this->use_shard]['write'];
		} else {
			if ( !isset($this->connections[$this->use_shard]['read']) ) {
				$this->connect( 'read' );
			}

			$handler = $this->connections[$this->use_shard]['read'];
		}

		$result = $handler->query($query);
		$timeEnd = microtime();

		if ( $result === false ) {
			\zephyros\Logger::logException(
				new \Exception(
					sprintf( 'Database error n° %s: %s.', $handler->errno, $handler->error. " SQL=$query" )
				)
			);
		} else {
			\zephyros\Logger::logQuery( round(($timeEnd - $timeStart) * 1000, 0), $query );
		}

		return $result;
	}

	public function write( $query ) {
		if ( !isset($this->connections[$this->use_shard]['write']) ) {
			$this->connect( 'write' );
		}

		$timeStart = microtime( true );
		$result = $this->connections[$this->use_shard]['write']->query( $query );
		$timeEnd = microtime( true );

		if ( $result === false ) {
			\zephyros\Logger::logException(
				new \Exception(
					sprintf( 'Database error n° %s: %s.', $this->connections[$this->use_shard]['write']->errno, $this->connections[$this->use_shard]['write']->error. " SQL=$query" )
				)
			);
		} else {
			\zephyros\Logger::logQuery( round(($timeEnd - $timeStart) * 1000, 0), $query );
		}

		return $result;
	}

	public function upsert( $table, $data, $incrementColumns = null ) {
		if ( !isset($this->connections[$this->use_shard]['write']) ) {
			$this->connect( 'write' );
		}
		
		$fields = '';
		$values = '';
		$update = '';
		foreach ( (array)$data as $key => $value ) {
			$fields .= '`'.$key.'`,';
			if ( $value === now || $value === current_date ) {
				$values .= "'" . self::utc_timestamp() . "',";
			} else if ( $value === null ) {
				$values .= 'NULL,';
			} else {
				$values .= '"'.$this->escape($value).'",';
			}

			if ( isset($incrementColumns[$key]) ) {
				$update .= '`'.$key.'` = `'.$key.'` + '.((float)$value).',';
			} else if ( $value === null ) {
				$update .= '`'.$key.'` = NULL,';
			} else if ( $value === now|| $value === current_date ) {
				$update .= '`'.$key."` = '" . self::utc_timestamp() . "',";
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
		if ( !isset($this->connections[$this->use_shard]['write']) ) {
			$this->connect( 'write' );
		}

		$fields = '';
		$values = '';
		foreach ( $data as $key => $value ) {
			if ( !is_numeric($key) && $fields !== false ) {
				$fields .= '`'.$key.'`,';
			} else {
				$fields = false;
			}
			if ( $value === now || $value === current_date ) {
				$values .= '"'.self::utc_timestamp().'",';
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
		if ( !isset($this->connections[$this->use_shard]['write']) ) {
			$this->connect( 'write' );
		}

		$query = 'UPDATE '.$table.' SET';

		foreach ( $data as $key => $value ) {
			if ( $value === now || $value === current_date ) {
				$update .= '`'.$this->escape($key).'` = "'.self::utc_timestamp().'",';
			} else {
				$query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'",';
			}
		}
		$query = substr($query, 0, -1);

		$query .= ' WHERE';
		if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::utc_timestamp().'" AND';
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::utc_timestamp().'") AND';
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
		if ( !isset($this->connections[$this->use_shard]['write']) ) {
			$this->connect( 'write' );
		}

		$query = 'DELETE FROM '.$table.' WHERE';
		if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::utc_timestamp().'" AND';
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::utc_timestamp().'") AND';
				} else {
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
		if ( !isset($this->connections[$this->use_shard]['read']) ) {
			$this->connect( 'read' );
		}

		$query = 'SELECT * FROM '.$table.' WHERE';
		if ( is_array($restrictions) ) {
			foreach ( $restrictions as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::utc_timestamp().'" AND';
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::utc_timestamp().'") AND';
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

	public function count( $table, $fields = null ) {
		if ( !isset($this->connections[$this->use_shard]['read']) ) {
			$this->connect( 'read' );
		}

		$query = 'SELECT COUNT(*) FROM '.$table.' WHERE';
		if ( $fields === null ) {
			$query .= ' 1';
		} else if ( is_array($fields) ) {
			foreach ( $fields as $key => $value ) {
				if ( $value === now ) {
					$query .= ' `'.$this->escape($key).'` = "'.self::utc_timestamp().'" AND';	
				} else if ( $value === current_date ) {
					$query .= ' `'.$this->escape($key).'` = DATE("'.self::utc_timestamp().'") AND';	
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
		return $this->connections[$this->use_shard]['write']->affected_rows;
	}

	public function escape( $string ) {
		if ( $string == null || is_scalar($string) )
			return $this->connections[$this->use_shard]['read']->real_escape_string( $string );
		else
			throw new \InvalidArgumentException('escape function only accepts scalar values. Passed value was: ' . var_export($string, true));
	}

	public function last_id() {
		return (int) $this->connections[$this->use_shard]['write']->insert_id;
	}

	public function start_transaction() {
		$this->connections[$this->use_shard]['write']->autocommit( FALSE );
	}

	public function autocommit( $mode ) { // FALSE => START TRANSACTION
		$this->connections[$this->use_shard]['write']->autocommit( $mode );
	}

	public function commit() {
		$this->connections[$this->use_shard]['write']->commit();
	}

	public function rollback() {
		$this->connections[$this->use_shard]['write']->rollback();
	}

	public function general_rollback() {
		foreach ( $this->connections as $shard => $connection ) {
			if ( isset($connection['write']) ) {
				$connection['write']->rollback();
			}
		}
	}

	public function read_error() {
		return $this->connections[$this->use_shard]['read']->error;
	}

	public function read_errno() {
		return $this->connections[$this->use_shard]['read']->errno;
	}

	public function write_error() {
		return $this->connections[$this->use_shard]['write']->error;
	}

	public function write_errno() {
		return $this->connections[$this->use_shard]['write']->errno;
	}

	public function __destruct() {
		$this->connections = array();
	}

	public static function utc_timestamp() {
		if ( self::$now == null ) {
			self::$now = new \DateTime(null, new \DateTimeZone('UTC'));
		}

		return self::$now->format('Y-m-d H:i:s');
	}
}