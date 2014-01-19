<?php
namespace bits4breakfast\zephyros\logger;

use bits4breakfast\zephyros\Logger;

class LogEntry {
	public $level;
	public $level_name;
	public $title;
	public $details;
	public $url;
	public $agent;
	public $request_method;
	public $query_string;
	public $referrer;
	public $exception_type;
	public $stack_trace_text;

	const MAXSTRLEN = 64;

	public function __construct($level, $title, $details = null, $stack_trace_text = null) {
		$this->level = $level;
		$this->level_name = Logger::level_name($level);
		if (is_scalar($title)) {
			$this->title = $title;
		} else {
			$this->title = var_export($title, true);
		}

		if ( is_scalar($details) ) {
			$this->details = str_replace(realpath(\Config::BASE_PATH), '', $details);
		} else {
			$this->details = var_export( $details, true );
		}

		if (isset($_SERVER['REQUEST_URI'])) {
			$this->url = $_SERVER['REQUEST_URI'];
			$this->agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
			$this->request_method = $_SERVER['REQUEST_METHOD'];

			if ($_POST) {
				$kv = array();
				foreach ($_POST as $key => $value) {
					@$kv[] = "$key=$value";
				}

				$this->query_string = join("&", $kv);
			} else {
				$this->query_string = $_SERVER['QUERY_STRING'];
			}
		} else {
			$this->url = $_SERVER['SCRIPT_FILENAME'];
		}

		if (isset($_SERVER['HTTP_REFERER']))
			$this->referrer = $_SERVER['HTTP_REFERER'];

		if ($stack_trace_text != null) {
			$this->stack_trace_text = $stack_trace_text;
		} else if ($level == Logger::ERROR || $level == Logger::CRITICAL) {
			$this->stack_trace_text = $this->build_stack_trace_text();
		}
	}

	private function build_stack_trace_text() {
		$s = '';
		$traces = debug_backtrace();
		array_shift($traces);
		$tabs = sizeof($traces) - 1;
		foreach ($traces as $trace) {
			$tabs -= 1;
			if (isset($trace['class'])) {
				$s .= $trace['class'] . '.';
			}

			$s .= $trace['function'] . '(';

			$s .= ')';
			$s .= sprintf(" at %s (line: %4d)", isset($trace['file']) ? str_replace(realpath(\Config::BASE_PATH), '', $trace['file']) : '', isset($trace['line']) ? $trace['line'] : '');
			$s .= PHP_EOL;

			unset($args);
		}
		$s .= '';

		return $s;
	}
}
?>