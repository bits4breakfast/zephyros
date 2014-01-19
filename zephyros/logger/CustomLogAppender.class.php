<?php
namespace bits4breakfast\zephyros\logger;

class CustomLogAppender extends AppenderBase {

	const HEADER = "****************** Entry START ******************";
	const FOOTER = "******************  Entry END  ******************";

	private $fp = null;

	public function __construct() {
		$this->logLevel = \Config::CUSTOM_LOG_LEVEL;
	}

	public function append( LogEntry $entry ) {
		$text =
			self::HEADER . PHP_EOL
			. 'TIME: ' . @date('Y-m-d H:i:s') . PHP_EOL
			. 'TITLE: ' . $entry->title . PHP_EOL
			. 'DETAILS: ' . $entry->details . PHP_EOL
			. 'LEVEL: ' . $entry->level_name . PHP_EOL
			. 'STACK-TRACE: ' . $entry->stack_trace_text . PHP_EOL
			. 'URL: ' . $entry->url . PHP_EOL
			. 'METHOD: ' . $entry->request_method . PHP_EOL
			. 'QUERY: ' . $entry->query_string . PHP_EOL
			. 'AGENT: ' . $entry->agent . PHP_EOL
			. 'REFERRER: ' . $entry->referrer . PHP_EOL
			. 'EXCEPTION TYPE: ' . $entry->exception_type . PHP_EOL
			. self::FOOTER . PHP_EOL;

		if ($this->fp == null) {
			$this->fp = fopen(\Config::LOGS_PATH . '/errors/' . @date('Y-m-d') . '.log', 'a');
			if ($this->fp === false) {
				return;
			}
			@chmod(\Config::LOGS_PATH . '/errors/' . @date('Y-m-d') . '.log', 0666);
		}

		fwrite($this->fp, $text);
	}

	public function __destruct() {
		if ($this->fp) {
			fclose($this->fp);
		}
	}
}
?>