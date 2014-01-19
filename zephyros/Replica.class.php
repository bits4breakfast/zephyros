<?php
class HttpReplicationClient {

	private static $instance = null;
	private static $filesToRemove = array();
	private static $filesToSend = array();
	private static $dirsToRemove = array();	
	
	public function __costruct() {
		return;	
	}
	
	public function __destruct() {
		return;
		$files = array();
		foreach ( self::$filesToSend as $file ) {
			if ( file_exists($file) ) {
				$files[] = array( "path" => $file, "content" => base64_encode(file_get_contents($file)) );
			}
		}
	
		$packetPayLoad = json_encode( array("dirsToRemove" => self::$dirsToRemove, "filesToRemove" => self::$filesToRemove, "filesToSend" => $files) );
		
		$hosts = file_get_contents(HOSTS_PATH."/replicationhosts.ini");
		if (trim($hosts) != '') {
			$hosts = explode(",",$hosts);
		} else {
			$hosts = array();
		}
		
		foreach ( $hosts as $host ) {
			$ch = curl_init();
			curl_setopt($ch,CURLOPT_URL,$host);
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$packetPayLoad);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
			curl_exec($ch);
			curl_close($ch);
		}
	}
	
	public static function rmdir( $dir ) {
		if ( self::$instance == null ) {
			self::$instance = new HttpReplicationClient();
		}
		self::$dirsToRemove[] = $dir;
	}
	
	public static function remove( $file ) {
		if ( self::$instance == null ) {
			self::$instance = new HttpReplicationClient();
		}
		self::$filesToRemove[] = $file;
	}
	
	public static function send( $file ) {
		if ( self::$instance == null ) {
			self::$instance = new HttpReplicationClient();
		}
		self::$filesToSend[] = $file;
	}
}

class HttpReplicationServer {

	public static function init() {
		$server = new HttpReplicationServer();
	}
	
	private function __construct() {
		$request = file_get_contents("php://input");
		if ( trim($request) != "" ) {
			$request = json_decode($request);
			if ( isset($request->dirsToRemove) ) {
				$this->removeDir( $request->dirsToRemove );
			}
			
			if ( isset($request->filesToSend) ) {
				$this->addFiles( $request->filesToSend );
			}
			
			if ( isset($request->filesToRemove) ) {
				$this->removeFiles( $request->filesToRemove );
			}	
		} 
	}
	
	private function removeFiles( $list ) {
		foreach ( $list as $file ) {
			@unlink($file);
		}
	}
	
	private function addFiles( $list ) {
		foreach ( $list as $file ) {
			$dir = dirname($file->path);
			if (opendir($dir) === false) {
				mkdir($dir,0777,true);
			}
			file_put_contents($file->path,base64_decode($file->content));
		}
	}
	
	private function removeDir( $list ) {
		foreach ( $list as $dir ) {
			HttpReplicationServer::rrmdir( $dir );
		}
	}
	
	public static function rrmdir( $path ) {
		if (!file_exists($path)) {
			return true;
		}
   		
   		if (!is_dir($path) || is_link($path)) { 
   			return unlink($path);
   		}
   		
        foreach (scandir($path) as $item) {
            if ($item == '.' || $item == '..') {
            	continue;
            }
            if (! HttpReplicationServer::rmdir($path . "/" . $item)) {
                chmod($path . "/" . $item, 0666);
                if (!HttpReplicationServer($path . "/" . $item)) {
                	return false;
                }
            }
        }
        return rmdir($path);
	}
		
}
?>