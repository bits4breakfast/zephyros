<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\ServiceBus;

class Cache {
	
	const TTL_HALF_HOUR = 1800;
	const TTL_HOUR = 3600;
	const TTL_DAY = 86400;
	const TTL_TWO_DAYS = 259200;
	
	public static $app_id = null;
	public static $config = null;
	public static $memcache = null;

	public static $deleted_keys = [];
	
	public function __construct( Config $config ) {
		self::$app_id = $config->get('kernel.app_id');

		self::$memcache = new \Memcached( $config->get('memcache.connection_id') );
		self::$memcache->setOption( \Memcached::OPT_COMPRESSION, false );
		$servers_list = self::$memcache->getServerList();
		if ( empty($servers_list) ) {
			self::$memcache->addServer( $config->get('memcache.url'), $config->get('memcache.port') );
		}
	}
	
	public static function commit() {
		if ( !empty(self::$deleted_keys) ) {
			ServiceBus::emit( 'invalidate_cache', (array) array_keys(self::$deleted_keys) );
		}
	}
	
	public static function set( $key, $value, $ttl = self::TTL_HOUR ) {
		$key = self::$app_id.':'.$key;
			
		self::$memcache->set( $key, $value, self::TTL_TWO_DAYS );
		
		apc_store( $key, $value, $ttl );
	}
	
	public static function get( $key ) {
		$key = self::$app_id.':'.$key;
		$value = apc_fetch( $key );
		if ( $value !== false ) {
			return $value;
		} else {			
			$value = self::$memcache->get( $key );
			
			if ( $value !== false ) {
				apc_store( $key, $value, self::TTL_HOUR );
			}
			
			return $value;
		}
	}
	
	public static function delete( $key ) {
		$key = self::$app_id.':'.$key;
		
		if ( !isset(self::$deleted_keys[$key]) ) {
			self::$memcache->delete( $key );
			apc_delete( $key );
			self::$deleted_keys[$key] = true;
		}
	}
	
	public static function set_to_memcache( $key, $value, $ttl = self::TTL_TWO_DAYS ) {
		$key = self::$app_id.':'.$key;
		
		self::$memcache->set( $key, $value, $ttl );
	}
	
	public static function get_from_memcache( $key ) {
		$key = self::$app_id.':'.$key;
		
		return self::$memcache->get( $key );
	}
	
	public static function delete_from_memcache( $key ) {
		$key = self::$app_id.':'.$key;
		
		self::$memcache->delete( $key );
	}
}