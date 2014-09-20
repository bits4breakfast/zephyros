<?php
namespace Bits4breakfast\Zephyros\Service;

use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceInterface;

class Mailer extends \PHPMailer implements ServiceInterface{

	protected $container = null;

	public $From = null;
	public $FromName = null;

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
		if ( is_array($from) ) {
			$this->From = self::cleanLine($from['email']);
			if ( isset($from['name']) ) {
				$this->FromName = self::cleanLine($from['name']);
			}
		} elseif (is_string($from)) {
			$this->From = self::cleanLine($from);
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
		if (is_array($recipient)) {
			foreach ($recipient as $to) {
				$this->AddAddress( self::cleanLine($to) );
			}
		} else {
			$this->AddAddress( self::cleanLine($recipient) );
		}

		return $this;
	}

	public function toCC($cc) {
		if (isset($cc)) {
			if (is_array($cc)) {
				foreach ($cc as $to) {
					parent::AddCC( self::cleanLine($to) );
				}
			} else {
				parent::AddCC( self::cleanLine($cc) );
			}
		}

		return $this;
	}

	public function toBCC($bcc) {
		if (is_array($bcc)) {
			foreach ($bcc as $to) {
				parent::AddBCC( self::cleanLine($to) );
			}
		} else {
			parent::AddBCC( self::cleanLine($bcc) );
		}

		return $this;
	}

	public function attach($attachment) {
		if (is_array($attachment)) {
			foreach ($attachment as $file) {
				parent::AddAttachment($file);
			}
		} else {
			parent::AddAttachment($attachment);
		}

		return $this;
	}

	public function replyTo($replyto) {
		if (is_array($replyto)) {
			foreach ($replyto as $to) {
				parent::AddReplyTo( self::cleanLine($to['email']), self::cleanLine($to['name']) );
			}
		} else {
			parent::AddReplyTo( self::cleanLine($replyto['email']), self::cleanLine($replyto['name']) );
		}

		return $this;
	}

	public function isHTML($ishtml = true) {
		parent::IsHTML($ishtml);

		return $this;
	}

	private function setSMTP($auth = null, $host = null, $user = null, $pass = null, $secure = null, $port = 25) {
		$this->SMTPAuth = $auth;
		$this->Host = $host;
		$this->Username = $user;
		$this->Password = $pass;
		$this->Port = $port;
		if ( $secure == 'ssl' || $secure == 'tls' ) {
			$this->SMTPSecure = $secure;
		}

		if ( $this->SMTPAuth !== null && $this->Host !== null && $this->Username !== null && $this->Password !== null ) {
			$this->IsSMTP();
		}
	}

	public static function cleanLine($value) {
		return trim(preg_replace('/(%0A|%0D|\n+|\r+)/i', '', $value));
	}

	public static function cleanText($value) {
		return trim(preg_replace('/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i', '', $value));
	}
}