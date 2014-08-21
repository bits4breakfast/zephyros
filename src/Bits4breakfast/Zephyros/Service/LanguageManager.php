<?php
namespace Bits4breakfast\Zephyros\Service;

use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceInterface;

class LanguageManager implements ServiceInterface {

	protected $container = null;
	protected $db = null;
	
	protected $lang = 'EN';
	protected $cache = [];
	
	public function __construct(ServiceContainer $container) {
		$this->container = $container;
		$this->db = $container->db();
	}
	
	public function set_language( $lang ) {
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
			$text = $this->container->cache()->get( 'lm:'.$this->lang.':'.$code );
			if ( false === $text ) {
				$text = $this->db->pick('setup')->result("SELECT IF(COUNT(*),text,'') FROM constants_translations LEFT JOIN constants ON constant_id=constants.id WHERE code='".$code."' AND lang='".$this->lang."'");
				
				if ( trim($text) != '' ) {
					$this->container->cache()->set( 'lm:'.$this->lang.':'.$code, $text );
				} else {
					$text = $this->container->cache()->get( 'lm:EN:'.$code );
					if ( false === $text ) {
						$text = $this->db->pick('setup')->result("SELECT IF(COUNT(*),text,'') FROM constants_translations LEFT JOIN constants ON constant_id=constants.id WHERE code='".$code."' AND lang='EN'");
						
						if ( trim($text) != '' ) {
							$this->container->cache()->set( 'lm:'.$this->lang.':'.$code, $text );
						} else {
							$text = $code;
						}
					} else {
						$this->container->cache()->set( 'lm:'.$this->lang.':'.$code, $text );
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