<?php
/*
$Id$
*/
include_once(LIB."/core/Mysql.class.php");
include_once(LIB."/core/LanguageManager.class.php");
class Controller {
	protected $db = null;
	protected $l = null;
	protected $p = null;
	protected $user = null;
	protected $response = null;
	
	const ERROR_NOT_IMPLEMENTED = 501;
	const ERROR_FORBIDDEN = 403;
	const ERROR_UNAUTHORIZED = 401;
	const ERROR_BAD_REQUEST = 400;
	const ERROR_NOT_FOUND = 404;
	
	public function __construct(RouteParameters $parameters) {
		$this->db = Mysql::create();

		$lang = "EN";
		if ( isset($_SESSION["_lang"]) ) {
			$lang = mysql_escape_string(strtoupper($_SESSION["_lang"]));
		} else if ( isset($_GET["lang"]) ) {
			$lang = mysql_escape_string(strtoupper($_GET["lang"]));
			$_SESSION["_lang"] = $lang;
		} else if ( isset($_POST["lang"]) ) {
			$lang = mysql_escape_string(strtoupper($_POST["lang"]));
			$_SESSION["_lang"] = $lang;
		}
		$this->l = new LanguageManager($lang);
		
		if ( $parameters->hasValidSession ) {
			if ( !isset(Config::$webservice) || ( isset(Config::$webservice) && !Config::$webservice) ) {
				include_once(LIB.'/model/'.Config::$loggedUserClass.'.class.php');
				$this->user = call_user_func( array(Config::$loggedUserClass,"create"), $_SESSION["userId"] );
			}
		}
		
		$this->p = $parameters;
		
		if ( $this->p->format == 'json' ) {
			header("Content-type: text/json");
		} else if ( $this->p->format == 'xml' ) {
			header("Content-type: text/xml");
		}
	}
	
	public function render() {
		$classMethods = get_class_methods($this);
		if ( in_array($this->p->action,$classMethods ) ) {
			$method = $this->p->action;
			$this->$method();
		} else if ( in_array("__default",$classMethods) ) {
			$this->__default();
		} else {
			$this->__toError(Controller::ERROR_NOT_IMPLEMENTED);
		}
	}
	
	protected function __default() {
		$classMethods = get_class_methods($this);
		
		if ( $this->p->action != '' && $this->p->id == '' ) {
			$this->p->id = $this->p->action;
			$this->p->action = '';
		}
		
		if ( $this->p->id === 0 || $this->p->id === '' ) {
			if ( $this->p->method == "GET" && in_array("index",$classMethods) ) {
				$this->index();
			} else if ( $this->p->method == 'PUT' && in_array("save",$classMethods) ) {
				$this->save();
			} else {
				$this->__toError(Controller::ERROR_NOT_IMPLEMENTED);
			}
		} else {
			if ( $this->p->method == "GET" && in_array("retrieve",$classMethods) ) {
				$this->retrieve();
			} else if ( $this->p->method == "DELETE" && in_array("delete",$classMethods) ) {
				$this->delete();
			} else if ( $this->p->method == 'POST' && in_array("save",$classMethods) ) {
				$this->save();
			} else {
				$this->__toError(Controller::ERROR_NOT_IMPLEMENTED);
			}
		}
	}
	
	protected function __toError($errorCode = 0) {
		if ( $errorCode == Controller::ERROR_NOT_IMPLEMENTED ) {
			header('HTTP/1.0 501 Not Implemented');
		} else if ( $errorCode == Controller::ERROR_UNAUTHORIZED ) {
			header('HTTP/1.0 401 Unauthorized');
		} else if ( $errorCode == Controller::ERROR_NOT_FOUND ) {
			header('HTTP/1.0 404 Not Found');
		} else if ( $errorCode == Controller::ERROR_FORBIDDEN ) {
			header('HTTP/1.0 403 Forbidden');
		} else {
			header('HTTP/1.0 400 Bad Request');
		}
		$this->response = array( "code" => $errorCode );
		exit();
	}
	
	protected function preventCaching() {
		header("ETag: PUB" . time());
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10) . " GMT");
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 5) . " GMT");
		header("Pragma: no-cache");
		header("Cache-Control: max-age=1, s-maxage=1, no-cache, must-revalidate");
	}
	
	public function redirectTo( $controller = null, $action = null, $id = null, $parameters = null ) {
		$path = ($this->p->mobile?'/m':'');
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
		
		header('Location: '.$path);
	}
	
	protected function logout() {
		if ( isset($_SESSION["userId"]) ) {
			unset($_SESSION["userId"]);
			return true;
		}
		return false;
	}
	
	// adapted from http://mavimo.org/varie/array_xml_php
	public static function toXml($data, $rootNodeName = 'data', &$xml = NULL) {
		if (is_null($xml)) {
			if ( Controller::isAssoc($data) ) {
				if ( substr($rootNodeName,-3) == 'ies' ) {
					$rootNodeName = substr($rootNodeName,0,-3).'y';
				} else if ( substr($rootNodeName,-1) == 's' ) {
					$rootNodeName = substr($rootNodeName,0,-1);
				} else {
					if ( count(array_keys($data)) == 1 ) {
						$keys = array_keys($data);
						if ( is_array($data[$keys[0]]) && !Controller::isAssoc($data[$keys[0]]) ) {
							$rootNodeName = $keys[0];
							$data = $data[$keys[0]];
						}
					}
				}
			}
			$xml = new SimpleXMLElement('<' . $rootNodeName . '/>');
		}
	
		// loop through the data passed in.
		foreach($data as $key => $value) {
			// if numeric key, assume array of rootNodeName elements
			if (is_numeric($key)) {
				if ( substr($rootNodeName,-3) == 'ies' ) {
					$key = substr($rootNodeName,0,-3).'y';
				} else if ( substr($rootNodeName,-1) == 's' ) {
					$key = substr($rootNodeName,0,-1);
				} else {
					$key = $rootNodeName;
				}
			}
			
			$attributes = array();
			
			if ( preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/",$key) > 0 ) {
				$attributes['value'] = $key;
				$key = 'date';
			}
			
			// delete any char not allowed in XML element names
			$key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);
	
			// if there is another array found recrusively call this function
			if (is_array($value)) {
				$node = $xml->addChild($key);
				if ( !empty($attributes) ) {
					foreach ( $attributes as $attributeKey => $attributeValue ) {
						$node->addAttribute($attributeKey, $attributeValue);
					}
				}
	
				// recrusive call - pass $key as the new rootNodeName
				Controller::toXml($value, $key, $node);
			} else {
				// add single node.
				if ( is_string($value) && !is_numeric($value) ) {
					if ( isset($value[3]) && preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/",$value) == 0 ) {
						$value = '<![CDATA['.utf8_encode(htmlspecialchars($value)).']]>';
					}
				}
				$node = $xml->addChild($key,$value);
				if ( !empty($attributes) ) {
					foreach ( $attributes as $attributeKey => $attributeValue ) {
						$node->addAttribute($attributeKey, $attributeValue);
					}
				}
			}
		}
		// pass back as string. or simple xml object if you want!
		return str_replace(array('&lt;![CDATA[',']]&gt;'),array('<![CDATA[',']]>'),$xml->asXML());
	}
	
	// adapted from http://mavimo.org/varie/array_xml_php
	public static function xmlToArray( $obj, &$arr = NULL, &$root = null ) {
	    if ( is_null($arr) ) {
			$arr = array();
	    }
	    if ( is_string($obj) ) {
			$obj = new SimpleXMLElement( $obj );
			//print_r($obj);
		}
	
	    $children = $obj->children();
	    
	    $executed = FALSE;
	    // Check all children of node
	    foreach ($children as $elementName => $node) {
			// Check if there are multiple node with the same key and generate a multiarray
			if ( isset($node['value']) && preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/",$node['value']) > 0 ) {
				$elementName = (string) $node['value'];
			} else {
				if ( $root != null ) {
					if ( isset($root[$elementName.'s']) ) {
						$elementName = $elementName.'s';
					} else if ( isset($root[$elementName.'ies']) ) {
						$elementName = $elementName.'ies';
					}
				}
			}
			
			if( $root != null && isset($root[$elementName]) ) {
				$i = count($root[$elementName]);
				if ( count($node->children()) > 0 ) {
					Controller::xmlToArray($node,$root[$elementName][$i], $root);
				}
			} else {
				if( isset($arr[$elementName]) ) {
					if( isset($arr[$elementName]) ) {
						$i = count($arr[$elementName]);
						Controller::xmlToArray($node, $arr[$elementName][$i], $arr);
					} else {
						$tmp = $arr[$elementName];
						$arr[$elementName][] = $tmp;
						$i = count($arr[$elementName]);
						Controller::xmlToArray($node, $arr[$elementName][$i], $arr);
					}
				} else {
					$arr[$elementName] = array();
					Controller::xmlToArray($node, $arr[$elementName], $arr);
				}
			}
			$executed = TRUE;
		}
	    // Check if is already processed and if already contains attributes
	    if(!$executed && $children->getName() == "") {
	      $arr = (String)$obj;
	    }
	    return $arr;
	}
	
	// adapted from http://mavimo.org/varie/array_xml_php
	private static function isAssoc( $array ) {
    	return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
	}
	
	public function __destruct() {
		if ( $this->response !== null ) {
			if ( $this->p->format == 'json' ) {
				if ( Controller::isAssoc($this->response) ) {
					if ( count(array_keys($this->response)) == 1 ) {
						$keys = array_keys($this->response);
						if ( !is_scalar($this->response[$keys[0]]) ) {
							if ( !Controller::isAssoc($this->response[$keys[0]]) ) {
								$this->response = $this->response[$keys[0]];
							}
						}
					}
				}
				echo json_encode($this->response);
			} else if ( $this->p->format == 'xml' ) {
				echo Controller::toXml($this->response,$this->p->controller);
			} else {
				if ( is_array($this->response) ) {
					echo '<h1>Error: #'.$this->response['code'].'</h1>';
				} else {
					echo $this->response;
				}
			}
		}
	}
}
?>