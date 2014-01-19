<?php
namespace bits4breakfast\zephyros;

class LanguageManager {

	private static $instances = [];

	protected $db = null;
	
	protected $lang = 'EN';
	protected $cache = [];
	
	
	public function __construct( $lang = 'EN' ) {
		$this->db = \zephyros\Mysql::init();
		
		$this->lang = strtoupper($lang);
	}
	
	public static function init( $lang = 'EN' ) {
		
		if( !isset(self::$instances[$lang]) )
			self::$instances[$lang] = new \zephyros\LanguageManager($lang);
		
		return self::$instances[$lang];
	}
	
	public function setLanguage( $lang ) {
		$this->lang = strtoupper($lang);
	}
	
	public function __get( $key ) {
		return $this->$key;
	}
	
	public function get($code, $search = null, $replace = null) {
		if ( isset($_GET['_do_not_translate']) ) {
			return $code;
		}
		
		if ( isset($this->cache[$code]) ) {
			$text = $this->cache[$code];
		} else {
			$text = apc_fetch( 'lm:'.$this->lang.':'.$code );
			if ( false === $text ) {
				$text = $this->db->pick('setup')->result("SELECT IF(COUNT(*),text,'') FROM constants_translations LEFT JOIN constants ON constant_id=constants.id WHERE code='".$code."' AND lang='".$this->lang."'");
				
				if ( trim($text) != '' ) {
					apc_store( 'lm:'.$this->lang.':'.$code, $text );
				} else {
					$text = apc_fetch( 'lm:EN:'.$code );
					if ( false === $text ) {
						$text = $this->db->pick('setup')->result("SELECT IF(COUNT(*),text,'') FROM constants_translations LEFT JOIN constants ON constant_id=constants.id WHERE code='".$code."' AND lang='EN'");
						
						if ( trim($text) != '' ) {
							apc_store( 'lm:'.$this->lang.':'.$code, $text );
						} else {
							$text = $code;
						}
					} else {
						apc_store( 'lm:'.$this->lang.':'.$code, $text );
					}
				}
			}
			
			$this->cache[$code] = $text;
		}
		
		if ( $search != null && $replace != null ) {
			return str_replace($search,$replace,$text);
		}
		
		return $text;
	}
}

?>