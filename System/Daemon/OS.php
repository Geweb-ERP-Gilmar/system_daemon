<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

/**
 * Operating System focussed functionality.
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * * 
 */
abstract class System_Daemon_OS
{

    /**
     * Operating systems and versions are based on the existence and
     * the information found in these files.
     * The order is important because Ubuntu has also has a Debian file
     * for compatibility purposes. So in this case, scan for most specific
     * first.
     *
     * @var array
     */    
    public static $osVersionFiles = array(
        "Mandrake"=>"/etc/mandrake-release",
        "SuSE"=>"/etc/SuSE-release",
        "RedHat"=>"/etc/redhat-release",
        "Ubuntu"=>"/etc/lsb-release",
        "Debian"=>"/etc/debian_version"
    );
    
    /**
     * Cache that holds values of some functions 
     * for performance gain. Easier then doing 
     * if (!isset(self::$XXX)) { self::$XXX = self::XXX(); }
     * every time, in my opinion. 
     *
     * @var array
     */
    private static $_intFunctionCache = array();
    
    
    /**
     * Decide what facility to log to.
     *  
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *
     * @throws System_Daemon_Exception  
     * @return void
     */
    private function log($level, $str, $file = false, $class = false, 
        $function = false, $line = false)
    {
        if ($level > 1) {
            if (parent) {
                if (parent::$pear) {
                    throw new System_Daemon_Exception($log_line);
                }
            }            
        }
    }//end log()   
    
    /**
     * Returns an array(main, distro, version) of the OS it's executed on
     *
     * @return array
     */
    public static function determine()
    {
        // this will not change during 1 run, so just cache the result
        if (!isset(self::$_intFunctionCache[__FUNCTION__])) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $main   = "Windows";
                $distro = PHP_OS;
            } else if (stristr(PHP_OS, "Darwin")) {
                $main   = "BSD";
                $distro = "Mac OSX";
            } else if (stristr(PHP_OS, "Linux")) {
                $main = php_uname('s');
                foreach (self::$osVersionFiles as $distro=>$osv_file) {
                    if (file_exists($osv_file)) {
                        $version = trim(file_get_contents($osv_file));
                        break;
                    }
                }
            } else {
                return false;
            }

            self::$_intFunctionCache[__FUNCTION__] = compact("main", "distro", 
                "version");
        }

        return self::$_intFunctionCache[__FUNCTION__];
    }//end determine()  
    
    /**
     * Writes an: 'init.d' script on the filesystem
     *
     * @param bolean $overwrite May the existing init.d file be overwritten?
     * 
     * @return mixed boolean on failure, string on success
     */
    public static function initDWrite($overwrite = false)
    {
        // up to date filesystem information
        clearstatcache();
        
        // collect init.d path
        $initd_location = self::initDLocation();
        if (!$initd_location) {
            // explaining errors should have been generated by System_Daemon_OS::initDLocation() 
            // already
            return false;
        }
        
        // collect init.d body
        $initd_body = self::osInitDForge();
        if (!$initd_body) {
            // explaining errors should have been generated by osInitDForge() 
            // already
            return false;
        }
        
        // as many safety checks as possible
        if (!$overwrite && file_exists(($initd_location))) {
            self::log(2, "init.d script already exists", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } 
        if (!is_dir($dir = dirname($initd_location))) {
            self::log(3, "init.d directory: '".$dir."' does not ".
                "exist. Can this be a correct path?", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!is_writable($dir = dirname($initd_location))) {
            self::log(3, "init.d directory: '".$dir."' cannot be ".
                "written to. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if (!file_put_contents($initd_location, $initd_body)) {
            self::log(3, "init.d file: '".$initd_location."' cannot be ".
                "written to. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if (!chmod($initd_location, 0777)) {
            self::log(3, "init.d file: '".$initd_location."' cannot be ".
                "chmodded. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } 
        
        return $initd_location;
    }//end System_Daemon_OS::initDWrite() 
    
    /**
     * Returns an: 'init.d' script path as a string. For now only Debian & Ubuntu
     *
     * @return mixed boolean on failure, string on success
     */
    public static function initDLocation()
    {
        // this will not change during 1 run, so just cache the result
        if (!isset(self::$_intFunctionCache[__FUNCTION__])) {
            self::$initDLocation = false;
            
            // collect OS information
            list($main, $distro, $version) = array_values(self::determine());
            
            // where to collect the skeleton (template) for our init.d script
            switch (strtolower($distro)){
            case "debian":
            case "ubuntu":
                // here it is for debian systems
                self::$initDLocation = "/etc/init.d/".self::appName;
                break;
            default:
                // not supported yet
                self::log(2, "skeleton retrieval for OS: ".$distro.
                    " currently not supported ", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            
            self::$_intFunctionCache[__FUNCTION__] = self::initDLocation;
        }
        
        return self::$_intFunctionCache[__FUNCTION__];
    }//end initDLocation()
    
    /**
     * Returns an: 'init.d' script as a string. for now only Debian & Ubuntu
     * 
     * @param integer $info Daemon properties
     * 
     * @throws System_Daemon_Exception
     * @return mixed boolean on failure, string on success
     */
    public static function initDForge( $properties = false )
    {
        // initialize & check variables
        $skeleton_filepath = false;
        
        // try to fetch properties from parent object if no argument
        // is specified
        if ($properties === false) {
            $properties = array();
            if (parent::$appName) {
                $properties["appName"]        = parent::$appName;
                $properties["appDescription"] = parent::$appDescription;
                $properties["authorName"]     = parent::$authorName;
                $properties["authorEmail"]    = parent::$authorEmail;
            }
        }
        
        if (!count($properties)) {
            self::log(2, "
                No properties to forge init.d script");
        }
        
        // sanity
        $daemon_filepath = $properties["appDir"]."/".$properties["appExecutable"];
        if (!file_exists($daemon_filepath)) {
            self::log(2, 
                "unable to forge startup script for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        if (!is_executable($daemon_filepath)) {
            self::log(2, 
                "unable to forge startup script. ".
                "daemon_filepath: ".$daemon_filepath.", needs to be executable ".
                "first", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if (!$properties["authorName"]) {
            self::log(2, 
                "unable to forge startup script for non existing ".
                "authorName: ".$properties["authorName"]."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$properties["authorEmail"]) {
            self::log(2, 
                "unable to forge startup script for non existing ".
                "authorEmail: ".$properties["authorEmail"]."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$properties["appDescription"]) {
            self::log(2, 
                "unable to forge startup script for non existing ".
                "appDescription: ".$properties["appDescription"]."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        // collect OS information
        list($main, $distro, $version) = array_values(self::determine());

        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
        case "debian":
        case "ubuntu":
            // here it is for debian based systems
            $skeleton_filepath = "/etc/init.d/skeleton";
            break;
        default:
            // not supported yet
            self::log(2, 
                "skeleton retrieval for OS: ".$distro.
                " currently not supported ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
            break;
        }

        // open skeleton
        if (!$skeleton_filepath || !file_exists($skeleton_filepath)) {
            self::log(2, 
                "skeleton file for OS: ".$distro." not found at: ".
                $skeleton_filepath, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } elseif ($skeleton = file_get_contents($skeleton_filepath)) {
            // skeleton opened, set replace vars
            switch (strtolower($distro)){
            case "debian":
            case "ubuntu":                
                $replace = array(
                    "Foo Bar" => $properties["authorName"],
                    "foobar@baz.org" => $properties["authorEmail"],
                    "daemonexecutablename" => $properties["appName"],
                    "Example" => $properties["appName"],
                    "skeleton" => $properties["appName"],
                    "/usr/sbin/\$NAME" => $daemon_filepath,
                    "Description of the service"=> $properties["appDescription"],
                    " --name \$NAME" => "",
                    "--options args" => "",
                    "# Please remove the \"Author\" ".
                        "lines above and replace them" => "",
                    "# with your own name if you copy and modify this script." => ""
                );
                break;
            default:
                // not supported yet
                self::log(2, 
                    "skeleton modification for OS: ".$distro.
                    " currently not supported ", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
                break;
            }

            // replace skeleton placeholders with actual daemon information
            $skeleton = str_replace(array_keys($replace), 
                array_values($replace), 
                $skeleton);

            // return the forged init.d script as a string
            return $skeleton;
        }
    }//end initDForge()
}//end class
?>