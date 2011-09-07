<?php
class Inflector {

	private static $cache = array();
	
	public static function plural( $s ) { 
	    $s = trim( $s );
	 
	    $isUppercase = ctype_upper($str);
	 
	    if (isset(Inflector::$cache[$key]))
	        return Inflector::$cache[$key];
	 
		if ( substr($s,-1) == 'y' ) {
			$s = substr($s,0,-1).'ies';	
		} else if ( substr($s,-2) == 'sh' || substr($s,-2) == 'ch' || substr($s,-1) == 's' || substr($s,-1) == 'x' || substr($s,-1) == 'z' ) {
			$s .= 'es';	
		} else {
			$s .= 's';
		}
	 
	    // Convert to uppsecase if nessasary
	    if ( $isUppercase ) {
	        $s = strtoupper( $s );
	    }
	 
	    // Set the cache and return
	    return Inflector::$cache['plural_'.$str] = $s;
	}
	
	public static function underscore( $s ) {
    	return preg_replace( '/\s+/', '_', trim($s) );
	}
}
?>