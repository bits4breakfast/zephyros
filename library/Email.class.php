<?php
include_once(BaseConfig::BASE_PATH.'/library/Replica.class.php');
require_once(BaseConfig::BASE_PATH.'/library/LanguageManager.class.php');

class Email {
	
	protected $l = null;
	
	protected $boundary;
	protected $textBoundary;
	
	protected $to;
	protected $subject;
	protected $message;
	protected $header;
	
	protected $attachments = array();
	
	
	public function __construct() {
		$baseDate = date('Ymd-His-');
		$this->boundary = 'ehbox_boundary_'.$baseDate.str_replace(' ','.',microtime());
		$this->textBoundary = 'ehbox_mime_boundary_'.$baseDate.str_replace(' ','.',microtime());
		
		$this->header = "MIME-Version: 1.0\n";
		$this->header .= "Content-Type: multipart/mixed; boundary=\"$this->boundary\"\n";
		$this->header .= "X-Mailer: EHBOX Mailing System (0.2)\n";
	}
	
	public function subject() {
		return $this->subject;
	}
	
	protected function plain($html) {
		$plain = str_replace("\n", '', $html);
		$plain = preg_replace('/<br\s?\/?>/i', "\n", $plain);
		#$plain = preg_replace('/<\/div>[\s\t\n]*<div/i', "\n<p", $plain);
		$plain = str_replace('<div', "\n<div", $plain);
		$plain = str_replace('</div>', "\n</div>", $plain);
		$plain = preg_replace('/<img ([^>]+)?alt="([^"]+)"\s?([^>]+)?\s?\/>/i', '\2', $plain);
		$plain = preg_replace('/<a ([^>]+)?href="([^"]+)"\s?([^>]+)?\s?>([^-]+)<\/a>/i', '\4 [\2]', $plain);
		$plain = preg_replace('/<[^>]+>/i', '', $plain);
		$plain = str_replace("\t", '', $plain);
		return $plain;
	}
	
	protected function attachFile($filename) {
		$tmp = explode('/', $filename);
		$name = $tmp[count($tmp)-1];
		
		$text = "--$this->boundary\n";
		#$text .= "Content-Type: ".mime_content_type($filename)."; name=\"$name\"\n";
		$text .= "Content-Transfer-Encoding: base64\n";
		$text .= "Content-Disposition: attachment; filename=\"$name\"\n\n";
		
		$file = fopen($filename,'rb');
		$data = fread($file, filesize($filename));
		fclose($file);
		
		$text .= chunk_split(base64_encode($data),70)."\n\n";
		
 		return wordwrap($text, 70);
	}
	
	protected function createMessage($html) {
		$message = "This is a multi-part message in MIME format.\n\n";

		$message .= "--$this->boundary\n";
		$message .= "Content-Type: multipart/alternative; boundary=\"$this->textBoundary\"\n\n\n";

		$message .= "--$this->textBoundary\n";
		$message .= "Content-Type: text/plain; charset=utf-8\n";
		$message .= "Content-Transfer-Encoding: 7bit\n\n";
		$message .= wordwrap($this->plain($html), 70)."\n\n";
		
		$message .= "--$this->textBoundary\n";
		$message .= "Content-Type: text/html; charset=utf-8\n";
		$message .= "Content-Transfer-Encoding: 7bit\n\n";
		$message .= wordwrap($html, 70)."\n\n";
		
		$message .= "--$this->textBoundary--\n";
		
		foreach($this->attachments as $file)
			$message .= $this->attachFile($file);
		
		$message .= "--$this->boundary--\n";
		
		return $message;
	}
	
	protected function prettyAmount( $amount ) {
		return number_format($amount,2,".",",");
	}
	
	public function formatDate($date,$shortMonth=false,$shortWeekDayName=true,$longWeekDayName=false,$includeYear = true) {
		list ($y,$m,$d) = explode("-",$date);
		$m = (int) $m;
		$d = (int) $d;
		$output = "";
		$timestamp = mktime(6,0,0,$m,$d,$y);
		if ( $shortWeekDayName || $longWeekDayName ) {
			if ( $shortWeekDayName ) {
				$output .= $this->l->get("GENERAL_DAY_SHORT_".date("w",$timestamp)).", ";
			} else {
				$output .= $this->l->get("GENERAL_DAY_LONG_".date("w",$timestamp)).", ";
			}
		}
		if ( $shortMonth ) {
			$month = $this->l->get("GENERAL_MONTH_SHORT_".$m);
		} else {
			$month = $this->l->get("GENERAL_MONTH_LONG_".$m);
		}
		if ( $this->l->lang == "EN" ) {
			$output .= $month." ".$d.date("S",$timestamp);
		} else {
			$output .= $d." ".$month;
		}
		if ( $includeYear ) {
			if ( $this->l->lang == "EN" ) {
				$output .= ", ";
			} else {
				$output .= " ";
			}
			$output .= $y;
		}
		return $output;
	}
	
	public function formatShortDate($date,$includeYear=true) {
		list ($y,$m,$d) = explode("-",$date);
		$m = (int) $m;
		$d = (int) $d;
		$output = "";
		if ( $this->l->lang == "EN" ) {
			$output .= $m."/".$d;
		} else {
			$output .= $d."/".$m;
		}
		if ( $includeYear ) {
			$output .= "/".$y;
		}
		return $output;
	}
	
	public function send() {
		$code = md5(microtime());
		//file_put_contents('/var/www/cache/emails2/'.$code, $this->to."\n\n".$this->subject."\n\n".$this->header."\n\n".$this->message);
		$sent = mail($this->to, $this->subject, $this->message, $this->header, "-f bookings@ehbox.com");
		
		return $sent;
	}
}

?>