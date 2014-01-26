<?php
if ( getenv('ENVIRONMENT') == 'dev' ) {
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

include __DIR__.'/../vendor/autoload.php';

function zephyros_class_loader( $class_name ) {
	$fully_qualified_name_pieces = explode('\\', $class_name );
	
	if ( $fully_qualified_name_pieces[0] == 'bits4breakfast' ) {
		unset($fully_qualified_name_pieces[0]);
		include \Config::BASE_PATH.'/'.implode('/', $fully_qualified_name_pieces).'.class.php';
	} else if ( $fully_qualified_name_pieces[0] == \Config::NS ) {
		unset($fully_qualified_name_pieces[0]);
		include \Config::BASE_PATH.'/application/'.implode('/', $fully_qualified_name_pieces).'.class.php';
	}
}

spl_autoload_register( 'zephyros_class_loader' );