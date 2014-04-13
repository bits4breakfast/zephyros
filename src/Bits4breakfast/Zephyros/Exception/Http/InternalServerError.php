<?php
namespace Bits4breakst\Zephyros\Exception\Http;

/*
	A generic error message, given when an unexpected condition was encountered and no more 
	specific message is suitable.
*/
class InternalServerErrorException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 500, $payload );
	}
}