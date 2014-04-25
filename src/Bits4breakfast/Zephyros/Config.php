<?php
namespace Bits4breakfast\Zephyros;

class Config {
	public $app_base_path = '';
	public $subdomain = '';
	public $environment = '';

	public $configuration = [];

	public function __construct( $app_base_path, $subdomain, $environment = 'dev' ) {
		$this->app_base_path = $app_base_path;
		$this->subdomain = $subdomain;
		$this->environment = $environment;
		$this->load();
	}

	public function load() {
		$key = $this->app_base_path.':'.$this->subdomain.':config';
		if ( ($this->configuration = apc_fetch($key) ) === false ) {
			foreach ( ['', '_'.$this->environment, '_'.$this->subdomain] as $prefix) {
				if ( file_exists($this->app_base_path.'/App/config/config'.$prefix.'.json') ) {
					$configuration = json_decode( file_get_contents($this->app_base_path.'/App/config/config'.$prefix.'.json'), true );
					foreach ($configuration as $key => $value) {
						$this->configuration[$key] = $value;
					}
				}
			}

			apc_store($key, $this->configuration, 86400);
		}
	}

	public function get( $key ) {
		if ( isset($this->configuration[$key]) ) {
			return $this->configuration[$key];
		}

		return null;
	}

	public function dump() {
		return $this->configuration;
	}
}