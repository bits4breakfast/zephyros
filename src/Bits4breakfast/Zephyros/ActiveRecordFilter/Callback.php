<?php
namespace Bits4breakfast\Zephyros\ActiveRecordFilter;

class Callback extends AbstractFilter {
	const NAME = 'CALLBACK';
	const CODE = 'FILTER_CALLBACK';

	public $error_string = '';
	public $callback = null;

	public function __construct($error_string, callable $callback) {
		$this->error_string = $error_string;
		$this->callback = $callback;
	}
}