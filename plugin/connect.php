<?php

// Plaintext Output
  header('Content-type: text/plain');

// Set parent system flag
  define('_JEXEC', 1);

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
    JFactory::getApplication('cms');
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
    $ipFilter = array_filter(explode("\n", $plugin_params->remote_ip_filter), 'strlen');
    $userFilter = array_filter(explode("\n", $plugin_params->remote_user_filter), 'strlen');
  }

// Filter Required
  if( empty($ipFilter) && empty($userFilter) ){
    header('HTTP/1.0 403 Forbidden'); // . $_SERVER['REMOTE_ADDR']);
    die('HTTP/1.0 403 Forbidden');
  }

// Simple IP Filter
  if( !empty($ipFilter) && !in_array($_SERVER['REMOTE_ADDR'], $ipFilter) ){
    header('HTTP/1.0 401 Unauthorized');
    die('HTTP/1.0 401 Unauthorized');
  }

// User Auth Filter
  if( !empty($userFilter) ){
    $headers = getallheaders();
    if( !empty($headers['Authorization']) ){
      $headerAuth = explode(' ', $headers['Authorization'], 2);
      $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
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
      $_SERVER['argv'][] = $v;
  }

// Include / Execute CLI Class
  include JPATH_BASE . '/cli/autoupdate.php';
