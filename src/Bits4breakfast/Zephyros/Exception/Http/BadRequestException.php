<?php
namespace Bits4breakst\Zephyros\Exception\Http;

class BadRequestException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 400, $payload );
	}
}