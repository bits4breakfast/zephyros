<?php
namespace Bits4breakfast\Zephyros\Exception\Http;

class NotFoundException extends HttpException {
	public function __construct( $message = "", $payload = [] ) {
		parent::__construct( $message, 404, $payload );
	}
}