<?php
namespace bits4breakfast\zephyros;

use bits4breakfast\zephyros\logger\LogEntry,
	bits4breakfast\zephyros\logger\StandardLogAppender,
	bits4breakfast\zephyros\logger\CustomLogAppender,
	bits4breakfast\zephyros\logger\EmailAppender;

class Logger {

	const VERBOSE = 0;
	const INFO = 10;
	const WARNING = 20;
	const ERROR = 30;
	const CRITICAL = 40;
	const NONE = 100;

	public static $entries = [];
	private static $appenders = null;
	private static $query_appender = null;

	public static function log($level, $title, $details = '') {
		$entry = new LogEntry($level, $title, $details);

		Logger::append($entry);
	}


	public static function exception($e) {
		$entry = new LogEntry(Logger::ERROR, $e->getMessage(), $e->getTraceAsString());
		$entry->exception_type = get_class($e);

		Logger::append($entry);
	}

	public static function query($executionTime, $query) {
		if ($executionTime > \Config::MYSQL_SLOWQUERY_TRESHOLD)
			$entry = new LogEntry(Logger::WARNING, sprintf("Slow query (%sms) detected.", $executionTime), $query);
		else
			$entry = new LogEntry(Logger::INFO, sprintf("Query executed (%sms).", $executionTime), $query);

		self::init_appenders();
		self::$query_appender->log($entry);
	}

	private static function append($entry) {
		foreach (self::init_appenders() as $appender) {
			$appender->log($entry);
		}
	}

	public static function level_name($level) {
		switch ($level) {
		case Logger::VERBOSE:
			return "VERBOSE";
		case Logger::INFO:
			return 'INFO';
		case Logger::WARNING:
			return 'WARNING';
		case Logger::ERROR:
			return 'ERROR';
		case Logger::CRITICAL:
			return 'CRITICAL';
		case Logger::NONE:
			return 'NONE';
		}
	}

	private static function init_appenders() {
		if (self::$appenders == null) {
			self::$appenders = array(
				new \zephyros\logger\StandardLogAppender(),
				new \zephyros\logger\CustomLogAppender(),
				new \zephyros\logger\EmailAppender()
			);

			self::$query_appender = new \zephyros\logger\QueryLogAppender();
		}

		return self::$appenders;
	}
}