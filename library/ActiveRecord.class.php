<?php
include_once(Config::CORE.'/Mysql.class.php');
include_once(Config::CORE.'/Cachable.class.php');
include_once(Config::CORE.'/Inflector.class.php');

abstract class ActiveRecord extends Cachable {
	
	// Object setup
	protected $_class = '';
	protected $_name = '';
	protected $_plural = '';
	protected $_fkName = '';
	protected $_columns = array();
	
	// Data storage
	protected $_data = array();
	protected $_related = array();
	
	// Relations
	protected $isLocalized = false;
	protected $isCompletedBy = null;
	protected $belongsTo = array();
	protected $hasOne = array();
	protected $hasMany = array();
	protected $hasManyAndBelongsToMany = array();
	
	public function __construct( $id = NULL ) {
		if ( $id !== NULL ) {
			$this->_class = get_class( $this ) ;
			$this->_name = strtolower( $this->_class );
			$this->_plurarl = Inflector::plural( $this->_name );
			$this->_fkName = $this->_name.'_id';
			
			if ( is_object($id) ) {
				foreach ( $id as $key => $value ) {
					$this->_data[$key] = $value;
				}
			} else {
				if ( is_int($id) ) {
					$this->_data['id'] = (int) $id;
				} else {
					$this->_data['id'] = trim($id);
				}
				
				$this->_load();
			}
		}
	}
	
	public function __set( $key, $value ) {
		if ( isset($this->hasOne[$key]) || isset($this->hasMany[$key]) || isset($this->hasManyAndBelongsToMany[$key]) ) {
			$this->_related[$key] = $value;
		} else {
			$this->_data[$key] = $value;
		}
	}
	
	public function __get( $key ) {
		if ( isset($this->_data[$key]) ) {
			return $this->_data[$key];
		} else if ( isset($this->_related[$key]) ) {
			return $this->_related[$key];
		}
		return null;
	}
	
	public function __toString(){
        return $this->id;
    }
	
	public function __call( $name, $arguments ) {
		if ( strpos( 'add_', $name ) ) {
			$this->add( $name, $arguments );
		} else if ( strpos( 'remove_', $name ) ) {
			$this->remove( $name, $arguments );
		} else if ( strpos( 'reset_', $name ) ) {
			$this->reset( $name );
		} else if ( strpos( 'replace_', $name ) ) {
			$this->replace( $name, $arguments );
		}
	}
	
	public function add( $property) {
	
	}
	
	public function remove( $property, $arguments ) {
		if ( isset($this->_related[$key]) ) {
			 $this->_related[$key] = array();
		} else if ( isset($this->_data[$key]) ) {
			$this->_data[$key] = array();
		}
	}
	
	public function reset( $property ) {
		if ( isset($this->_related[$property]) ) {
			 $this->_related[$property] = array();
		} else if ( isset($this->_data[$property]) ) {
			$this->_data[$property] = array();
		}
	}
	
	public function replace( $property, $arguments ) {
		list( $key, $value ) = $arguments;
		if ( isset($this->_related[$property]) ) {
			 $this->_related[$property][$key] = $value;
		} else if ( isset($this->_data[$property]) ) {
			$this->_data[$property][$key] = $value;
		}
	}
	
	public function __callStatic( $name, $arguments ) {
		if ( strpos('findBy') !== false  ) {
				
		}
	}
	
	public static function find( $parameters = null ) {
		$db = Mysql::init();
	}
	
	public function _load() {
		if ( method_exists( $this, 'beforeLoading' ) ) {
			$this->beforeLoading();
		}
		
		$query = $this->db->read('SELECT * FROM '.$this->_plural.' WHERE id = "'.$this->db->escape($this->id).'" LIMIT 1');
		$record = mysql_fetch_object($query);
		foreach ( $record as $key => $value ) {
			$this->_data[$key] = $value;
		}
		
		if ( $this->isCompletedBy != '' ) {
			$this->_columns = array_keys($this->_data);
			$query = $this->db->read('SELECT * FROM '.$this->isCompletedBy.' WHERE '.$this->_fkName.' = "'.$this->db->escape($this->id).'" LIMIT 1');
			$record = mysql_fetch_object($query);
			foreach ( $record as $key => $value ) {
				$this->_data[$key] = $value;
			}
		}
		
		if ( method_exists( $this, 'afterLoading' ) ) {
			$this->afterLoading();
		}
	}
	
	private function _save() {
		if ( method_exists( $this, 'beforeSaving' ) ) {
			$this->beforeSaving();
		}
		
		
		
		if ( method_exists( $this, 'afterSaving' ) ) {
			$this->afterSaving();
		}
	}
	
	private function _delete() {
		if ( method_exists( $this, 'beforeDeleting' ) ) {
			$this->beforeDeleting();
		}
		
		
		
		if ( method_exists( $this, 'afterDeleting' ) ) {
			$this->afterDeleting();
		}
	}
	
	private function isCached($className, $fileName) {
		return file_exists($this->_cacheDir.$className.'/'.$fileName);
	}
	
	private function readCache($className, $fileName) {
		$cacheFile = $this->_cacheDir.$className.'/'.$fileName;
		
		if( !is_file($cacheFile) ) {
			return;
		}
		
		$obj = unserialize(file_get_contents($this->_cacheDir.$this->_class.'/'.$this->id));
		
		if( !is_object($obj) ) {
			return;
		}
		
		$vars = $obj->__getDump();
		
		foreach ( $vars as $key => $val ) {
			if ( !isset($this->$key) or (!is_object($this->$key)) or (is_object($this->$key) && get_class($this->$key)!='Mysql') ) {
				$this->$key = $val;
			}
		}
	}
	
	private function writeCache() {
		$content = serialize($this);
		if ( trim($content) != "" ) {
			@file_put_contents($this->_cacheDir.$this->_class.'/'.$this->id, $content);
			@chmod($this->_cacheDir.'/'.$this->_class.'/'.$this->id,0766);
			HttpReplicationClient::send($this->_cacheDir.'/'.$this->_class.'/'.$this->id);
		}
	}
	
	protected function clearCache() {
		HttpReplicationClient::remove($this->_cacheDir.'/'.$this->_class.'/'.$this->id);

		if( is_file($this->_cacheDir.'/'.$this->_class.'/'.$this->id) && file_exists($this->_cacheDir.'/'.$this->_class.'/'.$this->id) )
			@unlink($this->_cacheDir.'/'.$this->_class.'/'.$this->id);
	}
	
	protected function __sleep() {
		return array();
	}
}
?>