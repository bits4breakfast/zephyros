<?php
namespace Bits4breakfast\Zephyros\Exception\Http;

/*
	The server either does not recognize the request method, or it lacks the ability to fulfill the 
	request.[2] Usually this implies future availability (e.g., a new feature of a web-service API).
*/
class NotImplementedException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 501, $payload );
	}
}