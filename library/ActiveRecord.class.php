<?php
include_once BaseConfig::BASE_PATH.'/library/Inflector.class.php';

abstract class ActiveRecord {
	
	// Object setup
	protected $_driver = 'mysql';
	protected $_db = null;
	protected $_database = '';
	protected $_class = '';
	protected $_name = '';
	protected $_plural = '';
	protected $_fkName = '';
	protected $_columns = array();
	
	// Data storage
	protected $_data = array();
	protected $_related = array();
	protected $_localized = array();
	
	public function __construct( $id = NULL, $load = true ) {
		$this->_class = get_class( $this );
		$this->_name = strtolower( $this->_class );
		$this->_plural = Inflector::plural( $this->_name );
		$this->_fkName = $this->_name.'_id';
		
		if ( $this->_driver == 'mysql' ) {
			$this->_db = Mysql::init();
		} else if ( $this->_driver == 'mongo' ) {
			$this->_db = new Mongo();
		}
		
		if ( $id !== NULL ) {
			if ( is_object($id) ) {
				foreach ( $id as $key => $value ) {
					$this->_data[$key] = $value;
				}
			} else {
				if ( is_numeric($id) ) {
					$this->_data['id'] = (int) $id;
				} else {
					$this->_data['id'] = trim($id);
				}
				
				if ( $load ) {
					$this->_load();
				}
			}
		}
	}
	
	public function __set( $key, $value ) {
		if ( isset($this->has_one[$key]) || isset($this->has_many[$key]) || isset($this->has_many_and_belongs_to_many[$key]) ) {
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
	}
	
	public function remove( $property, $arguments ) {
		if ( isset($this->_related[$property]) ) {
			if ( isset($this->_related[$property][$arguments[0]]) ) {
				unset($this->_related[$property][$arguments[0]]);
			} else {
				$keys = array_keys( $this->_related[$property], $arguments[0] );
				foreach ( $keys as $key ) {
					unset( $this->_related[$property][$key] );
				}
			}
		}
	}
	
	public function reset( $property ) {
		$this->_related[$property] = array();
	}
	
	public function replace( $property, $arguments ) {
		list( $key, $value ) = $arguments;
		$this->_related[$property][$key] = $value;
	}
	
	public function localize( $arguments, $lang = 'en' ) {
		$lang = strtolower($lang);
		if ( count($arguments) == 1 ) {
			if ( isset($this->_localized[$lang][$arguments[0]]) ) {
				return $this->_localized[$lang][$arguments[0]];
			}
		} else {
			$this->_localized[$lang][$arguments[0]] = $arguments[1];
		}
	}
	
	public static function __callStatic( $name, $arguments ) {
		if ( strpos('find_by') !== false  ) {
				
		}
	}
	
	public static function find( $parameters = null ) {
		$db = Mysql::init();
	}
	
	private function _load() {
		if ( $this->_isCached() ) {
			$this->_readCache();
		} else {
			if ( method_exists( $this, 'before_loading' ) ) {
				$this->before_loading();
			}
			
			if ( $this->_driver == 'mysql' ) {
				$query = $this->_db->read('SELECT * FROM '.$this->_plural.' WHERE id = "'.$this->_db->escape($this->id).'" LIMIT 1');
				if ( $query != null ) {
					$record = $query->fetch_object();
					if ( $record != null ) {
						foreach ( $record as $key => $value ) {
							$this->_data[$key] = $value;
						}
					}
				} else {
					throw new Exception( 'Table '.$this->_plural.' does not exist' );
				}
				
				if ( isset($this->has_one) && !empty($this->has_one) ) {
					foreach ( $this->has_one as $relation => $details ) {
						$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::plural( strtolower($relation) ) );
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$key = strtolower($relation);
						
						if ( isset($relation['is_dependent']) && $relation['is_dependent'] ) {
							$this->_related[$key] = $this->_db->read('SELECT * FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'" LIMIT 1')->fetch_object();
						} else {
							$this->_related[$key] = $this->_db->result('SELECT id FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'" LIMIT 1');
						}
					}
				}
				
				if ( isset($this->has_many) && !empty($this->has_may) ) {
					foreach ( $this->has_many as $relation => $details ) {
						$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::plural( strtolower($relation) ) );
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$key = Inflector::plural( strtolower($relation) );
						
						if ( isset($relation['is_dependent']) && $relation['is_dependent'] ) {
							$query = $this->_db->read('SELECT * FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
							if ( $query != null ) {
								while ( $record = $query->fetch_object() ) {
									$this->_related[$key][] = $record;
								}
							} else {
								throw new Exception( 'Table '.$tableName.' does not exist' );
							}
						} else {
							$fieldName = ( $details['field_name'] ? $details['field_name'] : strtolower($relation).'_id' );
							$query = $this->_db->read('SELECT '.$fieldName.' FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
							if ( $query != null ) {
								while ( list($record) = $query->fetch_object() ) {
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
						$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::habtmTableName( $this->_class, $relation ) );
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$key = Inflector::plural( strtolower($relation) );
						$fieldName = ( $details['field_name'] ? $details['field_name'] : strtolower($relation).'_id' );
						
						$query = $this->_db->read('SELECT '.$fieldName.' FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
						if ( $query != null ) {
							while ( list($record) = $query->fetch_object() ) {
								$this->_related[$key][] = $record;
							}
						} else {
							throw new Exception( 'Table '.$tableName.' does not exist' );
						}
					}
				}
				
				if ( isset($this->is_localized) && $this->is_localized ) {
					$this->_localized = array();
					$query = $this->db->query('SELECT * FROM '.$tableName.'_localized WHERE parent_id = '.$this->id);
					if ( $query != null ) {
						while ( $record = $query->fetch_object() ) {
							$lang = $record->lang;
							unset( $record->parent_id, $record->lang );
							$this->_localized[strtolower($lang)] = $record;
						}
					} else {
						throw new Exception( 'Table '.$tableName.' does not exist' );
					}
				}
			} else if ( $this->_driver == 'mongo' ) {
				$record = $this->_db->selectDB( $this->_database )->selectCollection( $this->_plural )->findOne( array( '_id' => new MongoID( $this->id ) ) );
				if ( $record !== NULL ) {
					list( $id, $this->_data, $this->_related, $this->_localized ) = $record;
					$this->_data['id'] = $id;
				}
			}
			
			if ( method_exists( $this, 'after_loading' ) ) {
				$this->after_loading();
			}
			
			$this->_writeCache();
		}
	}
	
	final public function save() {
		if ( !empty($this->_data) ) {
			if ( method_exists( $this, 'before_saving' ) ) {
				$this->before_saving();
			}
			
			if ( $this->_driver == 'mysql' ) {
				$this->_db->upsert( $this->_plural, $this->_data );
				
				if ( !isset($this->_data['id']) ) {
					$this->_data['id'] = $this->_db->last_id();
				}
							
				if ( isset($this->has_one) && !empty($this->has_one) ) {
					foreach ( $this->has_one as $relation => $details ) {
						if ( isset($relation['is_dependent']) && $relation['is_dependent'] ) {
							$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::plural( strtolower($relation) ) );
							$this->_db->upsert( $tableName, $this->_related[strtolower($relation)] );
						}
					}
				}
				
				if ( isset($this->has_many) && !empty($this->has_may) ) {
					foreach ( $this->has_many as $relation => $details ) {
						if ( isset($relation['is_dependent']) && $relation['is_dependent'] ) {
							$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::plural( strtolower($relation) ) );
							$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
							$key = Inflector::plural( strtolower($relation) );
							
							$this->_db->write('DELETE FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
							$this->_db->upsert( $tableName, $this->_related[strtolower($relation)] );
						}
					}
				}
				
				if ( isset($this->has_many_and_belongs_to_many) && !empty($this->has_many_and_belongs_to_many) ) {
					foreach ( $this->has_many_and_belongs_to_many as $relation => $details ) {
						$tableName = ( isset($details['table_name']) && !empty($details['table_name']) ? $details['table_name'] : Inflector::habtmTableName( $this->_class, $relation ) );
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$key = Inflector::plural( strtolower($relation) );
						$fieldName = ( $details['field_name'] ? $details['field_name'] : strtolower($relation).'_id' );
						
						$this->_db->write('DELETE FROM '.$tableName.' WHERE '.$fk.' = "'.$this->_db->escape($this->id).'"');
						$this->_db->upsert( $tableName, $this->_related[strtolower($relation)] );
					}
				}
				
				if ( isset($this->is_localized) && $this->is_localized ) {
					$this->db->write('DELETE FROM '.$tableName.'_localized WHERE parent_id = '.$this->id);
					foreach ( $this->_localized as $lang => $record ) {
						$record = (array) $record;
						$record = array_merge( array( 'parent_id' => $this->id, 'lang' => $lang ), $record );
						$this->_db->insert( $tableName, $record );
					}
				}
			} else if ( $this->_driver == 'mongo' ) {
				$collection = $this->_db->selectDB( $this->_database )->selectCollection( $this->_plural );
				$document = $this->_snapshot();
				$collection->update( array( '_id' => new MongoID( $this->id ) ), $document, array( 'upsert' => TRUE ) );
				if ( !isset($this->_data['id']) ) {
					$this->_data['id'] = $document['_id'];
				}
			}
			
			if ( method_exists( $this, 'after_saving' ) ) {
				$this->after_saving();
			}
			
			$this->_clearCache();
			
			return $this->id;
		}
	}
	
	final public function delete() {
		if ( method_exists( $this, 'before_deleting' ) ) {
			$this->before_deleting();
		}
		
		if ( $this->_driver == 'mysql' ) {
			$this->_db->write( 'DELETE FROM '.$this->_plural.' WHERE id = '.$this->id );
		} else if ( $this->_driver == 'mongo' ) {
			$this->_db->selectDB( $this->_database )->selectCollection( $this->_plural )->remove( array( '_id' => new MongoID( $this->id ) ) );
		}
		
		if ( method_exists( $this, 'after_deleting' ) ) {
			$this->after_deleting();
		}
		
		$this->_clearCache();
	}
	
	private function _isCached() {
		return file_exists( BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache' );
	}
	
	private function _readCache() {		
		if( !is_file( BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id ) ) {
			return;
		}
		
		$obj = unserialize(file_get_contents(BaseConfig::CACHE_PATH.$this->_class.'/'.$this->id.'.cache'));
		
		if( !is_object($obj) ) {
			return;
		}
		
		$vars = $obj->__getDump();
		
		foreach ( $vars as $key => $val ) {
			if ( !isset($this->$key) or (!is_object($this->$key)) or (is_object($this->$key) && get_class($this->$key) != 'Mysql' ) ) {
				$this->$key = $val;
			}
		}
	}
	
	private function _writeCache() {
		$content = serialize($this);
		if ( trim($content) != "" ) {
			if ( !file_exists(BaseConfig::CACHE_PATH.'/'.$this->_class) ) {
				mkdir(BaseConfig::CACHE_PATH.'/'.$this->_class, 0777, true);
			}
			@file_put_contents(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache', $content);
			@chmod(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache',0766);
			HttpReplicationClient::send(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache');
		}
	}
	
	private function _clearCache() {
		HttpReplicationClient::remove(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id.'.cache');

		if( is_file(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id) && file_exists(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id) ) {
			@unlink(BaseConfig::CACHE_PATH.'/'.$this->_class.'/'.$this->id);
		}
	}
	
	private function _snapshot() {
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
}
?>