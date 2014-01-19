<?php
/*~ class.phpmailer.php
.---------------------------------------------------------------------------.
|  Software: PHPMailer - PHP email class                                    |
|   Version: 5.1                                                            |
|   Contact: via sourceforge.net support pages (also www.worxware.com)      |
|      Info: http://phpmailer.sourceforge.net                               |
|   Support: http://sourceforge.net/projects/phpmailer/                     |
| ------------------------------------------------------------------------- |
|     Admin: Andy Prevost (project admininistrator)                         |
|   Authors: Andy Prevost (codeworxtech) codeworxtech@users.sourceforge.net |
|          : Marcus Bointon (coolbru) coolbru@users.sourceforge.net         |
|   Founder: Brent R. Matzelle (original founder)                           |
| Copyright (c) 2004-2009, Andy Prevost. All Rights Reserved.               |
| Copyright (c) 2001-2003, Brent R. Matzelle                                |
| ------------------------------------------------------------------------- |
|   License: Distributed under the Lesser General Public License (LGPL)     |
|            http://www.gnu.org/copyleft/lesser.html                        |
| This program is distributed in the hope that it will be useful - WITHOUT  |
| ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or     |
| FITNESS FOR A PARTICULAR PURPOSE.                                         |
| ------------------------------------------------------------------------- |
| We offer a number of paid services (www.worxware.com):                    |
| - Web Hosting on highly optimized fast and secure servers                 |
| - Technology Consulting                                                   |
| - Oursourcing (highly qualified programmers and graphic designers)        |
'---------------------------------------------------------------------------'
*/

/**
 * PHPMailer - PHP email transport class
 * NOTE: Requires PHP version 5 or later
 * @package PHPMailer
 * @author Andy Prevost
 * @author Marcus Bointon
 * @copyright 2004 - 2009 Andy Prevost
 * @version $Id: class.phpmailer.php 447 2009-05-25 01:36:38Z codeworxtech $
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

class PHPMailer {

	public $Priority          = 3;
	public $CharSet           = 'UTF-8';
	public $ContentType       = 'text/plain';

	/**
	 * Sets the Encoding of the message. Options for this are
	 *  "8bit", "7bit", "binary", "base64", and "quoted-printable".
	 * @var string
	 */
	public $Encoding          = '8bit';
	public $ErrorInfo         = '';
	public $From              = 'root@localhost';
	public $FromName          = 'Root User';
	public $Sender            = '';
	public $Subject           = '';
	public $Body              = '';
	public $AltBody           = '';
	public $WordWrap          = 0;
	public $Mailer            = 'mail';
	public $Sendmail          = '/usr/sbin/sendmail';
	public $PluginDir         = '';
	public $ConfirmReadingTo  = '';
	public $Hostname          = '';
	public $MessageID         = '';

	/////////////////////////////////////////////////
	// PROPERTIES FOR SMTP
	/////////////////////////////////////////////////

	/**
	 * Sets the SMTP hosts.  All hosts must be separated by a
	 * semicolon.  You can also specify a different port
	 * for each host by using this format: [hostname:port]
	 * (e.g. "smtp1.example.com:25;smtp2.example.com").
	 * Hosts will be tried in order.
	 * @var string
	 */
	public $Host          = 'localhost';
	public $Port          = 25;

	/**
	 * Sets the SMTP HELO of the message (Default is $Hostname).
	 * @var string
	 */
	public $Helo          = '';

	/**
	 * Sets connection prefix.
	 * Options are "", "ssl" or "tls"
	 * @var string
	 */
	public $SMTPSecure    = '';
	public $SMTPAuth      = false;
	public $Username      = '';
	public $Password      = '';
	public $Timeout       = 10;
	public $SMTPDebug     = false;
	public $SMTPKeepAlive = false;
	/**
	 * Provides the ability to have the TO field process individual
	 * emails, instead of sending to entire TO addresses
	 * @var bool
	 */
	public $SingleTo      = false;
	/**
	 * If SingleTo is true, this provides the array to hold the email addresses
	 * @var bool
	 */
	public $SingleToArray = array();

	public $LE              = "\n";

	/**
	 * Used with DKIM DNS Resource Record
	 * @var string
	 */
	public $DKIM_selector   = 'phpmailer';

	/**
	 * Used with DKIM DNS Resource Record
	 * optional, in format of email address 'you@yourdomain.com'
	 * @var string
	 */
	public $DKIM_identity   = '';

	/**
	 * Used with DKIM DNS Resource Record
	 * optional, in format of email address 'you@yourdomain.com'
	 * @var string
	 */
	public $DKIM_domain     = '';

	/**
	 * Used with DKIM DNS Resource Record
	 * optional, in format of email address 'you@yourdomain.com'
	 * @var string
	 */
	public $DKIM_private    = '';

	public $action_function = '';

	public $Version         = '5.1';

	private   $smtp           = NULL;
	private   $to             = array();
	private   $cc             = array();
	private   $bcc            = array();
	private   $ReplyTo        = array();
	private   $all_recipients = array();
	private   $attachment     = array();
	private   $CustomHeader   = array();
	private   $message_type   = '';
	private   $boundary       = array();
	protected $language       = array();
	private   $error_count    = 0;
	private   $sign_cert_file = "";
	private   $sign_key_file  = "";
	private   $sign_key_pass  = "";
	private   $exceptions     = false;

	const STOP_MESSAGE  = 0; // message only, continue processing
	const STOP_CONTINUE = 1; // message?, likely ok to continue processing
	const STOP_CRITICAL = 2; // message, plus full stop, critical error reached

	public function __construct($exceptions = false) {
		$this->exceptions = ($exceptions == true);
	}

	public function IsHTML($ishtml = true) {
		if ($ishtml) {
			$this->ContentType = 'text/html';
		} else {
			$this->ContentType = 'text/plain';
		}
	}

	public function IsSMTP() {
		$this->Mailer = 'smtp';
	}

	public function IsMail() {
		$this->Mailer = 'mail';
	}

	public function IsSendmail() {
		if (!stristr(ini_get('sendmail_path'), 'sendmail')) {
			$this->Sendmail = '/var/qmail/bin/sendmail';
		}
		$this->Mailer = 'sendmail';
	}

	public function IsQmail() {
		if (stristr(ini_get('sendmail_path'), 'qmail')) {
			$this->Sendmail = '/var/qmail/bin/sendmail';
		}
		$this->Mailer = 'sendmail';
	}

	public function AddAddress($address, $name = '') {
		return $this->AddAnAddress('to', $address, $name);
	}

	public function AddCC($address, $name = '') {
		return $this->AddAnAddress('cc', $address, $name);
	}

	public function AddBCC($address, $name = '') {
		return $this->AddAnAddress('bcc', $address, $name);
	}

	public function AddReplyTo($address, $name = '') {
		return $this->AddAnAddress('ReplyTo', $address, $name);
	}

	private function AddAnAddress($kind, $address, $name = '') {
		if (!preg_match('/^(to|cc|bcc|ReplyTo)$/', $kind)) {
			echo 'Invalid recipient array: ' . kind;
			return false;
		}
		$address = trim($address);
		$name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
		if (!self::validateAddress($address)) {
			$this->SetError('invalid_address: '. $address);
			if ($this->exceptions) {
				throw new PHPMailerException('invalid_address: '.$address);
			}
			echo 'invalid_address: '.$address;
			return false;
		}
		if ($kind != 'ReplyTo') {
			if (!isset($this->all_recipients[strtolower($address)])) {
				array_push($this->$kind, array($address, $name));
				$this->all_recipients[strtolower($address)] = true;
				return true;
			}
		} else {
			if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
				$this->ReplyTo[strtolower($address)] = array($address, $name);
				return true;
			}
		}
		return false;
	}

	public function SetFrom($address, $name = '', $auto=1) {
		$address = trim($address);
		$name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
		if (!self::validateAddress($address)) {
			$this->SetError('invalid_address: '. $address);
			if ($this->exceptions) {
				throw new PHPMailerException('invalid_address: '.$address);
			}
			echo 'invalid_address: '.$address;
			return false;
		}
		$this->From = $address;
		$this->FromName = $name;
		if ($auto) {
			if (empty($this->ReplyTo)) {
				$this->AddAnAddress('ReplyTo', $address, $name);
			}
			if (empty($this->Sender)) {
				$this->Sender = $address;
			}
		}
		return true;
	}

	public static function validateAddress($address) {
		if ( filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE ) {
			return false;
		} else {
			return true;
		}
	}

	public function Send() {
		try {
			if ( empty($this->to) && empty($this->cc) && empty($this->bcc) ) {
				throw new PHPMailerException('provide_address', self::STOP_CRITICAL);
			}

			if (!empty($this->AltBody)) {
				$this->ContentType = 'multipart/alternative';
			}

			$this->error_count = 0;
			$this->SetMessageType();
			$header = $this->CreateHeader();
			$body = $this->CreateBody();

			if (empty($this->Body)) {
				throw new PHPMailerException('empty_message', self::STOP_CRITICAL);
			}

			if ($this->DKIM_domain && $this->DKIM_private) {
				$header_dkim = $this->DKIM_Add($header, $this->Subject, $body);
				$header = str_replace("\r\n", "\n", $header_dkim) . $header;
			}

			switch ($this->Mailer) {
				case 'sendmail':
					return $this->SendmailSend($header, $body);
				case 'smtp':
					return $this->SmtpSend($header, $body);
				default:
					return $this->MailSend($header, $body);
			}

		} catch ( PHPMailerException $e ) {
			$this->SetError($e->getMessage());
			if ($this->exceptions) {
				throw $e;
			}
			echo $e->getMessage()."\n";
			return false;
		}
	}
	
	protected function SendmailSend($header, $body) {
		if ($this->Sender != '') {
			$sendmail = sprintf("%s -oi -f %s -t", escapeshellcmd($this->Sendmail), escapeshellarg($this->Sender));
		} else {
			$sendmail = sprintf("%s -oi -t", escapeshellcmd($this->Sendmail));
		}
		if ($this->SingleTo === true) {
			foreach ($this->SingleToArray as $key => $val) {
				if (!@$mail = popen($sendmail, 'w')) {
					throw new PHPMailerException('execute' . $this->Sendmail, self::STOP_CRITICAL);
				}
				fputs($mail, "To: " . $val . "\n");
				fputs($mail, $header);
				fputs($mail, $body);
				$result = pclose($mail);
				$isSent = ($result == 0) ? 1 : 0;
				$this->doCallback($isSent, $val, $this->cc, $this->bcc, $this->Subject, $body);
				if ($result != 0) {
					throw new PHPMailerException('execute' . $this->Sendmail, self::STOP_CRITICAL);
				}
			}
		} else {
			if (!@$mail = popen($sendmail, 'w')) {
				throw new PHPMailerException('execute' . $this->Sendmail, self::STOP_CRITICAL);
			}
			fputs($mail, $header);
			fputs($mail, $body);
			$result = pclose($mail);
			$isSent = ($result == 0) ? 1 : 0;
			$this->doCallback($isSent, $this->to, $this->cc, $this->bcc, $this->Subject, $body);
			if ($result != 0) {
				throw new PHPMailerException('execute' . $this->Sendmail, self::STOP_CRITICAL);
			}
		}
		return true;
	}

	protected function MailSend($header, $body) {
		$toArr = array();
		foreach ($this->to as $t) {
			$toArr[] = $this->AddrFormat($t);
		}
		$to = implode(', ', $toArr);

		$params = sprintf("-oi -f %s", $this->Sender);
		if ($this->Sender != '' && strlen(ini_get('safe_mode'))< 1) {
			$old_from = ini_get('sendmail_from');
			ini_set('sendmail_from', $this->Sender);
			if ($this->SingleTo === true && count($toArr) > 1) {
				foreach ($toArr as $key => $val) {
					$rt = mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
					$isSent = ($rt == 1) ? 1 : 0;
					$this->doCallback($isSent, $val, $this->cc, $this->bcc, $this->Subject, $body);
				}
			} else {
				$rt = mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
				$isSent = ($rt == 1) ? 1 : 0;
				$this->doCallback($isSent, $to, $this->cc, $this->bcc, $this->Subject, $body);
			}
		} else {
			if ($this->SingleTo === true && count($toArr) > 1) {
				foreach ($toArr as $key => $val) {
					$rt = mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
					$isSent = ($rt == 1) ? 1 : 0;
					$this->doCallback($isSent, $val, $this->cc, $this->bcc, $this->Subject, $body);
				}
			} else {
				$rt = mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header);
				$isSent = ($rt == 1) ? 1 : 0;
				$this->doCallback($isSent, $to, $this->cc, $this->bcc, $this->Subject, $body);
			}
		}
		if (isset($old_from)) {
			ini_set('sendmail_from', $old_from);
		}
		
		if (!$rt) {
			throw new PHPMailerException('instantiate', self::STOP_CRITICAL);
		}
		return true;
	}

	protected function SmtpSend($header, $body) {
		include_once 'SMTP.class.php';
		$bad_rcpt = array();

		if (!$this->SmtpConnect()) {
			throw new PHPMailerException('smtp_connect_failed', self::STOP_CRITICAL);
		}
		$smtp_from = ($this->Sender == '') ? $this->From : $this->Sender;
		if (!$this->smtp->Mail($smtp_from)) {
			throw new PHPMailerException('from_failed' . $smtp_from, self::STOP_CRITICAL);
		}

		foreach ($this->to as $to) {
			if (!$this->smtp->Recipient($to[0])) {
				$bad_rcpt[] = $to[0];
				$isSent = 0;
				$this->doCallback($isSent, $to[0], '', '', $this->Subject, $body);
			} else {
				$isSent = 1;
				$this->doCallback($isSent, $to[0], '', '', $this->Subject, $body);
			}
		}
		foreach ($this->cc as $cc) {
			if (!$this->smtp->Recipient($cc[0])) {
				$bad_rcpt[] = $cc[0];
				$isSent = 0;
				$this->doCallback($isSent, '', $cc[0], '', $this->Subject, $body);
			} else {
				$isSent = 1;
				$this->doCallback($isSent, '', $cc[0], '', $this->Subject, $body);
			}
		}
		foreach ($this->bcc as $bcc) {
			if (!$this->smtp->Recipient($bcc[0])) {
				$bad_rcpt[] = $bcc[0];
				$isSent = 0;
				$this->doCallback($isSent, '', '', $bcc[0], $this->Subject, $body);
			} else {
				$isSent = 1;
				$this->doCallback($isSent, '', '', $bcc[0], $this->Subject, $body);
			}
		}


		if ( !empty($bad_rcpt) ) {
			$badaddresses = implode(', ', $bad_rcpt);
			throw new PHPMailerException('recipients_failed' . $badaddresses);
		}
		if (!$this->smtp->Data($header . $body)) {
			throw new PHPMailerException('data_not_accepted', self::STOP_CRITICAL);
		}
		if ($this->SMTPKeepAlive == true) {
			$this->smtp->Reset();
		}
		return true;
	}

	public function SmtpConnect() {
		if (is_null($this->smtp)) {
			$this->smtp = new SMTP();
		}

		$this->smtp->do_debug = $this->SMTPDebug;
		$hosts = explode(';', $this->Host);
		$index = 0;
		$connection = $this->smtp->Connected();

		try {
			while ($index < count($hosts) && !$connection) {
				$hostinfo = array();
				if (preg_match('/^(.+):([0-9]+)$/', $hosts[$index], $hostinfo)) {
					$host = $hostinfo[1];
					$port = $hostinfo[2];
				} else {
					$host = $hosts[$index];
					$port = $this->Port;
				}

				$tls = ($this->SMTPSecure == 'tls');
				$ssl = ($this->SMTPSecure == 'ssl');

				if ($this->smtp->Connect(($ssl ? 'ssl://':'').$host, $port, $this->Timeout)) {

					$hello = ($this->Helo != '' ? $this->Helo : $this->ServerHostname());
					$this->smtp->Hello($hello);

					if ($tls) {
						if (!$this->smtp->StartTLS()) {
							throw new PHPMailerException('tls');
						}

						$this->smtp->Hello($hello);
					}

					$connection = true;
					if ($this->SMTPAuth) {
						if (!$this->smtp->Authenticate($this->Username, $this->Password)) {
							throw new PHPMailerException('authenticate');
						}
					}
				}
				$index++;
				if (!$connection) {
					throw new PHPMailerException('connect_host');
				}
			}
		} catch (PHPMailerException $e) {
			$this->smtp->Reset();
			throw $e;
		}
		return true;
	}

	public function SmtpClose() {
		if (!is_null($this->smtp)) {
			if ($this->smtp->Connected()) {
				$this->smtp->Quit();
				$this->smtp->Close();
			}
		}
	}

	public function AddrAppend($type, $addr) {
		$addr_str = $type . ': ';
		$addresses = array();
		foreach ($addr as $a) {
			$addresses[] = $this->AddrFormat($a);
		}
		$addr_str .= implode(', ', $addresses);
		$addr_str .= $this->LE;

		return $addr_str;
	}

	public function AddrFormat($addr) {
		if (empty($addr[1])) {
			return $this->SecureHeader($addr[0]);
		} else {
			return $this->EncodeHeader($this->SecureHeader($addr[1]), 'phrase') . " <" . $this->SecureHeader($addr[0]) . ">";
		}
	}

	public function WrapText($message, $length, $qp_mode = false) {
		$soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;
		$is_utf8 = (strtolower($this->CharSet) == "utf-8");

		$message = $this->FixEOL($message);
		if (substr($message, -1) == $this->LE) {
			$message = substr($message, 0, -1);
		}

		$lines = explode($this->LE, $message);
		$message = '';
		foreach ( $lines as $line ) {
			$line_part = explode(' ', $line );
			$buf = '';
			for ($e = 0, $line_parts_len = count($line_part); $e < $line_parts_len; $e++) {
				$word = $line_part[$e];
				if ($qp_mode and (strlen($word) > $length)) {
					$space_left = $length - strlen($buf) - 1;
					if ($e != 0) {
						if ($space_left > 20) {
							$len = $space_left;
							if ($is_utf8) {
								$len = $this->UTF8CharBoundary($word, $len);
							} elseif (substr($word, $len - 1, 1) == "=") {
								$len--;
							} elseif (substr($word, $len - 2, 1) == "=") {
								$len -= 2;
							}
							$part = substr($word, 0, $len);
							$word = substr($word, $len);
							$buf .= ' ' . $part;
							$message .= $buf . sprintf("=%s", $this->LE);
						} else {
							$message .= $buf . $soft_break;
						}
						$buf = '';
					}
					while (strlen($word) > 0) {
						$len = $length;
						if ($is_utf8) {
							$len = $this->UTF8CharBoundary($word, $len);
						} elseif (substr($word, $len - 1, 1) == "=") {
							$len--;
						} elseif (substr($word, $len - 2, 1) == "=") {
							$len -= 2;
						}
						$part = substr($word, 0, $len);
						$word = substr($word, $len);

						if (strlen($word) > 0) {
							$message .= $part . sprintf("=%s", $this->LE);
						} else {
							$buf = $part;
						}
					}
				} else {
					$buf_o = $buf;
					$buf .= ($e == 0) ? $word : (' ' . $word);

					if (strlen($buf) > $length and $buf_o != '') {
						$message .= $buf_o . $soft_break;
						$buf = $word;
					}
				}
			}
			$message .= $buf . $this->LE;
		}

		return $message;
	}

	public function UTF8CharBoundary($encodedText, $maxLength) {
		$foundSplitPos = false;
		$lookBack = 3;
		while (!$foundSplitPos) {
			$lastChunk = substr($encodedText, $maxLength - $lookBack, $lookBack);
			$encodedCharPos = strpos($lastChunk, "=");
			if ($encodedCharPos !== false) {
				$hex = substr($encodedText, $maxLength - $lookBack + $encodedCharPos + 1, 2);
				$dec = hexdec($hex);
				if ($dec < 128) {
					$maxLength = ($encodedCharPos == 0) ? $maxLength :
					$maxLength - ($lookBack - $encodedCharPos);
					$foundSplitPos = true;
				} elseif ($dec >= 192) {
					$maxLength = $maxLength - ($lookBack - $encodedCharPos);
					$foundSplitPos = true;
				} elseif ($dec < 192) {
					$lookBack += 3;
				}
			} else {
				$foundSplitPos = true;
			}
		}
		return $maxLength;
	}

	public function SetWordWrap() {
		if ($this->WordWrap < 1) {
			return;
		}

		switch ($this->message_type) {
			case 'alt':
			case 'alt_attachments':
				$this->AltBody = $this->WrapText($this->AltBody, $this->WordWrap);
				break;
			default:
				$this->Body = $this->WrapText($this->Body, $this->WordWrap);
				break;
		}
	}

	public function CreateHeader() {
		$result = '';

		$uniq_id = md5(uniqid(time()));
		$this->boundary[1] = 'b1_' . $uniq_id;
		$this->boundary[2] = 'b2_' . $uniq_id;

		$result .= $this->HeaderLine('Date', self::RFCDate());
		if ($this->Sender == '') {
			$result .= $this->HeaderLine('Return-Path', trim($this->From));
		} else {
			$result .= $this->HeaderLine('Return-Path', trim($this->Sender));
		}

		if ($this->Mailer != 'mail') {
			if ($this->SingleTo === true) {
				foreach ($this->to as $t) {
					$this->SingleToArray[] = $this->AddrFormat($t);
				}
			} else {
				if ( !empty($this->to) ) {
					$result .= $this->AddrAppend('To', $this->to);
				} elseif ( empty($this->cc) ) {
					$result .= $this->HeaderLine('To', 'undisclosed-recipients:;');
				}
			}
		}

		$from = array();
		$from[0][0] = trim($this->From);
		$from[0][1] = $this->FromName;
		$result .= $this->AddrAppend('From', $from);

		if ( !empty($this->cc) ) {
			$result .= $this->AddrAppend('Cc', $this->cc);
		}

		if ((($this->Mailer == 'sendmail') || ($this->Mailer == 'mail')) && ( !empty($this->bcc) )) {
			$result .= $this->AddrAppend('Bcc', $this->bcc);
		}

		if ( !empty($this->ReplyTo) ) {
			$result .= $this->AddrAppend('Reply-to', $this->ReplyTo);
		}

		if ($this->Mailer != 'mail') {
			$result .= $this->HeaderLine('Subject', $this->EncodeHeader($this->SecureHeader($this->Subject)));
		}

		if ($this->MessageID != '') {
			$result .= $this->HeaderLine('Message-ID', $this->MessageID);
		} else {
			$result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->ServerHostname(), $this->LE);
		}
		$result .= $this->HeaderLine('X-Priority', $this->Priority);
		$result .= $this->HeaderLine('X-Mailer', 'PHPMailer '.$this->Version.' (phpmailer.sourceforge.net)');

		if ($this->ConfirmReadingTo != '') {
			$result .= $this->HeaderLine('Disposition-Notification-To', '<' . trim($this->ConfirmReadingTo) . '>');
		}

		foreach ( $this->CustomHeader as $customerHeader ) {
			$result .= $this->HeaderLine(trim($customerHeader[0]), $this->EncodeHeader(trim($customerHeader[1])));
		}
		if (!$this->sign_key_file) {
			$result .= $this->HeaderLine('MIME-Version', '1.0');
			$result .= $this->GetMailMIME();
		}

		return $result;
	}

	public function GetMailMIME() {
		$result = '';
		switch ($this->message_type) {
			case 'plain':
				$result .= $this->HeaderLine('Content-Transfer-Encoding', $this->Encoding);
				$result .= sprintf("Content-Type: %s; charset=\"%s\"", $this->ContentType, $this->CharSet);
				break;
			case 'attachments':
			case 'alt_attachments':
				if ($this->InlineImageExists()) {
					$result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s", 'multipart/related', $this->LE, $this->LE, $this->boundary[1], $this->LE);
				} else {
					$result .= $this->HeaderLine('Content-Type', 'multipart/mixed;');
					$result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
				}
				break;
			case 'alt':
				$result .= $this->HeaderLine('Content-Type', 'multipart/alternative;');
				$result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
				break;
		}

		if ($this->Mailer != 'mail') {
			$result .= $this->LE.$this->LE;
		}

		return $result;
	}

	public function CreateBody() {
		$body = '';

		if ($this->sign_key_file) {
			$body .= $this->GetMailMIME();
		}

		$this->SetWordWrap();

		switch ($this->message_type) {
			case 'alt':
				$body .= $this->GetBoundary($this->boundary[1], '', 'text/plain', '');
				$body .= $this->EncodeString($this->AltBody, $this->Encoding);
				$body .= $this->LE.$this->LE;
				$body .= $this->GetBoundary($this->boundary[1], '', 'text/html', '');
				$body .= $this->EncodeString($this->Body, $this->Encoding);
				$body .= $this->LE.$this->LE;
				$body .= $this->EndBoundary($this->boundary[1]);
				break;
			case 'plain':
				$body .= $this->EncodeString($this->Body, $this->Encoding);
				break;
			case 'attachments':
				$body .= $this->GetBoundary($this->boundary[1], '', '', '');
				$body .= $this->EncodeString($this->Body, $this->Encoding);
				$body .= $this->LE;
				$body .= $this->AttachAll();
				break;
			case 'alt_attachments':
				$body .= sprintf("--%s%s", $this->boundary[1], $this->LE);
				$body .= sprintf("Content-Type: %s;%s" . "\tboundary=\"%s\"%s", 'multipart/alternative', $this->LE, $this->boundary[2], $this->LE.$this->LE);
				$body .= $this->GetBoundary($this->boundary[2], '', 'text/plain', '') . $this->LE;
				$body .= $this->EncodeString($this->AltBody, $this->Encoding);
				$body .= $this->LE.$this->LE;
				$body .= $this->GetBoundary($this->boundary[2], '', 'text/html', '') . $this->LE;
				$body .= $this->EncodeString($this->Body, $this->Encoding);
				$body .= $this->LE.$this->LE;
				$body .= $this->EndBoundary($this->boundary[2]);
				$body .= $this->AttachAll();
				break;
		}

		if ($this->IsError()) {
			$body = '';
		} elseif ($this->sign_key_file) {
			try {
				$file = tempnam('', 'mail');
				file_put_contents($file, $body);
				$signed = tempnam("", "signed");
				if (@openssl_pkcs7_sign($file, $signed, "file://".$this->sign_cert_file, array("file://".$this->sign_key_file, $this->sign_key_pass), NULL)) {
					@unlink($file);
					@unlink($signed);
					$body = file_get_contents($signed);
				} else {
					@unlink($file);
					@unlink($signed);
					throw new PHPMailerException("signing".openssl_error_string());
				}
			} catch (PHPMailerException $e) {
				$body = '';
				if ($this->exceptions) {
					throw $e;
				}
			}
		}

		return $body;
	}

	private function GetBoundary($boundary, $charSet, $contentType, $encoding) {
		$result = '';
		if ($charSet == '') {
			$charSet = $this->CharSet;
		}
		if ($contentType == '') {
			$contentType = $this->ContentType;
		}
		if ($encoding == '') {
			$encoding = $this->Encoding;
		}
		$result .= $this->TextLine('--' . $boundary);
		$result .= sprintf("Content-Type: %s; charset = \"%s\"", $contentType, $charSet);
		$result .= $this->LE;
		$result .= $this->HeaderLine('Content-Transfer-Encoding', $encoding);
		$result .= $this->LE;

		return $result;
	}

	private function EndBoundary($boundary) {
		return $this->LE . '--' . $boundary . '--' . $this->LE;
	}

	private function SetMessageType() {
		if ( empty($this->attachment) && strlen($this->AltBody) < 1) {
			$this->message_type = 'plain';
		} else {
			if ( !empty($this->attachment) ) {
				$this->message_type = 'attachments';
			}
			if (strlen($this->AltBody) > 0 && empty($this->attachment) ) {
				$this->message_type = 'alt';
			}
			if (strlen($this->AltBody) > 0 && !empty($this->attachment) ) {
				$this->message_type = 'alt_attachments';
			}
		}
	}

	public function HeaderLine($name, $value) {
		return $name . ': ' . $value . $this->LE;
	}

	public function TextLine($value) {
		return $value . $this->LE;
	}

	public function AddAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {
		try {
			if ( !@is_file($path) ) {
				throw new PHPMailerException('file_access' . $path, self::STOP_CONTINUE);
			}
			$filename = basename($path);
			if ( $name == '' ) {
				$name = $filename;
			}

			$this->attachment[] = array(
				0 => $path,
				1 => $filename,
				2 => $name,
				3 => $encoding,
				4 => $type,
				5 => false,  // isStringAttachment
				6 => 'attachment',
				7 => 0
			);

		} catch (PHPMailerException $e) {
			$this->SetError($e->getMessage());
			if ($this->exceptions) {
				throw $e;
			}
			echo $e->getMessage()."\n";
			if ( $e->getCode() == self::STOP_CRITICAL ) {
				return false;
			}
		}
		return true;
	}

	public function GetAttachments() {
		return $this->attachment;
	}

	private function AttachAll() {
		$mime = array();
		$cidUniq = array();
		$incl = array();

		foreach ($this->attachment as $attachment) {
			$bString = $attachment[5];
			if ($bString) {
				$string = $attachment[0];
			} else {
				$path = $attachment[0];
			}

			if (in_array($attachment[0], $incl)) { continue; }
			$filename    = $attachment[1];
			$name        = $attachment[2];
			$encoding    = $attachment[3];
			$type        = $attachment[4];
			$disposition = $attachment[6];
			$cid         = $attachment[7];
			$incl[]      = $attachment[0];
			if ( $disposition == 'inline' && isset($cidUniq[$cid]) ) { continue; }
			$cidUniq[$cid] = true;

			$mime[] = sprintf("--%s%s", $this->boundary[1], $this->LE);
			$mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $this->EncodeHeader($this->SecureHeader($name)), $this->LE);
			$mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->LE);

			if ($disposition == 'inline') {
				$mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->LE);
			}

			$mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s", $disposition, $this->EncodeHeader($this->SecureHeader($name)), $this->LE.$this->LE);

			if ($bString) {
				$mime[] = $this->EncodeString($string, $encoding);
				if ($this->IsError()) {
					return '';
				}
				$mime[] = $this->LE.$this->LE;
			} else {
				$mime[] = $this->EncodeFile($path, $encoding);
				if ($this->IsError()) {
					return '';
				}
				$mime[] = $this->LE.$this->LE;
			}
		}

		$mime[] = sprintf("--%s--%s", $this->boundary[1], $this->LE);

		return join('', $mime);
	}

	private function EncodeFile($path, $encoding = 'base64') {
		try {
			if (!is_readable($path)) {
				throw new PHPMailerException('file_open' . $path, self::STOP_CONTINUE);
			}
			if (function_exists('get_magic_quotes')) {
				function get_magic_quotes() {
					return false;
				}
			}
			if (PHP_VERSION < 6) {
				$magic_quotes = get_magic_quotes_runtime();
				set_magic_quotes_runtime(0);
			}
			$file_buffer  = file_get_contents($path);
			$file_buffer  = $this->EncodeString($file_buffer, $encoding);
			if (PHP_VERSION < 6) { set_magic_quotes_runtime($magic_quotes); }
			return $file_buffer;
		} catch (Exception $e) {
			$this->SetError($e->getMessage());
			return '';
		}
	}

	public function EncodeString($str, $encoding = 'base64') {
		$encoded = '';
		switch (strtolower($encoding)) {
			case 'base64':
				$encoded = chunk_split(base64_encode($str), 76, $this->LE);
				break;
			case '7bit':
			case '8bit':
				$encoded = $this->FixEOL($str);
				if (substr($encoded, -(strlen($this->LE))) != $this->LE)
					$encoded .= $this->LE;
				break;
			case 'binary':
				$encoded = $str;
				break;
			case 'quoted-printable':
				$encoded = $this->EncodeQP($str);
				break;
			default:
				$this->SetError('encoding' . $encoding);
				break;
		}
		return $encoded;
	}

	public function EncodeHeader($str, $position = 'text') {
		$x = 0;

		switch (strtolower($position)) {
			case 'phrase':
				if (!preg_match('/[\200-\377]/', $str)) {
					$encoded = addcslashes($str, "\0..\37\177\\\"");
					if (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
						return $encoded;
					} else {
						return "\"$encoded\"";
					}
				}
				$x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
				break;
			case 'comment':
				$x = preg_match_all('/[()"]/', $str, $matches);
			case 'text':
			default:
				$x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
				break;
		}

		if ($x == 0) {
			return $str;
		}

		$maxlen = 75 - 7 - strlen($this->CharSet);
		if (strlen($str)/3 < $x) {
			$encoding = 'B';
			if ( $this->HasMultiBytes($str)) {
				$encoded = $this->Base64EncodeWrapMB($str);
			} else {
				$encoded = base64_encode($str);
				$maxlen -= $maxlen % 4;
				$encoded = trim(chunk_split($encoded, $maxlen, "\n"));
			}
		} else {
			$encoding = 'Q';
			$encoded = $this->EncodeQ($str, $position);
			$encoded = $this->WrapText($encoded, $maxlen, true);
			$encoded = str_replace('='.$this->LE, "\n", trim($encoded));
		}

		$encoded = preg_replace('/^(.*)$/m', " =?".$this->CharSet."?$encoding?\\1?=", $encoded);
		$encoded = trim(str_replace("\n", $this->LE, $encoded));

		return $encoded;
	}

	public function HasMultiBytes($str) {
		return strlen($str) > mb_strlen($str, $this->CharSet);
	}

	public function Base64EncodeWrapMB($str) {
		$start = "=?".$this->CharSet."?B?";
		$end = "?=";
		$encoded = "";

		$mb_length = mb_strlen($str, $this->CharSet);
		$length = 75 - strlen($start) - strlen($end);
		$ratio = $mb_length / strlen($str);
		$offset = $avgLength = floor($length * $ratio * .75);

		for ($i = 0; $i < $mb_length; $i += $offset) {
			$lookBack = 0;

			do {
				$offset = $avgLength - $lookBack;
				$chunk = mb_substr($str, $i, $offset, $this->CharSet);
				$chunk = base64_encode($chunk);
				$lookBack++;
			}
			while (strlen($chunk) > $length);

			$encoded .= $chunk . $this->LE;
		}

		$encoded = substr($encoded, 0, -strlen($this->LE));
		return $encoded;
	}

	public function EncodeQPphp( $input = '', $line_max = 76, $space_conv = false) {
		$hex = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
		$lines = preg_split('/(?:\r\n|\r|\n)/', $input);
		$eol = "\r\n";
		$escape = '=';
		$output = '';
		while ( list(, $line) = each($lines) ) {
			$newline = '';
			for ($i = 0, $linlen = strlen($line); $i < $linlen; $i++) {
				$c = substr( $line, $i, 1 );
				$dec = ord( $c );
				if ( ( $i == 0 ) && ( $dec == 46 ) ) {
					$c = '=2E';
				}
				if ( $dec == 32 ) {
					if ( $i == ( $linlen - 1 ) ) {
						$c = '=20';
					} else if ( $space_conv ) {
							$c = '=20';
						}
				} elseif ( ($dec == 61) || ($dec < 32 ) || ($dec > 126) ) {
					$h2 = floor($dec/16);
					$h1 = floor($dec%16);
					$c = $escape.$hex[$h2].$hex[$h1];
				}
				if ( (strlen($newline) + strlen($c)) >= $line_max ) {
					$output .= $newline.$escape.$eol;
					$newline = '';
					if ( $dec == 46 ) {
						$c = '=2E';
					}
				}
				$newline .= $c;
			}
			$output .= $newline.$eol;
		}
		return $output;
	}

	public function EncodeQP($string, $line_max = 76, $space_conv = false) {
		return quoted_printable_encode($string);
	}

	public function EncodeQ($str, $position = 'text') {
		$encoded = preg_replace('/[\r\n]*/', '', $str);

		switch (strtolower($position)) {
			case 'phrase':
				$encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
				break;
			case 'comment':
				$encoded = preg_replace("/([\(\)\"])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
			case 'text':
			default:
				$encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e',
					"'='.sprintf('%02X', ord('\\1'))", $encoded);
				break;
		}

		$encoded = str_replace(' ', '_', $encoded);

		return $encoded;
	}

	public function AddStringAttachment($string, $filename, $encoding = 'base64', $type = 'application/octet-stream') {
		$this->attachment[] = array(
			0 => $string,
			1 => $filename,
			2 => basename($filename),
			3 => $encoding,
			4 => $type,
			5 => true,  // isStringAttachment
			6 => 'attachment',
			7 => 0
		);
	}

	public function AddEmbeddedImage($path, $cid, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {

		if ( !@is_file($path) ) {
			$this->SetError('file_access' . $path);
			return false;
		}

		$filename = basename($path);
		if ( $name == '' ) {
			$name = $filename;
		}

		$this->attachment[] = array(
			0 => $path,
			1 => $filename,
			2 => $name,
			3 => $encoding,
			4 => $type,
			5 => false,  // isStringAttachment
			6 => 'inline',
			7 => $cid
		);

		return true;
	}

	public function InlineImageExists() {
		foreach ($this->attachment as $attachment) {
			if ($attachment[6] == 'inline') {
				return true;
			}
		}
		return false;
	}

	public function ClearAddresses() {
		foreach ($this->to as $to) {
			unset($this->all_recipients[strtolower($to[0])]);
		}
		$this->to = array();
	}

	public function ClearCCs() {
		foreach ($this->cc as $cc) {
			unset($this->all_recipients[strtolower($cc[0])]);
		}
		$this->cc = array();
	}

	public function ClearBCCs() {
		foreach ($this->bcc as $bcc) {
			unset($this->all_recipients[strtolower($bcc[0])]);
		}
		$this->bcc = array();
	}

	public function ClearReplyTos() {
		$this->ReplyTo = array();
	}

	public function ClearAllRecipients() {
		$this->to = array();
		$this->cc = array();
		$this->bcc = array();
		$this->all_recipients = array();
	}

	public function ClearAttachments() {
		$this->attachment = array();
	}

	public function ClearCustomHeaders() {
		$this->CustomHeader = array();
	}

	protected function SetError($msg) {
		$this->error_count++;
		if ($this->Mailer == 'smtp' and !is_null($this->smtp)) {
			$lasterror = $this->smtp->getError();
			if (!empty($lasterror) and array_key_exists('smtp_msg', $lasterror)) {
				$msg .= '<p>' . 'smtp_error' . $lasterror['smtp_msg'] . "</p>\n";
			}
		}
		$this->ErrorInfo = $msg;
	}

	public static function RFCDate() {
		$tz = @date('Z');
		$tzs = ($tz < 0) ? '-' : '+';
		$tz = abs($tz);
		$tz = (int)($tz/3600)*100 + ($tz%3600)/60;
		$result = sprintf("%s %s%04d", @date('D, j M Y H:i:s'), $tzs, $tz);

		return $result;
	}

	private function ServerHostname() {
		if (!empty($this->Hostname)) {
			$result = $this->Hostname;
		} elseif (isset($_SERVER['SERVER_NAME'])) {
			$result = $_SERVER['SERVER_NAME'];
		} else {
			$result = 'localhost.localdomain';
		}

		return $result;
	}

	public function IsError() {
		return $this->error_count > 0;
	}

	private function FixEOL($str) {
		$str = str_replace("\r\n", "\n", $str);
		$str = str_replace("\r", "\n", $str);
		$str = str_replace("\n", $this->LE, $str);
		return $str;
	}

	public function AddCustomHeader($custom_header) {
		$this->CustomHeader[] = explode(':', $custom_header, 2);
	}

	public function MsgHTML($message, $basedir = '') {
		preg_match_all("/(src|background)=\"(.*)\"/Ui", $message, $images);
		if (isset($images[2])) {
			foreach ($images[2] as $i => $url) {
				if (!preg_match('#^[A-z]+://#', $url)) {
					$filename = basename($url);
					$directory = dirname($url);
					($directory == '.')?$directory='':'';
					$cid = 'cid:' . md5($filename);
					$ext = pathinfo($filename, PATHINFO_EXTENSION);
					$mimeType  = self::_mime_types($ext);
					if ( strlen($basedir) > 1 && substr($basedir, -1) != '/') { $basedir .= '/'; }
					if ( strlen($directory) > 1 && substr($directory, -1) != '/') { $directory .= '/'; }
					if ( $this->AddEmbeddedImage($basedir.$directory.$filename, md5($filename), $filename, 'base64', $mimeType) ) {
						$message = preg_replace("/".$images[1][$i]."=\"".preg_quote($url, '/')."\"/Ui", $images[1][$i]."=\"".$cid."\"", $message);
					}
				}
			}
		}
		$this->IsHTML(true);
		$this->Body = $message;
		$textMsg = trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/s', '', $message)));
		if (!empty($textMsg) && empty($this->AltBody)) {
			$this->AltBody = html_entity_decode($textMsg);
		}
		if (empty($this->AltBody)) {
			$this->AltBody = 'To view this email message, open it in a program that understands HTML!' . "\n\n";
		}
	}

	public static function _mime_types($ext = '') {
		$mimes = array(
			'hqx'   =>  'application/mac-binhex40',
			'cpt'   =>  'application/mac-compactpro',
			'doc'   =>  'application/msword',
			'bin'   =>  'application/macbinary',
			'dms'   =>  'application/octet-stream',
			'lha'   =>  'application/octet-stream',
			'lzh'   =>  'application/octet-stream',
			'exe'   =>  'application/octet-stream',
			'class' =>  'application/octet-stream',
			'psd'   =>  'application/octet-stream',
			'so'    =>  'application/octet-stream',
			'sea'   =>  'application/octet-stream',
			'dll'   =>  'application/octet-stream',
			'oda'   =>  'application/oda',
			'pdf'   =>  'application/pdf',
			'ai'    =>  'application/postscript',
			'eps'   =>  'application/postscript',
			'ps'    =>  'application/postscript',
			'smi'   =>  'application/smil',
			'smil'  =>  'application/smil',
			'mif'   =>  'application/vnd.mif',
			'xls'   =>  'application/vnd.ms-excel',
			'ppt'   =>  'application/vnd.ms-powerpoint',
			'wbxml' =>  'application/vnd.wap.wbxml',
			'wmlc'  =>  'application/vnd.wap.wmlc',
			'dcr'   =>  'application/x-director',
			'dir'   =>  'application/x-director',
			'dxr'   =>  'application/x-director',
			'dvi'   =>  'application/x-dvi',
			'gtar'  =>  'application/x-gtar',
			'php'   =>  'application/x-httpd-php',
			'php4'  =>  'application/x-httpd-php',
			'php3'  =>  'application/x-httpd-php',
			'phtml' =>  'application/x-httpd-php',
			'phps'  =>  'application/x-httpd-php-source',
			'js'    =>  'application/x-javascript',
			'swf'   =>  'application/x-shockwave-flash',
			'sit'   =>  'application/x-stuffit',
			'tar'   =>  'application/x-tar',
			'tgz'   =>  'application/x-tar',
			'xhtml' =>  'application/xhtml+xml',
			'xht'   =>  'application/xhtml+xml',
			'zip'   =>  'application/zip',
			'mid'   =>  'audio/midi',
			'midi'  =>  'audio/midi',
			'mpga'  =>  'audio/mpeg',
			'mp2'   =>  'audio/mpeg',
			'mp3'   =>  'audio/mpeg',
			'aif'   =>  'audio/x-aiff',
			'aiff'  =>  'audio/x-aiff',
			'aifc'  =>  'audio/x-aiff',
			'ram'   =>  'audio/x-pn-realaudio',
			'rm'    =>  'audio/x-pn-realaudio',
			'rpm'   =>  'audio/x-pn-realaudio-plugin',
			'ra'    =>  'audio/x-realaudio',
			'rv'    =>  'video/vnd.rn-realvideo',
			'wav'   =>  'audio/x-wav',
			'bmp'   =>  'image/bmp',
			'gif'   =>  'image/gif',
			'jpeg'  =>  'image/jpeg',
			'jpg'   =>  'image/jpeg',
			'jpe'   =>  'image/jpeg',
			'png'   =>  'image/png',
			'tiff'  =>  'image/tiff',
			'tif'   =>  'image/tiff',
			'css'   =>  'text/css',
			'html'  =>  'text/html',
			'htm'   =>  'text/html',
			'shtml' =>  'text/html',
			'txt'   =>  'text/plain',
			'text'  =>  'text/plain',
			'log'   =>  'text/plain',
			'rtx'   =>  'text/richtext',
			'rtf'   =>  'text/rtf',
			'xml'   =>  'text/xml',
			'xsl'   =>  'text/xml',
			'mpeg'  =>  'video/mpeg',
			'mpg'   =>  'video/mpeg',
			'mpe'   =>  'video/mpeg',
			'qt'    =>  'video/quicktime',
			'mov'   =>  'video/quicktime',
			'avi'   =>  'video/x-msvideo',
			'movie' =>  'video/x-sgi-movie',
			'doc'   =>  'application/msword',
			'word'  =>  'application/msword',
			'xl'    =>  'application/excel',
			'eml'   =>  'message/rfc822'
		);
		return (!isset($mimes[strtolower($ext)])) ? 'application/octet-stream' : $mimes[strtolower($ext)];
	}

	public function set($name, $value = '') {
		try {
			if (isset($this->$name) ) {
				$this->$name = $value;
			} else {
				throw new PHPMailerException('variable_set' . $name, self::STOP_CRITICAL);
			}
		} catch (Exception $e) {
			$this->SetError($e->getMessage());
			if ($e->getCode() == self::STOP_CRITICAL) {
				return false;
			}
		}
		return true;
	}

	public function SecureHeader($str) {
		$str = str_replace("\r", '', $str);
		$str = str_replace("\n", '', $str);
		return trim($str);
	}

	public function Sign($cert_filename, $key_filename, $key_pass) {
		$this->sign_cert_file = $cert_filename;
		$this->sign_key_file = $key_filename;
		$this->sign_key_pass = $key_pass;
	}

	public function DKIM_QP($txt) {
		$tmp="";
		$line="";
		for ($i=0;$i<strlen($txt);$i++) {
			$ord=ord($txt[$i]);
			if ( ((0x21 <= $ord) && ($ord <= 0x3A)) || $ord == 0x3C || ((0x3E <= $ord) && ($ord <= 0x7E)) ) {
				$line.=$txt[$i];
			} else {
				$line.="=".sprintf("%02X", $ord);
			}
		}
		return $line;
	}

	public function DKIM_Sign($s) {
		$privKeyStr = file_get_contents($this->DKIM_private);
		if ($this->DKIM_passphrase!='') {
			$privKey = openssl_pkey_get_private($privKeyStr, $this->DKIM_passphrase);
		} else {
			$privKey = $privKeyStr;
		}
		if (openssl_sign($s, $signature, $privKey)) {
			return base64_encode($signature);
		}
	}

	public function DKIM_HeaderC($s) {
		$s=preg_replace("/\r\n\s+/", " ", $s);
		$lines=explode("\r\n", $s);
		foreach ($lines as $key=>$line) {
			list($heading, $value)=explode(":", $line, 2);
			$heading=strtolower($heading);
			$value=preg_replace("/\s+/", " ", $value);
			$lines[$key]=$heading.":".trim($value);
		}
		$s=implode("\r\n", $lines);
		return $s;
	}
	
	public function DKIM_BodyC($body) {
		if ($body == '') return "\r\n";
		$body=str_replace("\r\n", "\n", $body);
		$body=str_replace("\n", "\r\n", $body);
		while (substr($body, strlen($body)-4, 4) == "\r\n\r\n") {
			$body=substr($body, 0, strlen($body)-2);
		}
		return $body;
	}

	public function DKIM_Add($headers_line, $subject, $body) {
		$DKIMsignatureType    = 'rsa-sha1';
		$DKIMcanonicalization = 'relaxed/simple';
		$DKIMquery            = 'dns/txt';
		$DKIMtime             = time();
		$subject_header       = "Subject: $subject";
		$headers              = explode("\r\n", $headers_line);
		foreach ($headers as $header) {
			if (strpos($header, 'From:') === 0) {
				$from_header=$header;
			} elseif (strpos($header, 'To:') === 0) {
				$to_header=$header;
			}
		}
		$from     = str_replace('|', '=7C', $this->DKIM_QP($from_header));
		$to       = str_replace('|', '=7C', $this->DKIM_QP($to_header));
		$subject  = str_replace('|', '=7C', $this->DKIM_QP($subject_header));
		$body     = $this->DKIM_BodyC($body);
		$DKIMlen  = strlen($body) ;
		$DKIMb64  = base64_encode(pack("H*", sha1($body))) ;
		$ident    = ($this->DKIM_identity == '')? '' : " i=" . $this->DKIM_identity . ";";
		$dkimhdrs = "DKIM-Signature: v=1; a=" . $DKIMsignatureType . "; q=" . $DKIMquery . "; l=" . $DKIMlen . "; s=" . $this->DKIM_selector . ";\r\n".
			"\tt=" . $DKIMtime . "; c=" . $DKIMcanonicalization . ";\r\n".
			"\th=From:To:Subject;\r\n".
			"\td=" . $this->DKIM_domain . ";" . $ident . "\r\n".
			"\tz=$from\r\n".
			"\t|$to\r\n".
			"\t|$subject;\r\n".
			"\tbh=" . $DKIMb64 . ";\r\n".
			"\tb=";
		$toSign   = $this->DKIM_HeaderC($from_header . "\r\n" . $to_header . "\r\n" . $subject_header . "\r\n" . $dkimhdrs);
		$signed   = $this->DKIM_Sign($toSign);
		return "X-PHPMAILER-DKIM: phpmailer.worxware.com\r\n".$dkimhdrs.$signed."\r\n";
	}

	protected function doCallback($isSent, $to, $cc, $bcc, $subject, $body) {
		if (!empty($this->action_function) && function_exists($this->action_function)) {
			$params = array($isSent, $to, $cc, $bcc, $subject, $body);
			call_user_func_array($this->action_function, $params);
		}
	}
}

class PHPMailerException extends Exception {
	public function errorMessage() {
		$errorMsg = '<strong>' . $this->getMessage() . "</strong><br />\n";
		return $errorMsg;
	}
}
?>