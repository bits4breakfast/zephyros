<?php
namespace Bits4breakfast\Zephyros;

interface ServiceInterface {

	protected $container;

	public function __construct(ServiceContainer $container) {}
}