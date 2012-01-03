<?php
if ( getenv('ENVIRONMENT') == 'test' ) {
	define('TEST_ENVIRONMENT',true);
	define('PROD_ENVIRONMENT',false);
	error_reporting(E_ALL);
} else {
	define('TEST_ENVIRONMENT',false);
	define('PROD_ENVIRONMENT',true);
}

include BaseConfig::BASE_PATH.'/library/Controller.class.php';

function zephyros_class_loader( $className ) {
	if ( $className == 'Mysql' ) {
		include BaseConfig::BASE_PATH.'/library/Mysql.class.php';
	} else if ( $className == 'ActiveRecord' ) {
		include BaseConfig::BASE_PATH.'/library/ActiveRecord.class.php';
	} else if ( $className == 'HttpReplicationClient' ) {
		include BaseConfig::BASE_PATH.'/library/Replica.class.php';
	} else if ( $className == 'UserInterface' ) {
		include BaseConfig::BASE_PATH.'/library/UserInterface.class.php';
	} else if ( substr($className,0,3) == 'BL_' ) {
		include BaseConfig::BASE_PATH.'/application/bl/'.str_replace( '_', '/', str_replace( 'BL_', '', $className ) ).'.class.php';
	} else if ( substr($className, -2) == 'UI' ) {
		include BaseConfig::BASE_PATH.'/application/interfaces/'.Config::SUBDOMAIN.'/'.str_replace('_', '/', $className).'.class.php';
	} else {
		include BaseConfig::BASE_PATH.'/application/model/'.str_replace('_', '/', $className).'.class.php';
	}
}

spl_autoload_register( 'zephyros_class_loader' );
?>