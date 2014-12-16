<?php
namespace Bits4breakfast\Zephyros;

interface ConfigInterface {
    public function __construct($app_base_path, $subdomain, $environment = 'dev');

    public function load();

    public function get($key);

    public function dump();

    public function is_dev();
}