<?php
namespace bits4breakfast\zephyros\logger;

class QueryLogAppender extends AppenderBase {
	private $fp = null;

	public function __construct() {
		$this->logLevel = \Config::QUERY_LOG_LEVEL;
	}


	public function append( \zephyros\LogEntry $entry ) {
		$details = sprintf('%s. STACK-TRACE: %s', $entry->details,  $entry->stackTraceText);
		$details .= sprintf(' - URL: %s', $entry->url );

		if ( $this->fp == null ) {
			$this->fp = fopen(\Config::LOGS_PATH.'/queries/'.@date('Y-m-d').'.log', 'a' );
			if ( $this->fp === false ) {
				return;
			}
			@chmod( \Config::LOGS_PATH.'/queries/'.@date('Y-m-d').'.log', 0666 );
		}

		fwrite( $this->fp, sprintf('%s. %s - %s', $entry->levelName, 'T '.$entry->title.'ms', $details ).PHP_EOL );
	}


	public function __destruct() {
		if ( $this->fp ) {
			fclose($this->fp);
		}
	}
}