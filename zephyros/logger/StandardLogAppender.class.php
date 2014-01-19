<?php
namespace bits4breakfast\zephyros\logger;

class StandardLogAppender extends AppenderBase {
	public function append(LogEntry $entry) {
		$details = sprintf('%s. STACK-TRACE: %s', $entry->details,  $entry->stack_trace_text);
		$details .= sprintf(' - URL: %s', $entry->url );

		error_log( sprintf('%s. %s - %s', $entry->levelName, $entry->title, $details), 0 );
	}
}
?>