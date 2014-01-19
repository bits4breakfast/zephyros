<?php
namespace bits4breakfast\zephyros;

abstract class UserInterface {

	protected $allow_caching = false;

	protected $smarty = null;
	
	protected $l = null;
	protected $p = null;
	protected $user = null;
	protected $config = array();
	protected $data = array();

	protected $metaTags = array();
	protected $opengraph = array();
	protected $stylesheets = array();
	protected $scripts = array();
	protected $templates = array();

	protected $mobile = false;
	
	public function __construct() {
		$this->smarty = self::init_smarty();
	}
	
	final public static function init_smarty() {
		$smarty = new \Smarty;
		$smarty->setTemplateDir( 
			array(
				\Config::BASE_PATH.'/application/templates/'.\Config::SUBDOMAIN,
				\Config::BASE_PATH.'/application/templates/shared'
			) 
		)
		->setCompileDir( \Config::CACHE_PATH.'/smarty' )
		->setCacheDir( \Config::CACHE_PATH.'/smarty' )
		->compile_check = \Config::SMARTY_COMPILE_CHECK;
		return $smarty;
	}
	
	final public function setRoute( \zephyros\RouteParameters $p ) {
		$this->p = $p;
		$this->smarty->assign( 'p', $p );
	}
	
	final public function setLanguageManager( \ehbox\core\LanguageManager $l ) {
		$this->l = $l;
		$this->smarty->assign( 'l', $l );
		$this->data['lang'] = $l->lang;
	}

	final public function setUser( $user ) {
		$this->user = $user;
		$this->smarty->assign( 'user', $user );
		if ( $user ) {
			$this->data['user_id'] = (int) $user->id;
			$this->data['username'] = $user->username;
		}
	}

	final public function set($key, $value) {
		$this->smarty->assign($key, $value);
	}
	
	final public function metatag($name, $content) {
		$this->metaTags[] = array( 'name' => $name, 'content' => $content );
	}

	final public function opengraph( $key, $value ) {
		$this->opengraph[] = array( 'key' => 'og:'.$key, 'value' => $value );	
	}

	final public function css( $path ) {
		$this->stylesheets[] = \Config::ASSETS_CDN_URL . $path;
	}

	final public function js( $path, $domain = NULL ) {
		if(!isset($domain)) {
			$domain = \Config::ASSETS_CDN_URL;
		}
		$this->scripts[] = $domain . $path;
	}

	final public function tpl( $path ) {
		$this->templates[] = $path;
	}
	
	final public function allow_caching() {
		$this->allow_caching = true;
	}
	
	final public function is_cached() {
		return \zephyros\Cache::exists( $this->cache_key() );
	}
	
	public function cache_key() {
		return 'pages:'.sha1( $_SERVER["REQUEST_URI"] );
	}
	
	public function output() {
		if ( $this->allow_caching ) {
			$key = $this->cache_key();
		
			$output = \zephyros\Cache::get( $key );
			
			if ( $output === false ) {
				$this->build();
				
				$this->smarty->assign( 'data', $this->data );
				$this->smarty->assign( 'config', $this->config );
				$this->smarty->assign( 'metaTags', $this->metaTags );
				$this->smarty->assign( 'opengraph', $this->opengraph );
				$this->smarty->assign( 'stylesheets', $this->stylesheets );
				$this->smarty->assign( 'javascripts', $this->scripts );
				
				$output = "";
				foreach ( $this->templates as $template ) {
					$output .= $this->smarty->fetch( $template );
				}
				\zephyros\Cache::set( $key, $output );
			}
			
			print $output;
			
		} else {
			$this->build();
			
			$this->smarty->assign( 'data', $this->data );
			$this->smarty->assign( 'config', $this->config );
			$this->smarty->assign( 'metaTags', $this->metaTags );
			$this->smarty->assign( 'opengraph', $this->opengraph );
			$this->smarty->assign( 'stylesheets', $this->stylesheets );
			$this->smarty->assign( 'javascripts', $this->scripts );
			
			foreach ( $this->templates as $template ) {
				$this->smarty->display( $template );
			}
		}
	}
}
?>