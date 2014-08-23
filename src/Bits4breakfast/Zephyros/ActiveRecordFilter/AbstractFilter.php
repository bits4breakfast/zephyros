<?php
namespace Bits4breakfast\Zephyros\ActiveRecordFilter;

abstract class AbstractFilter {
	const NAME = '';
	const CODE = 0;

	public $error_string = self::NAME;
}