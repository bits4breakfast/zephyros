<?php
class BaseConfig {
	const NS = '';
	
	const YUICOMPRESSOR_PATH = '/var/www/vendor';
	
	/* paths */
	const BASE_PATH = '';
	const CACHE_PATH = '';
	const LOGS_PATH = '';
	const TEMP_PATH = '';

	const ASSETS_CDN_URL = '';
	const DATA_CDN_URL = '';
	const DATA_BUCKET = '';
	const RELEASES_BUCKET = '';
	const USE_REMOTE_DATA_BUCKET = false;
	
	/* email */
	const MAIL_SENDER_ADDRESS = '';
	const MAIL_SENDER_NAME = '';
	const SMTP_AUTH = TRUE;
	const SMTP_HOST = '';
	const SMTP_USER = '';
	const SMTP_PASSWORD = '';
	const SMTP_PORT = 465;
	const SMTP_SECURE = 'ssl';

	/* database */
	const DB_MASTER_HOST = '127.0.0.1';
	const DB_USER = 'root';
	const DB_PASSWORD = '';
	const DB_DATABASE = '';
	
	const MEMCACHE_CONNECTION_ID = '';
	const MEMCACHE_URL = '';
	const MEMCACHE_PORT = '';
	
	const CUSTOM_LOG_LEVEL = \zephyros\Logger::ERROR;
	const STANDARD_LOG_LEVEL = \zephyros\Logger::ERROR;
	const EMAIL_LOG_LEVEL = \zephyros\Logger::ERROR;
	const QUERY_LOG_LEVEL = \zephyros\Logger::ERROR;
	const WEBPAGE_LOG_LEVEL = \zephyros\Logger::ERROR;
	
	const SMARTY_COMPILE_CHECK = true;
	
	const MYSQL_SLOWQUERY_TRESHOLD = 10000;
	
	public static $bus_routing_table = array();
}