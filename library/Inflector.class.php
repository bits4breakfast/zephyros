<?php
class Inflector {

	private static $cache = array();
	
	public static function plural( $s ) { 
	    $s = trim( $s );
	 
	    $isUppercase = ctype_upper($s);
	 
	    if (isset(Inflector::$cache['plural_'.$s]))
	        return Inflector::$cache['plural_'.$s];
	 
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
	    return Inflector::$cache['plural_'.$s] = $s;
	}
	
	public static function underscore( $s ) {
    	return preg_replace( '/\s+/', '_', trim($s) );
	}
	
	public static function habtmTableName ( $first, $second ) {
		$tableName = array( Inflector::plural( strtolower( $first ) ), Inflector::plural( strtolower($second) ) );
		sort( $tableName );
		return implode( '_', $tableName );
	}
}
?>