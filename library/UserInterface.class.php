<?php
abstract class UserInterface {

	protected $smarty = null;
	
	protected $l = null;
	protected $p = null;
	protected $user = null;

	protected $metaTags = array();
	protected $stylesheets = array();
	protected $scripts = array();
	protected $templates = array();

	protected $mobile = false;
	
	public function __construct() {
		$this->smarty = self::createSmarty();
	}
	
	final public static function createSmarty() {
		$smarty = new Smarty;
		$smarty->setTemplateDir( 
			array(
				Config::BASE_PATH.'/application/templates/'.Config::SUBDOMAIN,
				Config::BASE_PATH.'/application/templates/shared'
			) 
		)
		->setCompileDir( Config::CACHE_PATH.'/smarty' )
		->setCacheDir( Config::CACHE_PATH.'/smarty' )
		->compile_check = Config::SMARTY_COMPILE_CHECK;
		return $smarty;
	}
	
	final public function setRoute( RouteParameters $p ) {
		$this->p = $p;
		$this->smarty->assign( 'p', $p );
	}
	
	final public function setLanguageManager( LanguageManager $l ) {
		$this->l = $l;
		$this->smarty->assign( 'l', $l );
	}

	final public function setUser( $user ) {
		$this->user = $user;
		$this->smarty->assign( 'user', $user );
	}

	final public function assign($key, $value) {
		$this->smarty->assign($key, $value);
	}
	
	final public function metatag($name, $value, $content) {
		$this->metaTags[] = array( 'name' => $name, 'value' => $value, 'content' => $content );
	}

	final public function css( $path ) {
		$this->stylesheets[] = Config::ASSETS_CDN_URL . $path;
	}

	final public function js( $path, $domain = NULL ) {
		if(!isset($domain)) {
			$domain = Config::ASSETS_CDN_URL;
		}
		$this->scripts[] = $domain . $path;
	}

	final public function tpl( $path ) {
		$this->templates[] = $path;
	}
	
	final public function linkTo( $controller = null, $action = null, $id = null, $parameters = null ) {
		$path = ($this->mobile?'/m':'');
		if ( !empty($controller) ) {
			$path .= '/'.$controller;
			if ( !empty($action) ) {
				$path .= '/'.$action;
			}
			if ( !empty($id) ) {
				$path .= '/'.$id;
			}
		}
		if ( !empty($parameters) ) {
			$path .= '?'.http_build_query($parameters);
		}
		
		return $path;
	}
	
	public function output() {
		$this->build();
		
		$this->smarty->assign( 'metaTags', $this->metaTags );
		$this->smarty->assign( 'stylesheets', $this->stylesheets );
		$this->smarty->assign( 'javascripts', $this->scripts );

		foreach ( $this->templates as $template ) {
			$this->smarty->display( $template );
		}
	}
}
?>