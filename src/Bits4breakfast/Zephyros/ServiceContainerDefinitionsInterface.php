<?php
namespace Bits4breakfast\Zephyros;

interface ServiceContainerDefinitionsInterface {

	public function __construct($app_base_path) {}

	public function load() {}

	public function get($key) {}

	public function dump() {}
}