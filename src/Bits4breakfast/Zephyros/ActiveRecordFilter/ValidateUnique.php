<?php
namespace Bits4breakfast\Zephyros\ActiveRecordFilter;

class ValidateUnique extends AbstractFilter {
	const NAME = 'UNIQUE';
	const CODE = '';

	public $error_string = '';

	public function __construct($error_string) {
		$this->error_string = $error_string;
	}
}