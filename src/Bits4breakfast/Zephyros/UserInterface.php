<?php
namespace Bits4breakfast\Zephyros;

abstract class UserInterface {

	protected $allow_caching = false;

	protected $smarty = null;
	
	protected $container = null;
	protected $route = null;
	protected $user = null;
	protected $data = [];

	protected $metaTags = [];
	protected $opengraph = [];
	protected $stylesheets = [];
	protected $scripts = [];
	protected $templates = [];

	final private function init_smarty() {
		$config = $this->container->config();
		$folder = implode(DIRECTORY_SEPARATOR, explode('\\', $this->config->get('kernel.namespace')));

		$smarty = new \Smarty;
		$smarty->setTemplateDir( 
			array(
				$config->app_base_path.'/src/'.$folder.'/Template/'.$config->subdomain,
				$config->app_base_path.'/src/'.$folder.'/Template/shared'
			) 
		)
		->setCompileDir( $config->get('smarty.cache_path') )
		->setCacheDir( $config->get('smarty.cache_path') )
		->compile_check = $config->get('smarty.compile_check');
		return $smarty;
	}
	
	final public function set_route( Route $route ) {
		$this->route = $route;
		$this->smarty->assign( 'route', $route );
	}

	final public function set_container( ServiceContainer $container ) {
		$this->set_language_manager( $container->lm() );
		$this->init_smarty();
	}
	
	final private function set_language_manager( LanguageManager $l ) {
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
			$domain = $this->container->config()->get('assets.cdn_url');
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
				$this->smarty->assign( 'config', $this->container->config()->dump() );
				$this->smarty->assign( 'metaTags', $this->metaTags );
				$this->smarty->assign( 'opengraph', $this->opengraph );
				$this->smarty->assign( 'stylesheets', $this->stylesheets );
				$this->smarty->assign( 'javascripts', $this->scripts );
				
				$output = "";
				foreach ( $this->templates as $template ) {
					$output .= $this->smarty->fetch( $template );
				}
				Cache::set( $key, $output );
			}
			
			print $output;
			
		} else {
			$this->build();
			
			$this->smarty->assign( 'data', $this->data );
			$this->smarty->assign( 'config', $this->container->config()->dump() );
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