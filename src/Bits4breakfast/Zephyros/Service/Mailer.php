<?php
namespace Bits4breakfast\Zephyros\Service;

use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceInterface;

class Mailer extends \PHPMailer implements ServiceInterface{

	protected $container = null;

	public $From = null;
	public $FromName = null;

	private $last_message_id = null;

	public function __construct(ServiceContainer $container) {
		$this->container = $container;

		parent::__construct(true);

		$config = $this->container->config();
		if ($config->is_dev()) {
			$this->IsMail();
		} else {
			$this->setSMTP(
				$config->get('mailer.smpt_auth'),
				$config->get('mailer.smpt_host'),
				$config->get('mailer.smpt_user'),
				$config->get('mailer.smpt_password'),
				$config->get('mailer.smpt_secure'),
				$config->get('mailer.smpt_port')
			);
		}
	}

	public function set_sender($address, $name) {
		$this->from([ 'email' => $address, 'name' => $name ]);
	}

	public function send() {
		if ($this->From == null || $this->FromName == null) {
			$this->from([
				'email' => $this->container->config()->get('mailer.default_sender_email'), 
				'name' => $this->container->config()->get('mailer.default_sender_name')
			]);
		}

		return parent::Send();
	}

	public function from($from) {
		if (is_array($from)) {
			$this->setFrom(
				self::cleanLine($from['email']),
				(isset($from['name']) ? self::cleanLine($from['name']) : '')
			);
		} elseif (is_string($from)) {
			$this->setFrom(self::cleanLine($from));
		} else {
			throw new \Exception('Invalid sender');
		}

		return $this;
	}

	public function subject($subject) {
		$this->Subject = self::cleanLine($subject);

		return $this;
	}

	public function message($content) {
		$this->Body = self::cleanText($content);

		return $this;
	}

	public function to($recipient) {
		$this->to = [];
		if (isset($recipient['name']) && isset($recipient['email'])) {
			$this->addAddress(self::cleanLine($recipient['email']), $recipient['name']);
		} else if (is_array($recipient)) {
			foreach ($recipient as $to) {
				if (isset($to['name']) && isset($to['email'])) {
					$this->addAddress(self::cleanLine($to['email']), $to['name']);
				} else {
					$this->addAddress(self::cleanLine($to));
				}
			}
		} else {
			$this->addAddress(self::cleanLine($recipient));
		}

		return $this;
	}

	public function toCC($cc) {
		$this->cc = [];
		
		if (isset($cc)) {
			if (is_array($cc)) {
				foreach ($cc as $to) {
					$this->addCC(self::cleanLine($to));
				}
			} else {
				$this->addCC(self::cleanLine($cc));
			}
		}

		return $this;
	}

	public function toBCC($bcc) {
		$this->bcc = [];

		if (is_array($bcc)) {
			foreach ($bcc as $to) {
				$this->addBCC(self::cleanLine($to));
			}
		} else {
			$this->addBCC(self::cleanLine($bcc));
		}

		return $this;
	}

	public function attach($attachment) {
		if (is_array($attachment)) {
			foreach ($attachment as $file) {
				$this->addAttachment($file);
			}
		} else {
			$this->addAttachment($attachment);
		}

		return $this;
	}

	public function replyTo($replyto) {
		if (is_array($replyto)) {
			foreach ($replyto as $to) {
				$this->addReplyTo(self::cleanLine($to['email']), self::cleanLine($to['name']));
			}
		} else {
			$this->addReplyTo(self::cleanLine($replyto['email']), self::cleanLine($replyto['name']));
		}

		return $this;
	}

	public function isHTML($ishtml = true) {
		parent::isHTML($ishtml);

		return $this;
	}

	public function add_custom_header($name, $value = null)
	{
		$this->addCustomHeader($name, $value);

		return $this;
	}

	private function setSMTP($auth = null, $host = null, $user = null, $pass = null, $secure = null, $port = 25) {
		$this->SMTPAuth = $auth;
		$this->Host = $host;
		$this->Username = $user;
		$this->Password = $pass;
		$this->Port = $port;
		$this->SMTPDebug = 2;
		$this->Debugoutput = $this;
		if ($secure == 'ssl' || $secure == 'tls') {
			$this->SMTPSecure = $secure;
		}

		if ($this->SMTPAuth !== null && $this->Host !== null && $this->Username !== null && $this->Password !== null) {
			$this->IsSMTP();
		}
	}

	public static function cleanLine($value)
	{
		return trim(preg_replace('/(%0A|%0D|\n+|\r+)/i', '', $value));
	}

	public static function cleanText($value)
	{
		return trim(preg_replace('/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i', '', $value));
	}

	public function last_message_id()
	{
		return $this->last_message_id;
	}

	public function __invoke($string)
	{
		if (strpos($string, 'SERVER -> CLIENT: 250 Ok') !== false) {
			$string = str_replace(["\n", 'SERVER -> CLIENT: 250 Ok'], '', $string);
			if (trim($string) != '') {
				$this->last_message_id = trim($string);
			}
		}
	}
}