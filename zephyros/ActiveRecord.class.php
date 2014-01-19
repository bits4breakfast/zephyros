<?php
include_once BaseConfig::BASE_PATH.'/zephyros/Inflector.class.php';

abstract class ActiveRecord {
	
	// Object setup
	protected static $instances = array();
	
	protected $_db = null;
	private $_database = '';
	private $_class = '';
	private $_name = '';
	private $_table = '';
	private $_fkName = '';
	private $_columns = array();
	private $_didLoad = true;
	
	// Data storage
	protected $_data = array();
	protected $_related = array();
	protected $_localized = array();
	
	public function __construct( $id = NULL, $load = true, $strict = false ) {
		$this->_class = get_class( $this );
		$this->_name = strtolower( Inflector::decamelize( $this->_class ) );
		$this->_fkName = $this->_name.'_id';
		
		if ( isset($this->table_name) && trim($this->table_name) != '' ) {
			$this->_table = $this->table_name;
		} else {
			$this->_table = Inflector::plural( $this->_name );
		}

		$this->_db = Mysql::init();
		$this->_table = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$this->_table;
		
		if ( $id !== NULL ) {
			if ( is_scalar($id) ) {
				if ( is_numeric($id) ) {
					$this->_data['id'] = (int) $id;
				} else {
					$this->_data['id'] = trim($id);
				}
				
				if ( $load ) {
					$this->_load( $strict );
				}
			} else {
				foreach ( $id as $key => $value ) {
					if ( $value === now ) {
						$value = Mysql::nowAsUTC();
					}
					
					$this->_data[$key] = $value;
				}
			}
		}
	}
	
	public static function init( $id = NULL ) {
		$calledClass = get_called_class();
		if ( !isset(self::$instances[$calledClass][$id]) ) {
			self::$instances[$calledClass][$id] = new $calledClass( $id );
		}
		
		return self::$instances[$calledClass][$id];
	}
	
	public function __set( $key, $value ) {
		if ( $value === now ) {
			$value = Mysql::nowAsUTC();
		}
		
		if ( is_array($value) ) {
			$this->_related[$key] = $value;
			$this->_related['_changed'][$key] = true;
		} else {
			$this->_data[$key] = $value;
		}
	}
	
	public function __get( $key ) {
		if ( isset($this->_data[$key]) ) {
			return $this->_data[$key];
		} else if ( isset($this->_related[$key]) ) {
			return $this->_related[$key];
		} else if ( $key == '_didLoad' ) {
			return $this->_didLoad;
		}
		return null;
	}
	
	public function __call( $name, $arguments ) {
		if ( strpos( $name, 'add_' ) !== false ) {
			if ( empty($arguments) ) {
				throw new Exception( 'Missing value to add to '.Inflector::plural($name) );
			}
			if ( !isset($arguments[1]) ) {
				$this->add( Inflector::plural( str_replace('add_','',$name) ), $arguments[0] );
			} else {
				$this->add( Inflector::plural( str_replace('add_','',$name) ), $arguments[0], $arguments[1] );
			}
		} else if ( strpos( $name, 'remove_' ) !== false ) {
			$this->remove( Inflector::plural( str_replace('remove_','',$name) ), $arguments );
		} else if ( strpos( $name, 'reset_' ) !== false ) {
			$this->reset( str_replace('reset_','',$name) );
		} else if ( strpos( $name, 'has_' ) !== false ) {
			return $this->has( str_replace('has_','',$name) );
		} else if ( strpos( $name, 'save_' ) !== false ) {
			$relation = Inflector::singular( str_replace('save_','',$name) );
			$this->save_dependent( $relation, $this->has_many[$relation] );
		} else if ( strpos( $name, 'replace_in_' ) !== false ) {
			$this->replace( str_replace('replace_in_','',$name), $arguments );
		} else if ( strpos( $name, 'localize_in_' ) !== false ) {
			$this->localize( $arguments, str_replace( 'localize_in_', '', $name ) );
		}
	}
	
	public function add( $toProperty, $value, $key = null ) {
		if ( $key === null ) {
			$this->_related[$toProperty][] = $value;
		} else {
			$this->_related[$toProperty][$key] = $value;
		}
		
		$this->_related['_changed'][$toProperty] = true;
	}
	
	public function remove( $fromProperty, $arguments ) {
		if ( isset($this->_related[$fromProperty]) ) {
			if ( isset($this->_related[$fromProperty][$arguments[0]]) ) {
				unset($this->_related[$fromProperty][$arguments[0]]);
			} else {
				$keys = array_keys( $this->_related[$fromProperty], $arguments[0] );
				foreach ( $keys as $key ) {
					unset( $this->_related[$fromProperty][$key] );
				}
			}
			$this->_related['_changed'][$fromProperty] = true;
		}
	}
	
	final public function reset( $property ) {
		$this->_related[$property] = array();
		$this->_related['_changed'][$property] = true;
	}
	
	final public function has( $property ) {
		return !empty($this->_related[$property]);
	}
	
	final public function replace( $property, $arguments ) {
		list( $key, $value ) = $arguments;
		if ( is_array($this->_related[$property]) ) {
			$this->_related[$property][$key] = $value;
		} else {
			$this->_related[$property]->$key = $value;
		}
		$this->_related['_changed'][$property] = true;
	}
	
	final public function localize( $arguments, $lang = 'en' ) {
		$lang = strtolower($lang);
		if ( is_string($arguments) ) {
			if ( isset($this->_localized[$lang][$arguments]) ) {
				return $this->_localized[$lang][$arguments];
			}
		} else if ( count($arguments) == 1 ) {
			if ( isset($this->_localized[$lang][$arguments[0]]) ) {
				return $this->_localized[$lang][$arguments[0]];
			}
		} else {
			$this->_localized[$lang][$arguments[0]] = $arguments[1];
		}
	}
	
	final public static function find( $what = first, $conditions = null, $options = null ) {
		$calledClass = get_called_class();
		$temp = new $calledClass();
		$temp = $temp->_reflection();
		
		$db = Mysql::init();

		if ( is_string($conditions) ) {
			$query = $conditions;	
		} else {
			$query = '';		
			foreach ( (array)$conditions as $field => $value ) {
				if ( is_numeric($field) && is_array($value) ) {
					$query .= '(';
					foreach ( $value as $field => $value ) {
						if ( $value == notnull ) {
							$query .= '`'.$field.'` IS NOT NULL OR ';
						} else if ( $value == isnull ) {
							$query .= '`'.$field.'` IS NULL OR ';
						} else if ( $value != null ) {
							if ( is_array($value) ) {
								$query .= '`'.$field.'` IN ("'.implode('","',$value).'") OR ';				
							} else {
								$query .= '`'.$field.'` = "'.$db->escape($value).'" OR ';
							}
						}	
					}
					$query = substr($query,0,-4). ') AND ';
				} else {
					if ( $value == notnull ) {
						$query .= '`'.$field.'` IS NOT NULL AND ';
					} else if ( $value == isnull ) {
						$query .= '`'.$field.'` IS NULL AND ';
					} else if ( $value != null ) {
						if ( is_array($value) ) {
							$query .= '`'.$field.'` IN ("'.implode('","',$value).'") AND ';				
						} else {
							$query .= '`'.$field.'` = "'.$db->escape($value).'" AND ';
						}
					}
				}
			}
			$query = substr($query,0,-5);
		}
		
		$query = 'SELECT id FROM '.( trim($temp->_database) != '' ? '`'.$temp->_database.'`.' : '' ).'`'.$temp->_table.'`'.( empty($conditions) ? '' : ' WHERE '.$query );
		if ( $what == first ) {
			$query .= ' LIMIT 1';
		} else if ( $what == last ) {
			$query .= ' ORDER BY `id` DESC LIMIT 1';
		} else {
			if ( isset($options['orderby']) ) {
				$query .= ' ORDER BY '.$options['orderby'];
			}
			
			if ( isset($options['limit']) ) {
				$start = isset($options['start']) ? (int) $options['start'] : 0;
				$query .= ' LIMIT '.$start.','.$options['limit'];
			}
		}
		
		unset( $temp );
		
		$result = $db->read( $query );
		if ( $result === false ) {
			throw new FindException( $db->read_error(), $db->read_errno() );
		} else {
			if ( $what == first || $what == last ) {
				if ( $result->num_rows == 0 ) {
					return null;
				}
			
				$temp = new $calledClass( $result->fetch_object()->id );
				$result->free();
				return $temp;
			} else {
				return $result;
			}
		}
	}
	
	final public static function exists( $conditions = null) {
		if ( empty($conditions) ) {
			return null;
		}
	
		$calledClass = get_called_class();
		$temp = new $calledClass();
		$temp = $temp->_reflection();
		
		$db = Mysql::init();

		$query = '';
		foreach ( $conditions as $field => $value ) {
			if ( $value == notnull ) {
				$query .= '`'.$field.'` IS NOT NULL AND ';
			} else if ( $value == isnull ) {
				$query .= '`'.$field.'` IS NULL AND ';
			} else {
				$query .= '`'.$field.'` = "'.$db->escape($value).'" AND ';
			}
		}
		
		$query = 'SELECT COUNT(*) FROM '.( trim($temp->_database) != '' ? '`'.$temp->_database.'`.' : '' ).'`'.$temp->_table.'` WHERE '.substr($query,0,-5);
		
		unset( $temp );
		
		$result = $db->read( $query );
		if ( $result === false ) {
			return false;
		} else {
			return ( $result->num_rows > 0 );
		}
	}
	
	private function _load( $strict = false ) {
		if ( ( !isset($this->do_not_cache) || (isset($this->do_not_cache) && !$this->do_not_cache ) ) && $this->_isCached() ) {
			if ( method_exists( $this, 'before_restoring' ) ) {
				$this->before_restoring();
			}
			
			$this->_readCache();
			
			if ( method_exists( $this, 'after_restoring' ) ) {
				$this->after_restoring();
			}
		} else {
			if ( method_exists( $this, 'before_loading' ) ) {
				$this->before_loading();
			}
			$queryStr = 'SELECT * FROM '.$this->_table.' WHERE id = "'.$this->_db->escape($this->id).'" LIMIT 1';
			$query = $this->_db->read($queryStr);
			if ( $query != null ) {
				$record = $query->fetch_object();
				if ( $record != null ) {
					foreach ( $record as $key => $value ) {
						$this->_data[$key] = $value;
					}
				} else {
					if ( $strict ) {
						throw new NonExistingItemException(sprintf("Record with id %s not found in table '%s'.", $this->id, $this->_table));
					}
				}
			} else {
				$msg = $this->_db->read_error();
				throw new Exception( "Query execution failure, reason: $msg. Query: $queryStr" );
			}
			
			if ( isset($this->has_one) && !empty($this->has_one) ) {
				foreach ( $this->has_one as $relation => $details ) {
					if ( isset($details['do_not_load']) && $details['do_not_load'] ) {
						continue;
					}
					
					if ( isset($details['table_name']) && !empty($details['table_name']) ) {
						$tableName = $details['table_name'];
					} else {
						$tableName = strtolower($relation);
						$tableName = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$tableName;
						$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
					}
					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$record = $this->_db->read('SELECT * FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'" LIMIT 1');
						if ( $record->num_rows == 1 ) {
							$this->_related[$relation] = (object) $record->fetch_object();
						} else {
							$this->_related[$relation] = null;
						}
					} else {
						$this->_related[$relation] = $this->_db->result('SELECT id FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'" LIMIT 1');
					}
				}
			}
			
			if ( isset($this->has_many) && !empty($this->has_many) ) {
				foreach ( $this->has_many as $relation => $details ) {
					if ( isset($details['do_not_load']) && $details['do_not_load'] ) {
						continue;
					}
					
					$key = Inflector::plural( strtolower($relation) );
					$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : $key );
					$tableName = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$tableName;
					$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$query = $this->_db->read('SELECT * FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
						if ( $query != null ) {
							while ( $record = $query->fetch_object() ) {
								$this->_related[$key][] = $record;
							}
						} else {
							throw new Exception( 'Table '.$tableName.' does not exist' );
						}
					} else {
						$fieldName = ( isset($details['field_name']) ? $details['field_name'] : 'id' );
						$query = $this->_db->read('SELECT '.$fieldName.' FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
						if ( $query != null ) {
							while ( list($record) = $query->fetch_row() ) {
								$this->_related[$key][] = $record;
							}
						} else {
							throw new Exception( 'Table '.$tableName.' does not exist' );
						}
					}
				}
			}
			
			if ( isset($this->has_many_and_belongs_to_many) && !empty($this->has_many_and_belongs_to_many) ) {
				foreach ( $this->has_many_and_belongs_to_many as $relation => $details ) {
					if ( isset($details['do_not_load']) && $details['do_not_load'] ) {
						continue;
					}
					
					$key = Inflector::plural( strtolower($relation) );
					$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::habtmTableName( $this->_class, $relation ) );
					$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					$fieldName = ( isset($details['field_name']) ? $details['field_name'] : strtolower($relation).'_id' );
					
					$query = $this->_db->read('SELECT '.$fieldName.' FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
					if ( $query != null ) {
						while ( list($record) = $query->fetch_row() ) {
							$this->_related[$key][] = $record;
						}
					} else {
						throw new Exception( 'Table '.$tableName.' does not exist' );
					}
				}
			}
			
			if ( isset($this->is_localized) && $this->is_localized ) {
				$this->_localized = array();
				$query = $this->_db->read('SELECT * FROM '.$this->_table.'_localized WHERE parent_id = '.$this->id);
				if ( $query != null ) {
					while ( $record = $query->fetch_assoc() ) {
						$lang = $record['lang'];
						unset( $record['parent_id'], $record['lang'] );
						$this->_localized[strtolower($lang)] = $record;
					}
				} else {
					throw new Exception( 'Table '.$tableName.' does not exist' );
				}
			}
			
			if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
				$this->_writeCache();
			}
			
			if ( method_exists( $this, 'after_loading' ) ) {
				$this->after_loading();
			}
		}
	}
	
	final public function save() {
		if ( !empty($this->_data) ) {
			if ( method_exists( $this, 'before_saving' ) ) {
				$this->before_saving();
			}
			
			$this->_db->upsert( $this->_table, $this->_data, (isset($this->columns_to_increment)?$this->columns_to_increment:null) );
			
			if ( ( !isset($this->_data['id']) || ( isset($this->_data['id']) && $this->_data['id'] == 0 ) ) && ( !isset($this->has_composite_primary_key) || ( isset($this->has_composite_primary_key) && !$this->has_composite_primary_key ) ) ) {
				$this->_data['id'] = $this->_db->last_id();
			}
						
			if ( isset($this->has_one) && !empty($this->has_one) ) {
				foreach ( $this->has_one as $relation => $details ) {
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						if ( isset($this->_related['_changed'][$relation]) || isset($this->_related['_changed'][$relation]) ) {
							$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : $this->_table.'_'.$relation );
							$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
							$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
							
							if ( is_array($this->_related[strtolower($relation)]) ) {
								$record = array_merge( array( $fk => $this->id ), $this->_related[strtolower($relation)] );
							} else {
								$record = array_merge( array(  $fk => $this->id), (array)$this->_related[strtolower($relation)] );
							}
							
							$this->_db->upsert( $tableName, $record );
						}
					}
				}
			}
			
			if ( isset($this->has_many) && !empty($this->has_many) ) {
				foreach ( $this->has_many as $relation => $details ) {
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$this->save_dependent( $relation, $details );
					}
				}
			}
			
			if ( isset($this->has_many_and_belongs_to_many) && !empty($this->has_many_and_belongs_to_many) ) {
				foreach ( $this->has_many_and_belongs_to_many as $relation => $details ) {
					$key = Inflector::plural( strtolower($relation) );
					if ( isset($this->_related['_changed'][$relation]) || isset($this->_related['_changed'][$key]) ) {
						$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::habtmTableName( $this->_class, $relation ) );
						$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$fieldName = ( isset($details['field_name']) ? $details['field_name'] : strtolower($relation).'_id' );
						
						$this->_db->delete( $tableName, array( $fk => $this->id ) );
						foreach ( $this->_related[$key] as $recordId ) {
							$this->_db->insert( $tableName, array( $fk => $this->id, $fieldName => $recordId ) );
						}
					}
				}
			}
			
			if ( isset($this->is_localized) && $this->is_localized ) {
				$this->_db->delete( $this->_table.'_localized', array( 'parent_id' => $this->id ) );
				foreach ( $this->_localized as $lang => $record ) {
					$record = (array) $record;
					$record = array_merge( array( 'parent_id' => $this->id, 'lang' => $lang ), $record );
					$this->_db->insert( $this->_table.'_localized', $record );
				}
			}
			
			if ( method_exists( $this, 'after_saving' ) ) {
				$this->after_saving();
			}
			
			if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
				$this->_clearCache();
			}
			
			return $this->id;
		}
	}
	
	final public function save_dependent( $relation, $details = null ) {
		$key = Inflector::plural( strtolower($relation) );
		
		if ( isset($this->_related['_changed'][$key]) || isset($this->_related['_changed'][$relation]) ) {
			
			$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : $key );
			$tableName = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$tableName;
			$tableName = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$tableName;
			$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
			
			$result = $this->_db->delete( $tableName, array( $fk => $this->id ) );
			foreach ( $this->_related[$key] as $record ) {
				if ( is_array($record) ) {
					$record = array_merge( array( $fk => $this->id ), $record );
				} else {
					$record = array_merge( array(  $fk => $this->id), (array)$record );
				}
				
				$this->_db->insert( $tableName, $record );
			}
			
		}
	}
	
	final public function delete() {
		if ( method_exists( $this, 'before_deleting' ) ) {
			$this->before_deleting();
		}
		
		$this->_db->delete( $this->_table, array( 'id' => $this->id ) );
		
		if ( method_exists( $this, 'after_deleting' ) ) {
			$this->after_deleting();
		}
		
		if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
			$this->_clearCache();
		}
	}
	
	private function _isCached() {
		return file_exists( BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache' );
	}
	
	private function _readCache() {
		$obj = unserialize(file_get_contents(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache'));
		
		if( !is_object($obj) ) {
			return;
		}
		
		$vars = $obj->_snapshot();
		
		foreach ( $vars as $key => $val ) {
			$this->$key = $val;
		}
	}
	
	private function _writeCache() {
		if ( DEV_ENVIRONMENT ) {
			return;
		}
		$toSerialize = serialize($this);
		if ( trim($toSerialize) != "" ) {
			if ( !file_exists(BaseConfig::CACHE_PATH.'/'.$this->_class) ) {
				mkdir(BaseConfig::CACHE_PATH.'/'.$this->_class, 0777, true);
			}
			@file_put_contents(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache', $toSerialize);
			@chmod(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache',0766);
		}
	}
	
	private function _clearCache() {
		if( is_file(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache') && file_exists(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache') ) {
			@unlink(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache');
		}
	}
	
	final public function _snapshot() {
		return array(
			'_data' => $this->_data,
			'_related' => $this->_related,
			'_localized' => $this->_localized
		);
	}
	
	protected function __sleep() {
		return array( '_data', '_related', '_localized' );
	}
	
	public function __toString() {
		return $this->_class.' #'.$this->id;
	}
	
	final public function _reflection() {
		return (object) array(
			'_database' => $this->_database,
			'_class' => $this->_class,
			'_name' => $this->_name,
			'_table' => $this->_table,
			'_fkName' => $this->_fkName,
			'_columns' => $this->_columns
		);
	}
}

class NonExistingItemException extends Exception {}
class FindException extends Exception {}
?>