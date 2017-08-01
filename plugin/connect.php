<?php

// Plaintext Output
  header('Content-type: text/plain');

// Set parent system flag
  define('_JEXEC', 1);

/**
 * Patch for PHP-FPM missing method
 */
  
  if (!function_exists('getallheaders')) { 
    function getallheaders() { 
      $headers = array(); 
      foreach ($_SERVER as $name => $value) { 
        if (substr($name, 0, 5) == 'HTTP_') { 
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
        } else if ($name == "CONTENT_TYPE") { 
          $headers["Content-Type"] = $value; 
        } else if ($name == "CONTENT_LENGTH") { 
          $headers["Content-Length"] = $value; 
        }         
      } 
      return $headers; 
    } 
  } 

/**
 * Load Environment
 */

  // Define Base
    $base_path = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirname(getcwd())), 0, -2));
    if( !file_exists($base_path . '/configuration.php') && isset($_SERVER['SCRIPT_FILENAME']) ){
      $base_path = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']), 0, -4));
    }
    if( !file_exists($base_path . '/configuration.php') ){
      header('HTTP/1.0 500 Internal Server Error');
      die('HTTP/1.0 500 Internal Server Error');
    }
    define('JPATH_BASE', $base_path);

  // Load System defines
    if( file_exists(JPATH_BASE . '/defines.php') ){
      require_once JPATH_BASE . '/defines.php';
    }
  // Load Defaut Defines
    if( !defined('_JDEFINES') ){
      require_once JPATH_BASE . '/includes/defines.php';
    }

  // Load Application & Required Libraries
    require_once JPATH_BASE . '/includes/framework.php';

  // Iniitialize Application
    if( version_compare( JVERSION, '3.2.0', '>=' ) ){
      JFactory::getApplication('cms');
    }
    else if( version_compare( JVERSION, '3.1.0', '>=' ) ){
      JFactory::getApplication('site');
    }
    else {
      JFactory::getApplication('administrator');
    }

  // Required Libraries
    jimport('joomla.user.authentication');

// Import Configuration
  if( is_readable('connect.config.php') ){
    include 'connect.config.php';
  }
  else {
    $plugin = JPluginHelper::getPlugin('system','wbsitemanager');
    if( empty($plugin) ){
      header('HTTP/1.0 503 Service Unavailable');
      die('HTTP/1.0 503 Service Unavailable');
    }
    $plugin_params = json_decode($plugin->params);
    $ipFilter = array_filter(explode("\n", preg_replace('/[^\n0-9\.\*]/','',$plugin_params->remote_ip_filter)), 'strlen');
    $userFilter = array_filter(explode("\n", preg_replace('/[^\na-z0-9\.\@\_\-]/','',strtolower($plugin_params->remote_user_filter))), 'strlen');
  }

// Filter Required
  if( empty($ipFilter) && empty($userFilter) ){
    header('HTTP/1.0 403 Forbidden'); // . $_SERVER['REMOTE_ADDR']);
    die('HTTP/1.0 403 Forbidden');
  }

// Simple IP Filter
  if( !empty($ipFilter) ){
    require_once __DIR__ . '/classes/ipv4filter.class.php';
    $ipv4filter = new wbSiteManager_IPV4Filter($ipFilter);
    if( !$ipv4filter->check( $_SERVER['REMOTE_ADDR'] ) ){
      header('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
      die('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
    }
  }

// User Auth Filter
  if( !empty($userFilter) ){
    $headers = getallheaders();
    $authCredentials = null;
    if( !empty($headers['Authorization-Manager']) ){
      $headerAuth = explode(' ', $headers['Authorization-Manager'], 2);
      $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
    }
    else if( !empty($headers['Authorization']) ){
      $headerAuth = explode(' ', $headers['Authorization'], 2);
      $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
    }
    else if( @$_SERVER['PHP_AUTH_USER'] && @$_SERVER['PHP_AUTH_PW'] ){
      $authCredentials = array('username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']);
    }
    if( $authCredentials ){
      if( is_array($userFilter) && !in_array($authCredentials['username'], $userFilter) ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
      $authResult = JAuthentication::getInstance()->authenticate($authCredentials);
      if( !$authResult || $authResult->status != 1 ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
    }
    else {
      header('HTTP/1.0 400 Bad Request');
      die('HTTP/1.0 400 Bad Request');
    }
  }

// Prepare CLI Requirements
  define('STDIN', fopen('php://input', 'r'));
  define('STDOUT', fopen('php://output', 'w'));
  $_SERVER['argv'] = array('autoupdate.php');
  $mQuery = array_merge($_GET, $_POST);
  foreach( $mQuery AS $k => $v ){
    $_SERVER['argv'][] = '-' . $k;
    if( strlen($v) )
      $_SERVER['argv'][] = urldecode($v);
  }

// Include / Execute CLI Class
  if (!is_readable(JPATH_BASE . '/cli/autoupdate.php')) {
    header('HTTP/1.0 404 Update CLI not found');
    die('HTTP/1.0 404 Update CLI not found');
  }
  include JPATH_BASE . '/cli/autoupdate.php';
