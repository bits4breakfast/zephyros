<?php

if (empty($_SERVER['UNIT_TEST_RUNTIME'])) {
	if ( getenv('ENVIRONMENT') == 'dev' || getenv('ENVIRONMENT') == false ) {
		define('DEV_ENVIRONMENT', true);
		define('TEST_ENVIRONMENT', false);
		define('PROD_ENVIRONMENT', false);
		error_reporting(E_ALL);
	} else if ( getenv('ENVIRONMENT') == 'test' ) {
		define('DEV_ENVIRONMENT', false);
		define('TEST_ENVIRONMENT', true);
		define('PROD_ENVIRONMENT', false);
		error_reporting(E_ALL);
	} else {
		define('DEV_ENVIRONMENT', false);
		define('TEST_ENVIRONMENT', false);
		define('PROD_ENVIRONMENT', true);
	}
}

function zephyros_class_loader( $className ) {
	if ( $className == 'Mysql' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Mysql.class.php';
	} else if ( $className == 'ActiveRecord' ) {
		include BaseConfig::BASE_PATH.'/zephyros/ActiveRecord.class.php';
	} else if ( $className == 'Controller' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Controller.class.php';
	} else if ( $className == 'UserInterface' ) {
		include BaseConfig::BASE_PATH.'/zephyros/UserInterface.class.php';
	} else if ( $className == 'Smarty' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Smarty/Smarty.class.php';
	} else if ( $className == 'Router' || $className == 'RouteParameters' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Router.class.php';
	} else if ( $className == 'Logger' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Logger/Logger.class.php';
	} else if ( $className == 'Email' ) {
		include BaseConfig::BASE_PATH.'/zephyros/Email.class.php';
	} else if ( $className == 'LanguageManager' ) {
		include BaseConfig::BASE_PATH.'/zephyros/LanguageManager.class.php';
	} else if ( substr($className, 0, 3) == 'BL_' ) {
		include BaseConfig::BASE_PATH.'/application/bl/'.str_replace( '_', '/', str_replace( 'BL_', '', $className ) ).'.class.php';
	} else if ( substr($className, -2) == 'UI' ) {
		include BaseConfig::BASE_PATH.'/application/interfaces/'.Config::SUBDOMAIN.'/'.str_replace('_', '/', $className).'.class.php';
	} else if ( strpos( $className, 'Smarty' ) === false ) {
		include BaseConfig::BASE_PATH.'/application/model/'.str_replace('_', '/', $className).'.class.php';
	}
}

spl_autoload_register( 'zephyros_class_loader' );
?>