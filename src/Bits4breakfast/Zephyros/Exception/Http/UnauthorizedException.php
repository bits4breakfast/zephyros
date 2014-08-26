<?php
namespace Bits4breakfast\Zephyros\Exception\Http;

/*
	Similar to 403 Forbidden, but specifically for use when authentication is required and has 
	failed or has not yet been provided.[2] The response must include a WWW-Authenticate header 
	field containing a challenge applicable to the requested resource.
*/
class UnauthorizedException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 401, $payload );
	}
}