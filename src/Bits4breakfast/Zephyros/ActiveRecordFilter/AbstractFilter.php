<?php
namespace Bits4breakfast\Zephyros\ActiveRecordFilter;

abstract class AbstractFilter {
	const NAME = '';
	const CODE = 0;

	public $error_string = self::NAME;

	public function __construct($error_string = '') {
		if ($error_string == '') {
			$this->error_string = self::CODE;
		} else {
			$this->error_string = $error_string;
		}
	}
}