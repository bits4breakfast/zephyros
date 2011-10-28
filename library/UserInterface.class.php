<?php
include_once(LIB.'/core/Replica.class.php');
include_once(LIB.'/bl/webservices/cloudfiles/cloudfiles.php');
abstract class UserInterface {

	protected $parameters = array();
	protected $mobile = false;
	protected $db = null;
	protected $l = null;

	public function header( $title = "", $css = null, $javascript = null, $bodyOptions = "", $IESpecificCss = '' ) {
		
		$output = '<!DOCTYPE html><html><head>';
		if ( $this->mobile ) {
			$output .= '<meta name="apple-touch-fullscreen" content="YES"><meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">';
		}
		$output .= '<title>' . $title . '</title>';
		
		$builtCssFile = $this->build( Config::SUBDOMAIN, 'css', $css );
		
		if ( $builtCssFile == '' ) {
			foreach ( (array)$css as $file ) {
				$output .=	$this->includecss($file);
			}
		} else {
			$output .= $this->includecss($builtCssFile,true);
		}
		
		if ( trim($IESpecificCss) != '' ) {
			$output .= $IESpecificCss;
		}
		
		$output .= '<script>var '.BaseConfig::JS_PARAMETERS_PREFIX.' = ' . json_encode( array('p' => $this->parameters) ) . ';</script>';
		
		$buildList = array();
		foreach ( (array)$javascript as $script ) {
			if ( strpos($script,'localize') !== false  ) {
				$output .=	$this->includejs( $script );
			} else {
				$buildList[] = $script;
			}
		}
		
		$builtJsFile = $this->build( Config::SUBDOMAIN, 'js', $buildList );
		
		if ( $builtJsFile == '' ) {
			foreach ( (array)$javascript as $script ) {
				$output .=	$this->includejs($script);
			}
		} else {
			$output .= $this->includejs($builtJsFile,true);
		}
		
		$output .= '</head><body'.(trim($bodyOptions) == "" ? "":" ".$bodyOptions).'>';
		
		return $output;
	}
	
	final public function build( $subdomain, $extension = 'js', $files = null ) {

		if ( !empty($files) ) {
			if ( is_string($files) ) {
				$fileName = $files;
				$files = array( $fileName );
			} else {
				$fileName = implode('|',$files);
			}
			$fileName = $subdomain.'_'.( $this->mobile && $extension == 'css' ?'m':'').md5( $subdomain . '/' . $fileName . sha1( $subdomain . '/' . $fileName ) ).'.'.$extension;
			
			if ( !file_exists( BaseConfig::BUILD_PATH.'/'.$fileName ) ) {
				foreach ( $files as $file ) {
					if ( strpos($file,'localize')===false ) {
						if ( $extension == 'css' ) {
							$file = ($this->mobile?'m/':'').$file;
						}
						file_put_contents( BaseConfig::BUILD_PATH.'/'.$fileName, file_get_contents(BaseConfig::STATIC_FOLDER.'/'.(strpos($file,'shared/')!==false?'':Config::SUBDOMAIN.'/'.$extension.'/').$file), FILE_APPEND );
					}
				}
				if ( PROD_ENVIRONMENT ) {
					exec('java -jar '.BaseConfig::BASE_PATH.'/library/yuicompressor-2.4.6.jar '.BaseConfig::BUILD_PATH.'/'.$fileName.' -o '.BaseConfig::BUILD_PATH.'/'.$fileName);
					if ( BaseConfig::USE_CACHE_REPLICATION ) {
						HttpReplicationClient::send( BaseConfig::BUILD_PATH.'/'.$fileName );
					}
					
					if ( CloudFilesConfig::USERNAME != '' && CloudFilesConfig::API_KEY != '' ) {
						try {
							$auth = new CF_Authentication( CloudFilesConfig::USERNAME, CloudFilesConfig::API_KEY );
							$auth->authenticate();
							$conn = new CF_Connection($auth);
							$container = $conn->get_container( CloudFilesConfig::BUILD_CONTAINER );
							
							$object = @$container->create_object($fileName);
							$object->content_type = ($extension == 'js'?'application/x-javascript':'text/css');
							$object->load_from_filename( BaseConfig::BUILD_PATH.'/'.$fileName );
							$object->purge_from_cdn();
						} catch ( Exception $e ) {
							return '';
						}
					}
				}
			}
			
			return $fileName;
			
		} else {
			return '';
		}
	}
	
	final public function includejs( $file, $build = false ) {
		if ( $build ) {
			return '<script src="'.BaseConfig::BUILD_CDN_CONTAINER_PATH.'/'.$file.'" type="text/javascript"></script>';
		} else {
			return '<script src="'.BaseConfig::COOKIELESS_DOMAIN.'/'.(strpos($file,'shared/')!==false?'':Config::$folder.'/js/').$file.'" type="text/javascript"></script>';
		}
	}
	
	final public function includecss( $file, $build = false, $media = "all" ) {
		if ( $build ) {
			return '<link rel="stylesheet" href="'.BaseConfig::BUILD_CDN_CONTAINER.'/'.$file.'" media="'.$media.'"/>';
		} else {
			return '<link rel="stylesheet" href="'.BaseConfig::COOKIELESS_DOMAIN.'/'.(strpos($file,'shared/')!==false?'':Config::SUBDOMAIN.'/css/'.( $this->mobile ? 'm_' : '' )).$file.'" media="'.$media.'"/>';
		}
	}
	
	public static function session() {
		return session_name().'='.session_id();
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
	
	public function formatDate( $date, $shortMonth = false, $shortWeekDayName = true, $longWeekDayName = false, $includeYear = true ) {
		list ($y,$m,$d) = explode("-",$date);
		$output = "";
		$timestamp = strtotime($date);
		if ( $shortWeekDayName || $longWeekDayName ) {
			if ( $shortWeekDayName ) {
				$output .= $this->l->get("GENERAL_DAY_SHORT_".date("w",$timestamp)).", ";
			} else {
				$output .= $this->l->get("GENERAL_DAY_LONG_".date("w",$timestamp)).", ";
			}
		}
		if ( $shortMonth ) {
			$month = $this->l->get("GENERAL_MONTH_SHORT_".((int)$m));
		} else {
			$month = $this->l->get("GENERAL_MONTH_LONG_".((int)$m));
		}
		if ( $this->l->lang == "EN" ) {
			$output .= $month." ".$d.date("S",$timestamp);
		} else {
			$output .= ((int)$d)." ".$month;
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
	
	public function formatShortDate( $date, $includeYear = true ) {
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
}
?>