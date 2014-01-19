<?php
namespace bits4breakfast\zephyros;

class Request
{    
    public static $user;
    public static $l;
    
    
    /**
     * A wrapper for $_REQUEST[$key] that checks if the searched $key is defined 
     * (and thus prevent that tedious PHP Notice in log files
     * @param string $key the key to search for
     * @param string $default the value to be returned when $key is not found
     * @return string 
    */
    public static function get($key, $default = null)
    {
        if (isset($_REQUEST[$key]))
            return $_REQUEST[$key];        
        else 
            return $default;        
    }
    
    public static function isDefined($key)
    {
        return array_key_exists($key, $_REQUEST);
    }
    
    public static function isCrawler()
    {
        return Request::isDefined("_escaped_fragment_")
            || strpos($_SERVER['HTTP_USER_AGENT'], "Googlebot") !== false 
            || strpos($_SERVER['HTTP_USER_AGENT'], "Bingbot") !== false 
            || strpos($_SERVER['HTTP_USER_AGENT'], "Yahoo! Slurp") !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], "KomodiaBot") !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], "Aboundex") !== false;                       
    }    
    
    
    public static function init(User $user = null)
    {
        self::$user = $user;        
        
        $lang = "IT";

        /*
        if ($this->user)
            $lang = $this->user->language;
        else
        {
            $lang = strtoupper(substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2));
            
            if (empty($lang) || ! LanguageManager::languagesIsSupported($lang))
                $lang = 'EN';
        }        
        */
        
       self::$l = new LanguageManager($lang);
    }
    
    
    public static function userTimeZone()
    {
        //@TODO: get from logged user if any, or try get from http request or ip geo-localization
        
        return 'Europe/Rome';
    }
    
    
    public static function fail( RouteParameters $p, $code = 0, $user = null )
    {
        
		                       
        if ( $p->format == 'json' ) {
            echo json_encode( array( "code" => $code ) );
		} elseif ($p->format == 'html') {                   
            
        }
        
		exit;        
    }
    
    
    public static function url($includeQuery = true)
    {
        $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
        
        $uri = $_SERVER["PATH_INFO"];
        if ($includeQuery)
            $uri = $_SERVER["REQUEST_URI"];
        
        if ($_SERVER["SERVER_PORT"] != "80")
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$uri;
        else 
            $pageURL .= $_SERVER["SERVER_NAME"].$uri;
        
        return $pageURL;        
    }    
}

?>