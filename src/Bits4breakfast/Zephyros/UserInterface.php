<?php
namespace Bits4breakfast\Zephyros;

abstract class UserInterface {

	protected $allow_caching = false;

	protected $smarty = null;
	
	protected $container = null;
	protected $route = null;
	protected $user = null;
	protected $data = [];

	protected $flash_message = null;

	protected $meta_tags = [];
	protected $opengraph = [];
	protected $stylesheets = [];
	protected $scripts = [];
	protected $templates = [];

	final public function init_smarty() {
		$config = $this->container->config();
		$folder = implode(DIRECTORY_SEPARATOR, explode('\\', $config->get('kernel_namespace')));

		$smarty = new \Smarty;
		$smarty->setTemplateDir( 
			[
				$config->app_base_path.'/src/'.$folder.'/Template/'.$config->subdomain,
				$config->app_base_path.'/src/'.$folder.'/Template'
			] 
		)
		->setCompileDir( $config->get('smarty_cache_path') )
		->setCacheDir( $config->get('smarty_cache_path') )
		->compile_check = $config->get('smarty_compile_check');
		return $smarty;
	}
	
	final public function set_route( Route $route ) {
		$this->route = $route;
		$this->smarty->assign('route', (array)$route);
	}

	final public function set_container( ServiceContainer $container ) {
		$this->container = $container;
		$this->smarty = $this->init_smarty();
		$this->set_language_manager( $container->lm() );
	}
	
	final private function set_language_manager( Service\LanguageManager $l ) {
		$this->l = $l;
		$this->smarty->assign('l', $l);
		$this->data['lang'] = $l->lang;
	}

	final public function set_user( $user = null ) {
		$this->user = $user;
		if ( $user ) {
			$this->data['user'] = $user->dump();
		} else {
			$this->data['user'] = null;
		}

		$this->smarty->assign( 'user', $this->data['user'] );
	}

	final public function set_flash_message($message) {
		$this->flash_message = $message;
	}

	final public function set($key, $value) {
		$this->smarty->assign($key, $value);
	}
	
	final public function metatag($name, $content) {
		$this->meta_tags[] = ['name' => $name, 'content' => $content];
	}

	final public function opengraph( $key, $value ) {
		$this->opengraph[] = ['key' => 'og:'.$key, 'value' => $value];	
	}

	final public function css( $path ) {
		$this->stylesheets[] = $this->container->config()->get('assets_cdn_url') . $path;
	}

	final public function js( $path, $remote = false ) {
		if($remote) {
			$this->scripts[] = $path;
		} else {
			$this->scripts[] = $this->container->config()->get('assets_cdn_url') . $path;
		}
	}

	final public function tpl( $path ) {
		$this->templates[] = $path;
	}
	
	final public function allow_caching() {
		$this->allow_caching = true;
	}

	final public function will_cache() {
		return $this->allow_caching;
	}
	
	final public function is_cached() {
		return Cache::exists( $this->cache_key() );
	}
	
	public function cache_key() {
		return 'pages:'.sha1( $_SERVER["REQUEST_URI"] );
	}

	final public function render() {
		$this->templates = [];
		$this->build();
				
		$this->smarty->assign( 'data', (array)$this->data );
		$this->smarty->assign( 'config', $this->container->config()->dump() );
		$this->smarty->assign( 'meta_tags', $this->meta_tags );
		$this->smarty->assign( 'opengraph', $this->opengraph );
		$this->smarty->assign( 'stylesheets', $this->stylesheets );
		$this->smarty->assign( 'javascripts', $this->scripts );
		if (!$this->allow_caching) {
			$this->smarty->assign( 'flash_message', $this->flash_message );
		}
		
		$output = "";
		foreach ( $this->templates as $template ) {
			$output .= $this->smarty->fetch( $template );
		}

		return $output;
	}
	
	final public function output() {
		if ($this->allow_caching) {
			$key = $this->cache_key();
		
			$output = Cache::get($key);
			if ( $output === false ) {
				$output = $this->render();
				Cache::set($key, $output);
			}
			
			print $output;
		} else {
			echo $this->render();
		}
	}
}