<?php
namespace bits4breakfast\zephyros;

use bits4breakfast\zephyros\ServiceBus;

class Cache {
	
	const TTL_HALF_HOUR = 1800;
	const TTL_HOUR = 3600;
	const TTL_DAY = 86400;
	const TTL_TWO_DAYS = 259200;
	
	public static $instance = null;
	public static $deleted_keys = [];
	
	public $memcache = null;
	
	public function __construct() {
		$this->memcache = new \Memcached( \Config::MEMCACHE_CONNECTION_ID );
		$this->memcache->setOption( \Memcached::OPT_COMPRESSION, false );
		$servers_list = $this->memcache->getServerList();
		if ( empty($servers_list) ) {
			$this->memcache->addServer( \Config::MEMCACHE_URL, \Config::MEMCACHE_PORT );
		}
	}
	
	public static function exists( $key ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		return ( apc_exists( $key ) || self::$instance->memcache->append( $key, null ) );
	}

	public function __destruct() {
		//self::commit();	
	}
	
	public static function commit() {
		if ( !empty(self::$deleted_keys) ) {
			ServiceBus::emit( 'invalidate_cache', (array) array_keys(self::$deleted_keys) );
		}
	}
	
	public static function set( $key, $value, $ttl = self::TTL_HOUR ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
			
		self::$instance->memcache->set( $key, $value, self::TTL_TWO_DAYS );
		
		apc_store( $key, $value, $ttl );
	}
	
	public static function get( $key ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		$value = apc_fetch( $key );
		if ( $value !== false ) {
			return $value;
		} else {
			if ( self::$instance === null ) {
				self::$instance = new Cache();
			}
			
			$value = self::$instance->memcache->get( $key );
			
			if ( $value !== false ) {
				//self::$instance->memcache->touch( $key, self::TTL_TWO_DAYS );
				apc_store( $key, $value, self::TTL_HOUR );
			}
			
			return $value;
		}
	}
	
	public static function delete( $key ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		if ( !isset(self::$deleted_keys[$key]) ) {
			self::$instance->memcache->delete( $key );
			apc_delete( $key );
			self::$deleted_keys[$key] = true;
		}
	}
	
	public static function set_to_memcache( $key, $value, $ttl = self::TTL_TWO_DAYS ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		self::$instance->memcache->set( $key, $value, $ttl );
	}
	
	public static function get_from_memcache( $key ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		return self::$instance->memcache->get( $key );
	}
	
	public static function delete_from_memcache( $key ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		self::$instance->memcache->delete( $key );
	}
	
	public static function append_to_memcache( $key, $value ) {
		$key = \Config::BASE_DOMAIN.':'.$key;
		if ( self::$instance === null ) {
			self::$instance = new Cache();
		}
		
		$previous_value = self::$instance->memcache->get( $key );
		self::$instance->memcache->set( $key, $previous_value.$value );
	}
}
?>