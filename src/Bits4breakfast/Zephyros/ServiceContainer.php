<?php
namespace Bits4breakfast\Zephyros;

use Monolog\Logger;

class ServiceContainer {

	private static $instance = null;
	private $services = [];

	public static function init( Config $config = null ) {
		if ( $config == null && self::$instance == null ) {
			throw new \InvalidArgumentException( 'config instance cannot be a null reference' );
		}

		if (self::$instance === null) {
			self::$instance = new ServiceContainer( $config  );
		}

		return self::$instance;
	}

	public static function instance() {
		return self::$instance;
	}

	private function __construct( Config $config ) {
		$this->register('bits4brekfast.zephyros.config', $config );
		$this->register('bits4brekfast.zephyros.logger', new Logger( 'bits4brekfast.zephyros.logger' ) );
		$this->register('bits4brekfast.zephyros.db', Mysql::init( $this ) );
		$this->register('bits4brekfast.zephyros.cache', new Cache( $this ) );
		$this->register('bits4brekfast.zephyros.lm', new LanguageManager( $this ) );
		$this->register('bits4brekfast.zephyros.service_bus', new ServiceBus( $this ) );
	}

	public function register( $service_id, $instance ) {
		if (empty($service_id)) {
			throw new \InvalidArgumentException( 'service_id cannot be an empty string' );
		}

		if ($instance == null) {
			throw new \InvalidArgumentException( 'instance cannot be a null reference' );
		}

		if (isset($this->services[$service_id])) {
			throw new \InvalidArgumentException( 'you cannot replace '.$service_id.' with another instance' );	
		}

		$this->services[$service_id] = $instance;

		return $this;
	}

	public function get( $service_id ) {
		if (empty($service_id)) {
			throw new \InvalidArgumentException( 'service_id cannot be an empty string' );
		}

		if ( isset($this->services[$service_id]) ) {
			return $this->services[$service_id];
		}

		switch ( $service_id ) {
			case 'logger':
				return $this->logger();
			case 'cache':
				return $this->cache();
			case 'config':
				return $this->config();
			case 'lm':
				return $this->lm();
			case 'db':
				return $this->db();
		}

		throw new \OutOfRangeException( '$service_id was not found' );
	}

	public function logger() {
		return $this->services['bits4brekfast.zephyros.logger'];
	}

	public function cache() {
		return $this->services['bits4brekfast.zephyros.cache'];
	}

	public function config() {
		return $this->services['bits4brekfast.zephyros.config'];
	}

	public function lm() {
		return $this->services['bits4brekfast.zephyros.lm'];
	}

	public function db() {
		return $this->services['bits4brekfast.zephyros.db'];
	}

	public function bus() {
		return $this->services['bits4brekfast.zephyros.service_bus'];
	}
}