<?php
namespace Bits4breakfast\Zephyros;

use Monolog\Logger;

class ServiceContainer {

	private $services = [];

	public function __construct( $subdomain ) {
		$this->subdomain = $subdomain;
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
		if ( !isset($this->services['bits4brekfast.zephyros.logger']) ) {
			$this->services['bits4brekfast.zephyros.logger'] = new Logger( 'bits4brekfast.zephyros.logger' );
		}

		return $this->services['bits4brekfast.zephyros.logger'];
	}
}