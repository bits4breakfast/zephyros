<?php
namespace Bits4breakst\Zephyros\Exception\Http;

class HttpException extends \RuntimeException {

	public $payload = [];

	public function __construct( $message, $error_code, $payload ) {
		$this->payload = (array) $payload;

		parent::__construct($message, $error_code);
	}
}