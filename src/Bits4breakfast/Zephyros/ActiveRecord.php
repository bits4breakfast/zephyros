<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakst\Zephyros\Exception\Http\BadRequestException;

abstract class ActiveRecord {

	const first = 0;
	const all = 1;
	const last = 2;
	const random = 3;
	const notnull = 'zephyros_ActiveRecord_notnull';
	const isnull = 'zephyros_ActiveRecord_isnull';
	const now = 'zephyros_ActiveRecord_now';
	const current_date = 'zephyros_ActiveRecord_current_date';
	
	// Object setup
	protected static $instances = [];

	protected $_db = null;
	protected $_container = null;

	protected $_shard = null;
	protected $_database = '';
	protected $_class = '';
	protected $_name = '';
	protected $_table = '';
	protected $_fkName = '';
	protected $_columns = [];
	protected $_didLoad = true;

	// Data storage
	protected $_data = [];
	protected $_related = [];
	protected $_localized = [];

	protected $_encrypt = [];

	public function __construct( $id = NULL, $load = true, $strict = false ) {
		$this->_container = ServiceContainer::init();

		if ( isset($this->shard) && trim($this->shard) != '' ) {
			$this->_shard = $this->shard;
		} else {
			$this->_shard = $this->_container->config()->get('database_shards_default');
		}

		$this->_class = get_class( $this );
		$this->_name = strtolower( Inflector::decamelize( join('', array_slice(explode('\\', $this->_class), -1)), '_' ) );
		$this->_fkName = $this->_name.'_id';
		
		if ( isset($this->table_name) && trim($this->table_name) != '' ) {
			$this->_table = $this->table_name;
		} else {
			$this->_table = $this->_name;
		}

		$this->_db = Mysql::init($this->_container);
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
					if ( $value === self::now ) {
						$value = Mysql::utc_timestamp();
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
		if ( $value === self::now ) {
			$value = Mysql::utc_timestamp();
		}
		
		if ( is_array($value) || is_object($value) ) {
			$this->_related[$key] = $value;
			$this->_related['_changed'][$key] = true;
		} else {
			if ($value === null && isset($this->_related[$key])) {
				$this->_related[$key] = $value;
			} else {
				$this->_data[$key] = $value;
			}
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
				throw new \Exception( 'Missing value to add to '.Inflector::plural($name) );
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
			return $this->localize( $arguments, str_replace( 'localize_in_', '', $name ) );
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
		$this->_related[$property] = [];
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
		if ( is_scalar($arguments) ) {
			if ( isset($this->_localized[$lang][$arguments]) ) {
				return $this->_localized[$lang][$arguments];
			} else {
				if ( isset($this->_localized['en'][$arguments]) ) {
					return $this->_localized['en'][$arguments];
				}
			}
		} else if ( is_array($arguments) && !isset($arguments[1]) ) {
			if ( isset($this->_localized[$lang][$arguments[0]]) ) {
				return $this->_localized[$lang][$arguments[0]];
			} else {
				if ( isset($this->_localized['en'][$arguments[0]]) ) {
					return $this->_localized['en'][$arguments[0]];
				}
			}
		} else {
			$this->_localized[$lang][$arguments[0]] = $arguments[1];
		}
	}
	
	final public static function find( $what = first, $conditions = null, $options = null ) {
		$calledClass = get_called_class();
		$temp = new $calledClass();
		$temp = $temp->_reflection();
		
		$db = Mysql::init(ServiceContainer::init());

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
		} else if ( $what == random ) {
			$query .= ' ORDER BY RAND() LIMIT 1';
		} else {
			if ( isset($options['orderby']) ) {
				$query .= ' ORDER BY '.$options['orderby'];
			}
			
			if ( isset($options['limit']) ) {
				$start = isset($options['start']) ? (int) $options['start'] : 0;
				$query .= ' LIMIT '.$start.','.$options['limit'];
			}
		}
		
		$result = $db->pick( $temp->_shard )->read( $query );
		if ( $result === false ) {
			throw new FindException( $db->read_error(), $db->read_errno() );
		} else {
			if ( $result->num_rows == 0 ) {
				return null;
			}
			
			if ( $what == first || $what == last || $what == random ) {
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
		
		$db = Mysql::init(ServiceContainer::init());

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
		
		$result = $db->pick( $temp->_shard )->read( $query );
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
			
			$this->read_from_cache();
			
			if ( method_exists( $this, 'after_restoring' ) ) {
				$this->after_restoring();
			}
		} else {
			if ( method_exists( $this, 'before_loading' ) ) {
				$this->before_loading();
			}
			$queryStr = 'SELECT * FROM '.$this->_table.' WHERE id = "'.$this->_db->escape($this->_data['id']).'" LIMIT 1';
			$query = $this->_db->pick($this->_shard)->read($queryStr);
			if ( $query != null ) {
				$record = $query->fetch_object();
				if ( $record != null ) {
					foreach ( $record as $key => $value ) {
						$this->_data[$key] = $value;
					}
				} else {
					if ( $strict ) {
						throw new NonExistingItemException(sprintf("Record with id %s not found in table '%s'.", $this->_data['id'], $this->_table));
					}
				}
			} else {
				$msg = $this->_db->pick($this->_shard)->read_error();
				throw new \Exception( "Query execution failure, reason: $msg. Query: $queryStr" );
			}
			
			if ( isset($this->has_one) && !empty($this->has_one) ) {
				foreach ( $this->has_one as $relation => $details ) {
					if ( isset($details['do_not_load']) && $details['do_not_load'] ) {
						continue;
					}
					
					if ( isset($details['table_name']) && !empty($details['table_name']) ) {
						$table_name = $details['table_name'];
					} else {
						$table_name = Inflector::plural(strtolower($relation));
						$table_name = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$table_name;
						$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
					}

					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$query = $this->_db->pick($this->_shard)->read('SELECT * FROM '.$table_name.' WHERE '.$fk.' = "'.$this->_db->escape($this->_data['id']).'" LIMIT 1');
						$this->_related[$relation] = ( $query->num_rows > 0 ? (object) $query->fetch_object() : null );
					} else {
						$this->_related[$relation] = $this->_db->pick($this->_shard)->result('SELECT id FROM '.$table_name.' WHERE '.$fk.' = "'.$this->_db->escape($this->_data['id']).'" LIMIT 1');
					}
				}
			}
			
			if ( isset($this->has_many) && !empty($this->has_many) ) {
				foreach ( $this->has_many as $relation => $details ) {
					if ( isset($details['do_not_load']) && $details['do_not_load'] ) {
						continue;
					}
					
					$key = Inflector::plural( strtolower($relation) );
					if ( isset($details['table_name']) && !empty($details['table_name']) ) {
						$table_name = $details['table_name'];
					} else {
						$table_name = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$key;
						$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
					}
					
					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$query = $this->_db->pick($this->_shard)->read('SELECT * FROM '.$table_name.' WHERE '.$fk.' = "'.$this->_db->escape($this->_data['id']).'"');
						if ( $query != null ) {
							while ( $record = $query->fetch_object() ) {
								$this->_related[$key][] = $record;
							}
						} else {
							throw new \Exception( 'Table '.$table_name.' does not exist' );
						}
					} else {
						$field_name = ( isset($details['field_name']) ? $details['field_name'] : 'id' );
						$query = $this->_db->pick($this->_shard)->read('SELECT '.$field_name.' FROM '.$table_name.' WHERE '.$fk.' = "'.$this->_db->escape($this->_data['id']).'"');
						if ( $query != null ) {
							while ( list($record) = $query->fetch_row() ) {
								$this->_related[$key][] = $record;
							}
						} else {
							throw new \Exception( 'Table '.$table_name.' does not exist' );
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
					if ( isset($details['table_name']) && !empty($details['table_name']) ) {
						$table_name = $details['table_name'];
					} else {
						$table_name = Inflector::habtmTableName( $this->_name, $relation );
						$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
					}
					
					$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
					$field_name = ( isset($details['field_name']) ? $details['field_name'] : strtolower($relation).'_id' );
					
					$query = $this->_db->pick($this->_shard)->read('SELECT '.$field_name.' FROM '.$table_name.' WHERE '.$fk.' = "'.$this->_db->escape($this->_data['id']).'"');
					if ( $query != null ) {
						while ( list($record) = $query->fetch_row() ) {
							$this->_related[$key][] = $record;
						}
					} else {
						throw new \Exception( 'Table '.$table_name.' does not exist' );
					}
				}
			}
			
			if ( isset($this->is_localized) && $this->is_localized ) {
				$this->_localized = [];
				$query = $this->_db->pick($this->_shard)->read('SELECT * FROM '.$this->_table.'_localized WHERE '.$this->_fkName.' = '.$this->_data['id']);
				if ( $query != null ) {
					while ( $record = $query->fetch_object() ) {
						$lang = $record->lang;
						unset( $record->{$this->_fkName}, $record->lang );
						$this->_localized[strtolower($lang)] = (array) $record;
					}
				} else {
					throw new \Exception( 'Table '.$table_name.' does not exist' );
				}
			}
			
			if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
				$this->write_to_cache();
			}
			
			if ( method_exists( $this, 'after_loading' ) ) {
				$this->after_loading();
			}
		}
	}

	final public function apply_patch( $patch, $patching_schema = 'default' ) {
		$patching_schema = $this->patching_schema($patching_schema);
		if (empty($patching_schema)) {
			throw new BadRequestException;
		}

		foreach ( $patching_schema as $key ) {
			if ( isset($patch[$key]) ) {
				$this->_data[$key] = $patch[$key];
			}
		}
	}

	final public function validate( $validation_schema = 'default' ) {
		$validation_schema = $this->validation_schema( $validation_schema );
		if ( empty($validation_schema) ) {
			return null;
		}

		$errors = [];

		return $errors;
	}
	
	final public function save() {
		if ( !empty($this->_data) ) {
			if ( method_exists( $this, 'before_saving' ) ) {
				$this->before_saving();
			}
			
			$this->_db->pick($this->_shard)->upsert( $this->_table, $this->_data, (isset($this->columns_to_increment)?$this->columns_to_increment:null) );
			
			if ( ( !isset($this->_data['id']) || ( isset($this->_data['id']) && $this->_data['id'] == 0 ) ) && ( !isset($this->has_composite_primary_key) || ( isset($this->has_composite_primary_key) && !$this->has_composite_primary_key ) ) ) {
				$this->_data['id'] = (int) $this->_db->pick($this->_shard)->last_id();
				if ( $this->_data['id'] == 0 ) {
					throw new PersistingErrorException();
				}
			}
						
			if ( isset($this->has_one) && !empty($this->has_one) ) {
				foreach ( $this->has_one as $relation => $details ) {
					if ( isset($details['is_dependent']) && $details['is_dependent'] ) {
						$key = strtolower($relation);
						if ( isset($this->_related['_changed'][$relation]) || isset($this->_related['_changed'][$key]) ) {
							if ( isset($details['table_name']) && !empty($details['table_name']) ) {
								$table_name = $details['table_name'];
							} else {
								$table_name = Inflector::plural(strtolower($relation));
								$table_name = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$table_name;
								$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
							}
							
							$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
							
							$record = (array)$this->_related[strtolower($relation)];
							if ( empty($record) ) {
								$this->_db->pick($this->_shard)->delete( $table_name, [ $fk => $this->_data['id'] ] );
							} else {
								$record = array_merge( [ $fk => $this->_data['id'] ], $record );
								$this->_db->pick($this->_shard)->upsert( $table_name, $record );
							}
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
						if ( isset($details['table_name']) && !empty($details['table_name']) ) {
							$table_name = $details['table_name'];
						} else {
							$table_name = Inflector::habtmTableName( $this->_name, $relation );
							$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
						}
						
						$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
						$field_name = ( isset($details['field_name']) ? $details['field_name'] : strtolower($relation).'_id' );
						
						$this->_db->pick($this->_shard)->delete( $table_name, [ $fk => $this->_data['id'] ] );
						foreach ( $this->_related[$key] as $recordId ) {
							$this->_db->pick($this->_shard)->insert( $table_name, [ $fk => $this->_data['id'], $field_name => $recordId ] );
						}
					}
				}
			}
			
			if ( isset($this->is_localized) && $this->is_localized ) {
				$this->_db->pick($this->_shard)->delete( $this->_table.'_localized', [ $this->_fkName => $this->_data['id'] ] );
				foreach ( $this->_localized as $lang => $record ) {
					$record = (array) $record;
					$record = array_merge( [ $this->_fkName => $this->_data['id'], 'lang' => $lang ], $record );
					$this->_db->pick($this->_shard)->insert( $this->_table.'_localized', $record );
				}
			}
			
			if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
				$this->_clearCache();
			}

			if ( method_exists( $this, 'after_saving' ) ) {
				$this->after_saving();
			}
			
			return $this->_data['id'];
		}
	}
	
	final public function save_dependent( $relation, $details = null ) {
		$key = Inflector::plural( strtolower($relation) );
		
		if ( isset($this->_related['_changed'][$key]) || isset($this->_related['_changed'][$relation]) ) {
			if ( isset($details['table_name']) && !empty($details['table_name']) ) {
				$table_name = $details['table_name'];
			} else {
				$table_name = ( isset($details['is_dependent']) && $details['is_dependent'] ? $this->_table.'_' : '' ).$key;
				$table_name = ( isset($this->_database) && $this->_database != '' ? $this->_database.'.' : '' ).$table_name;
			}

			$fk = ( isset($details['foreign_key']) && !empty($details['foreign_key']) ? $details['foreign_key'] : $this->_fkName );
			
			$this->_db->pick($this->_shard)->delete( $table_name, [$fk => $this->_data['id'] ] );
			foreach ( $this->_related[$key] as $record ) {
				if ( is_array($record) ) {
					unset($record[$fk]);
					$record = array_merge( [$fk => $this->_data['id']], $reco );
				} else {
					$record = (array)$record;
					unset($record[$fk]);
					$record = array_merge( [$fk => $this->_data['id']], $reco );
				}
				
				$this->_db->pick($this->_shard)->insert( $table_name, $record );
			}
			
		}
	}
	
	final public function delete() {
		if ( method_exists( $this, 'before_deleting' ) ) {
			$this->before_deleting();
		}
		
		$this->_db->pick($this->_shard)->delete( $this->_table, ['id' => $this->_data['id'] ] );
		
		if ( method_exists( $this, 'after_deleting' ) ) {
			$this->after_deleting();
		}
		
		if ( !isset($this->do_not_cache) || ( isset($this->do_not_cache) && !$this->do_not_cache ) ) {
			$this->_clearCache();
		}
	}
	
	private function _isCached() {
		return Cache::exists( 'ar:'.$this->_class.':'.$this->_data['id'] );
	}
	
	final public function read_from_cache() {
		$obj = Cache::get( 'ar:'.$this->_class.':'.$this->_data['id'] );
		
		if( $obj === false ) {
			return;
		}
		
		$vars = $obj->_snapshot();
		
		foreach ( $vars as $key => $val ) {
			$this->$key = $val;
		}
	}
	
	final public function write_to_cache() {
		Cache::set( 'ar:'.$this->_class.':'.$this->_data['id'], $this, 7200 );
	}
	
	final public function clear_cache() {
		Cache::delete( 'ar:'.$this->_class.':'.$this->_data['id'] );
	}
	
	final public function _snapshot() {
		return [
			'_data' => $this->_data,
			'_related' => $this->_related,
			'_localized' => $this->_localized
		];
	}
	
	final public function __sleep() {
		return [ '_data', '_related', '_localized' ];
	}
	
	public function __toString() {
		return $this->_class.' #'.$this->_data['id'];
	}
	
	final public function _reflection() {
		return (object) [
			'_database' => $this->_database,
			'_shard' => $this->_shard,
			'_class' => $this->_class,
			'_name' => $this->_name,
			'_table' => $this->_table,
			'_fkName' => $this->_fkName,
			'_columns' => $this->_columns
		];
	}
}

class NonExistingItemException extends \Exception {}
class FindException extends \Exception {}
class PersistingErrorException extends \Exception {}