<?php
namespace Bits4breakst\Zephyros\Exception\Http;

class BadRequestException extends \RuntimeException {
	public function __construct( $message = "" ) {
		parent::__construct( $message, 400 );
	}
}