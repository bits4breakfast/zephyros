<?php
namespace bits4breakfast\zephyros\logger;

use bits4breakfast\zephyros\Email;

class EmailAppender extends AppenderBase {

	public function __construct() {
		$this->logLevel = \Config::EMAIL_LOG_LEVEL;
	}

	public function append( LogEntry $entry ) {
		if (empty(\Config::$errorsNotificationEmail)) {
			return;
		}

		$mailer = new Email();

		foreach (\Config::$errorsNotificationEmail as $recipient) {
			$mailer->to($recipient);
		}

		$stackTraceText = str_replace(PHP_EOL, '<br/>', $entry->stackTraceText);

		$env = getenv('ENVIRONMENT');
		$subject = sprintf('Epoque %s ALERT (%s).', isset($env) ? '(' . $env . ')' : '', $entry->levelName);

		$body = '<table border="1" width="100%">';
		$body .= sprintf('<tr><td>TITLE:</td><td>%s</td></tr>', $entry->title);
		$body .= sprintf('<tr><td>TIMESTAMP:</td><td>%s</td></tr>', @date('Y-m-d H:m:s'));
		$body .= sprintf('<tr><td>LEVEL:</td><td>%s</td></tr>', $entry->levelName);
		$body .= sprintf('<tr><td>MESSAGE:</td><td>%s</td></tr>', isset($entry->details) ? $entry->details : '&nbsp;');
		$body .= sprintf('<tr><td>STACKTRACE:</td><td>%s</td></tr>', isset($stackTraceText) ? $stackTraceText : '&nbsp;');
		$body .= sprintf('<tr><td>URL:</td><td>%s</td></tr>', isset($entry->url) ? $entry->url : '&nbsp;');
		$body .= sprintf('<tr><td>METHOD:</td><td>%s</td></tr>', isset($entry->requestMethod) ? $entry->requestMethod : '&nbsp;');
		$body .= sprintf('<tr><td>QUERY:</td><td>%s</td></tr>', isset($entry->queryString) ? $entry->queryString : '&nbsp;');

		$body .= sprintf('<tr><td>AGENT:</td><td>%s</td></tr>', isset($entry->agent) ? $entry->agent : '&nbsp;');
		$body .= sprintf('<tr><td>REFERRER:</td><td>%s</td></tr>', isset($entry->referrer) ? $entry->referrer : '&nbsp;');
		$body .= sprintf('<tr><td>EXCEPTION TYPE:</td><td>%s</td></tr>', isset($entry->exception_type) ? $entry->exception_type : '&nbsp;');
		$body .= '</table>';

		$mailer->subject($subject);
		$mailer->message($body);
		$mailer->isHTML(true);

		$mailer->Send();
	}
}