<?php
namespace bits4breakfast\zephyros\logger;

use bits4breakfast\zephyros\Logger;

abstract class AppenderBase {
	public $log_level = Logger::NONE;

	abstract protected function append( LogEntry $entry );

	public function log( LogEntry $entry) {
		if ( $this->logLevel > $entry->level ) {
			return;
		}

		$this->append($entry);
	}
}