<?php
namespace Bits4breakfast\Zephyros;

class Validator {
	const BOOLEAN = FILTER_VALIDATE_BOOLEAN;
	const FLOAT = FILTER_VALIDATE_FLOAT;
	const INT = FILTER_VALIDATE_INT;
	const URL = FILTER_VALIDATE_URL;
	const EMAIL = FILTER_VALIDATE_EMAIL;
	const UNIQUE = 'zephyros_Validator_unique';
	const NOT_EMPTY = 'zephyros_Validator_not_empty';
}