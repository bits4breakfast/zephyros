<?php
namespace Bits4breakfast\Zephyros\Exception\Http;

class ConflictException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 409, $payload );
	}
}