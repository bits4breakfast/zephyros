<?php
namespace Bits4breakfast\Zephyros;

abstract class UserInterface {

	protected $allow_caching = false;

	protected $smarty = null;
	
	protected $l = null;
	protected $p = null;
	protected $user = null;
	protected $config =null;
	protected $data = [];

	protected $metaTags = [];
	protected $opengraph = [];
	protected $stylesheets = [];
	protected $scripts = [];
	protected $templates = [];

	protected $mobile = false;
	
	public function __construct() {
		$this->smarty = self::init_smarty();
	}
	
	final public static function init_smarty() {
		$smarty = new \Smarty;
		$smarty->setTemplateDir( 
			array(
				$this->config->app_base_path.'/src/'.implode(DIRECTORY_SEPARATOR, explode('\\', $this->config->get('kernel.namespace'))).'/'.$this->config->subdomain,
				$this->config->app_base_path.'/src/templates/shared'
			) 
		)
		->setCompileDir( $this->config->get('smarty.cache_path') )
		->setCacheDir( $this->config->get('smarty.cache_path') )
		->compile_check = $this->config->get('smarty.compile_check');
		return $smarty;
	}
	
	final public function set_route( Route $p ) {
		$this->p = $p;
		$this->smarty->assign( 'p', $p );
	}
	
	final public function set_language_manager( LanguageManager $l ) {
		$this->l = $l;
		$this->smarty->assign( 'l', $l );
		$this->data['lang'] = $l->lang;
	}

	final public function set_user( $user = null ) {
		$this->user = $user;
		$this->smarty->assign( 'user', $user );
		if ( $user ) {
			$this->data['user_id'] = (int) $user->id;
			$this->data['username'] = $user->username;
		}
	}

	final public function set_config( Config $config ) {
		$this->config = $config;
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
		return Cache::exists( $this->cache_key() );
	}
	
	public function cache_key() {
		return 'pages:'.sha1( $_SERVER["REQUEST_URI"] );
	}
	
	public function output() {
		if ( $this->allow_caching ) {
			$key = $this->cache_key();
		
			$output = Cache::get( $key );
			
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