<?php
namespace Bits4breakfast\Zephyros;

use Monolog\Logger;

class ServiceContainer {

	private $services = [];

	public function __construct( Config $config ) {
		$this->services['bits4brekfast.zephyros.config'] = $config;
		$this->services['bits4brekfast.zephyros.logger'] = new Logger( 'bits4brekfast.zephyros.logger' );
		$this->services['bits4brekfast.zephyros.cache'] = new Cache( $config );
	}

	public function get( $service_id ) {
		if (empty($service_id)) {
			throw new \InvalidArgumentException( '$service_id cannot be an empty string' );
		}

		switch ( $service_id ) {
			case 'logger':
				return $this->logger();
		}
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
}