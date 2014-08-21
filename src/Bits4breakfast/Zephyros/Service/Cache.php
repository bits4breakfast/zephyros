<?php
namespace Bits4breakfast\Zephyros\Service;

use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceInterface;

class Cache implements ServiceInterface {
	
	const TTL_HALF_HOUR = 1800;
	const TTL_HOUR = 3600;
	const TTL_DAY = 86400;
	const TTL_TWO_DAYS = 259200;
	
	private $app_id = null;
	private $config = null;
	private $memcache = null;

	private $deleted_keys = [];
	
	public function __construct( ServiceContainer $container ) {
		$config = $container->config();

		$this->app_id = $config->get('kernel.app_id');

		$this->memcache = new \Memcached($config->get('cache.memcache.connection_id'));
		$this->memcache->setOption( \Memcached::OPT_COMPRESSION, false );
		$servers_list = $this->memcache->getServerList();
		if ( empty($servers_list) ) {
			$this->memcache->addServer($config->get('cache.memcache.url'), $config->get('cache.memcache.port'));
		}
	}
	
	public function commit() {
		if ( !empty($this->deleted_keys) ) {
			$this->container->bus()->emit( 'invalidate_cache', (array) array_keys($this->deleted_keys) );
		}
	}
	
	public function set( $key, $value, $ttl = self::TTL_HOUR ) {
		$key = $this->app_id.':'.$key;
			
		$this->memcache->set( $key, $value, self::TTL_TWO_DAYS );
		
		apc_store( $key, $value, $ttl );
	}
	
	public function get( $key ) {
		$key = $this->app_id.':'.$key;
		$value = apc_fetch( $key );
		if ( $value !== false ) {
			return $value;
		} else {			
			$value = $this->memcache->get( $key );
			
			if ( $value !== false ) {
				apc_store( $key, $value, self::TTL_HOUR );
			}
			
			return $value;
		}
	}
	
	public function delete( $key ) {
		$key = $this->app_id.':'.$key;
		
		if ( !isset($this->deleted_keys[$key]) ) {
			$this->memcache->delete( $key );
			apc_delete( $key );
			$this->deleted_keys[$key] = true;
		}
	}
	
	public function set_to_memcache( $key, $value, $ttl = self::TTL_TWO_DAYS ) {
		$key = $this->app_id.':'.$key;
		
		$this->memcache->set( $key, $value, $ttl );
	}
	
	public function get_from_memcache( $key ) {
		$key = $this->app_id.':'.$key;
		
		return $this->memcache->get( $key );
	}
	
	public function delete_from_memcache( $key ) {
		$key = $this->app_id.':'.$key;
		
		$this->memcache->delete( $key );
	}
}