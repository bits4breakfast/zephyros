<?php
class LanguageManager {

	private static $singletonInstances = array();	///< Array of LanguageManager Resources. Singleton instance pointers

	protected $db = null;
	
	protected $language = 'EN';
	
	
	public function __construct( $language = 'EN' ) {
		$this->db = Mysql::init();
		
		$this->language = strtoupper($language);
	}
	
	public static function create( $language = 'EN' ) {
		
		if( !isset(self::$singletonInstances[$lang]) )
			self::$singletonInstances[$lang] = new LanguageManager($language);
		
		return self::$singletonInstances[$lang];
	}
	
	public function setLanguage( $lang ) {
		$this->language = strtoupper($lang);
	}
	
	public function language() {
		return $this->language;
	}
	
	public function lang() {
		return $this->language;
	}
	
	public function __get( $key ) {
		if ( $key == "lang" ) {
			return $this->language;
		}
		return $this->$key;
	}
	
	public function get($code, $search = null, $replace = null) {
		
		$dir='/cache/constants/';
		
		# if is cached in current language
		if( is_file($dir.$this->language.'/'.$code) )
			$text = file_get_contents($dir.$this->language.'/'.$code);
		
		else {
			# if exists in current language, load and cache
			$text = $this->db->result("SELECT IF(COUNT(*),text,'') FROM locale.translations LEFT JOIN locale.languages ON language_id=languages.id LEFT JOIN locale.constants ON constant_id=constants.id WHERE constant='$code' AND language='$this->language'");
			
			if( $text!='' ) {
				# update db flagging as used this constant
				//$this->db->query("UPDATE locale.constants SET used=1 WHERE constant='$code' ");
				file_put_contents($dir.$this->language.'/'.$code, $text);
			}
			
			# if is cached in english
			else if( is_file($dir.'EN/'.$code) )
				$text = file_get_contents($dir.'EN/'.$code);
			
			else {
				# if exists in english, load and cache
				$text = $this->db->result("SELECT IF(COUNT(*),text,'') FROM locale.translations LEFT JOIN locale.languages ON language_id=languages.id LEFT JOIN locale.constants ON constant_id=constants.id WHERE constant='$code' AND language='EN'");
				
				if( $text!='' ) {
					# update db flagging as used this constant
					//$this->db->query("UPDATE locale.constants SET used=1 WHERE constant='$code' ");
					file_put_contents($dir.'EN/'.$code, $text);
				}
				
				# return the raw constant ad mark as missing
				//$this->db->query("INSERT INTO locale.missings (constant) VALUES ('$code') ON DUPLICATE KEY UPDATE constant=constant ");
				else
					$text = $code;
			}
		}
		
		# testing mode
		/*
if(0) { 
			if(0)
				$text = '<span title="'.str_replace('"', '&quot;', preg_replace('/<[^<>]+>/', ' ', $text)).'">'.preg_replace('/[a-zA-Zàèìòù]/', '_', $text).'</span>';
			else
				$text = preg_replace('/[a-zA-Zàèìòù]/', '_', $text);
		}
*/
		if ( $search != null && $replace != null ) {
			return str_replace($search,$replace,$text);
		}
		
		return $text;
	}
	
	public function saveTranslation($constant, $language, $translation) {
		
	}
	
	public function js($string) {
		return str_replace(array("\n", "\r"), '', str_replace("'","\'", $this->get($string)));
	}
}

?>