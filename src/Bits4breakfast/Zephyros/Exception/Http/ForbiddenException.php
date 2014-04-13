<?php
namespace Bits4breakst\Zephyros\Exception\Http;

/*
	The request was a valid request, but the server is refusing to respond to it.[2] Unlike a 401 
	Unauthorized response, authenticating will make no difference.[2] On servers where 
	authentication is required, this commonly means that the provided credentials were successfully 
	authenticated but that the credentials still do not grant the client permission to access the 
	resource (e.g., a recognized user attempting to access restricted content).
*/
class ForbiddenHttpException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 403, $payload );
	}
}