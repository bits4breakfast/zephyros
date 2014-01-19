<?php
class LanguageManager {

	private static $singletonInstances = array();	///< Array of LanguageManager Resources. Singleton instance pointers

	protected $db = null;
	protected $language = 'EN';
	
	
	public function __construct( $lang = 'EN' ) {
		$this->db = Mysql::init();
		$this->language = strtoupper( $lang );
	}
	
	public static function init( $lang = 'EN' ) {
		if( !isset(self::$singletonInstances[$lang]) )
			self::$singletonInstances[$lang] = new LanguageManager( $lang );
		
		return self::$singletonInstances[$lang];
	}
	
	public function setLanguage( $lang ) {
		$this->language = strtoupper( $lang );
	}
	
	public function language() {
		return $this->language;
	}
	
	public function __get( $key ) {
		if ( $key == 'language' ) {
			return $this->language;
		} else {
			return $this->get( $key );
		}
	}
	
	public function get($code, $search = null, $replace = null) {
		
		$dir = '/cache/constants/';
		
		# if is cached in current language
		if( is_file($dir.$this->language.'/'.$code) ) {
			$text = file_get_contents($dir.$this->language.'/'.$code);
		} else {
			# if exists in current language, load and cache
			$text = $this->db->result("SELECT IF(COUNT(*),text,'') FROM locale.translations LEFT JOIN locale.languages ON language_id=languages.id LEFT JOIN locale.constants ON constant_id=constants.id WHERE constant='$code' AND language='$this->language'");
			
			if ( $text != '' ) {
				file_put_contents($dir.$this->language.'/'.$code, $text);
			} else if ( is_file($dir.'EN/'.$code) ) {
				$text = file_get_contents($dir.'EN/'.$code);
			} else {
				$text = $this->db->result("SELECT IF(COUNT(*),text,'') FROM locale.translations LEFT JOIN locale.languages ON language_id=languages.id LEFT JOIN locale.constants ON constant_id=constants.id WHERE constant='$code' AND language='EN'");
				
				if ( $text != '' ) {
					file_put_contents($dir.'EN/'.$code, $text);
				} else {
					$text = $code;
				}
			}
		}
		
		if ( $search != null && $replace != null ) {
			return str_replace($search,$replace,$text);
		}
		
		return $text;
	}
	
	public function js ( $string ) {
		return str_replace(array("\n", "\r"), '', str_replace("'","\'", $this->get($string)));
	}
}

?>