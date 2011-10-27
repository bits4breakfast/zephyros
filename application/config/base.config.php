<?php
class BaseConfig {
	
	const DB_MASTER_HOST = '';
	const DB_USER = '';
	const DB_PASSWORD = '';
	const DB_DATABASE = '';
	static $slavesPool = array();
	
	const BASE_PATH = '';
	const CACHE_PATH = '';
	const LOGS_PATH = '';
	const BUILD_PATH = '';
	
	const USE_CACHE_REPLICATION = false;
	const USE_CLOUD_FILES = false;
}
?>