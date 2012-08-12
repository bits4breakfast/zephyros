<?php
class BaseConfig {
	
	const DB_MASTER_HOST = 'master01';
	const DB_USER = 'ehrdb';
	const DB_PASSWORD = 'hicsuntl3on3s';
	const DB_DATABASE = 'test';
	static $slavesPool = array('master01','slave01');
	
	const BASE_PATH = '/var/www/zephyros/trunk';
	const CACHE_PATH = '/cache/zephyros';
	const LOGS_PATH = '';
}

class Config extends BaseConfig {
}

function class_loader( $className ) {
	if ( $className == 'Mysql' ) {
		include BaseConfig::BASE_PATH.'/library/Mysql.class.php';
	} else if ( $className == 'HttpReplicationClient' ) {
		include BaseConfig::BASE_PATH.'/library/Replica.class.php';
	} else {
		include BaseConfig::BASE_PATH.'/application/model/'.str_replace('_', '/', $className).'.class.php';
	}
}

spl_autoload_register( 'class_loader' );

include Config::BASE_PATH.'/library/ActiveRecord.class.php';

class User extends ActiveRecord {}

/*
$test = new User();
$test->name = 'Matteo';
$test->surname = 'Galli';
$test->email = 'matt@epoquehotels.com';
$id = $test->save();
*/
$test = new User( 3 );
?>