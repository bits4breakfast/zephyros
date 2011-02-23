<?
include_once(LIB."/core/Replica.class.php");
abstract class Cachable {
	
	protected $cacheDir = '/cache/objects/';
	
	protected function isCached($className, $fileName) {
		return file_exists($this->cacheDir.$className.'/'.$fileName);
	}
	
	protected function readCache($className, $fileName) {
		$cacheFile = $this->cacheDir.$className.'/'.$fileName;
		
		if( !is_file($cacheFile) ) {
			//print "Impossible to load cache for $className #$fileName<br />";
			return;
		}
		
		$obj = unserialize(file_get_contents($this->cacheDir.$className.'/'.$fileName));
		
		if( !is_object($obj) ) {
			//print "Impossible to load cache for $className #$fileName<br />";
			return;
		}
		
		$vars = $obj->__getDump();
		
		foreach($vars as $key=>$val)
			if( !isset($this->$key) or (!is_object($this->$key)) or (is_object($this->$key) && get_class($this->$key)!='Mysql') )
				$this->$key = $val;
	}
	
	protected function writeCache($className, $fileName) {
		if( $className==get_class($this) and $fileName!='' ) {
			$content = serialize($this);
			if (trim($content) != "") {
				@file_put_contents($this->cacheDir.$className.'/'.$fileName, $content);
				@chmod($this->cacheDir.$className.'/'.$fileName,0766);
				HttpReplicationClient::send("/cache/objects/".$className.'/'.$fileName);
			}
		} else {
			self::clearCache($className, $fileName);
		}
	}
	
	protected function clearCache($className, $fileName) {
		HttpReplicationClient::remove("/cache/objects/".$className.'/'.$fileName);

		if( is_file($this->cacheDir.$className.'/'.$fileName) && file_exists($this->cacheDir.$className.'/'.$fileName) )
			@unlink($this->cacheDir.$className.'/'.$fileName);
	}
	
	final function __getDump() {
		$vars = array();
		
		foreach($this as $key=>$val)
			$vars[$key] = $val;
		
		return $vars;
	}
	
	final function __hasObj($var) {
		if( is_object($var) )
			return true;
		else if( is_array($var) ) {
			foreach($var as $key=>$val)
				if( $this->__hasObj($val) )
					return true;
			
			return false;
		}
		else
			return false;
	}
	
	protected function __sleep() {
		$toSerialize = array();
		
		foreach($this as $key=>$val)
			if( !$this->__hasObj($val) )
				$toSerialize[] = $key;
		
		return $toSerialize;
	}
}

?>