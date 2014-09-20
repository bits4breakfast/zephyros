<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\Config;
use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceContainerDefinitions;

use Monolog\Logger;
use Bits4breakfast\Zephyros\Service\Mysql;
use Bits4breakfast\Zephyros\Service\Cache;
use Bits4breakfast\Zephyros\Service\LanguageManager;
use Bits4breakfast\Zephyros\Service\MessageBus;

class ServiceContainer {

	private static $instance = null;
	
	private $service_container_definitions = null;
	private $services = [];

	public static function init(Config $config = null, ServiceContainerDefinitions $service_container_definitions = null) {
		if ($config == null && self::$instance == null) {
			throw new \InvalidArgumentException( 'config instance cannot be a null reference' );
		}

		if ($service_container_definitions == null && self::$instance == null) {
			throw new \InvalidArgumentException( 'service_container_definitions instance cannot be a null reference' );
		}

		if (self::$instance === null) {
			self::$instance = new ServiceContainer($config, $service_container_definitions);
		}

		return self::$instance;
	}

	private function __construct(Config $config, ServiceContainerDefinitions $service_container_definitions) {
		$this->service_container_definitions = $service_container_definitions;

		$this->register('bits4brekfast.zephyros.config', $config );
		$this->register('bits4brekfast.zephyros.logger', new Logger('bits4brekfast.zephyros.logger') );
		$this->register('bits4brekfast.zephyros.db', new Mysql($this) );
		$this->register('bits4brekfast.zephyros.cache', new Cache($this) );
		$this->register('bits4brekfast.zephyros.lm', new LanguageManager($this) );
		$this->register('bits4brekfast.zephyros.message_bus', new MessageBus($this) );
	}

	public function register($service_id, $instance) {
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

	public function get($service_id) {
		if (empty($service_id)) {
			throw new \InvalidArgumentException( 'service_id cannot be an empty string' );
		}

		if (isset($this->services[$service_id])) {
			return $this->services[$service_id];
		}

		switch ($service_id) {
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
			case 'mailer':
				return $this->mailer();
		}

		if ($class_name = $this->service_container_definitions->get($service_id)) {
			if (in_array('Bits4breakfast\\Zephyros\\ServiceInterface', class_implements($class_name))) {
				$instance = new $class_name($this);
				$this->register($service_id, $instance);

				return $instance;
			}

			throw new \RuntimeException($class_name.' must implement Bits4breakfast\\Zephyros\\ServiceInterface');
		}

		throw new \OutOfRangeException('$service_id was not found');
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
		return $this->services['bits4brekfast.zephyros.message_bus'];
	}

	public function mailer() {
		if (!isset($this->services['bits4brekfast.zephyros.mailer'])) {
			$this->register('bits4brekfast.zephyros.mailer', new Service\Mailer($this));
		}
		return $this->services['bits4brekfast.zephyros.mailer'];
	}
}