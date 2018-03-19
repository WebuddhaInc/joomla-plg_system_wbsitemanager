<?php

// Die
  (isset($installer) && $installer instanceof wbSiteManager_StandaloneInstaller)
    or die();

// Mark
  $installer->log('Starting postFlight operation');

// Set Version
  const _JoomlaCliAutoUpdateVersion = '0.2.0';

/**
 * 869: joomla\application\web
 */
  if( !isset($_SERVER['HTTP_HOST']) )
    $_SERVER['HTTP_HOST'] = 'cms';

/**
 * 11: includes\framework.php
 */
  if( !isset($_SERVER['HTTP_USER_AGENT']) )
    $_SERVER['HTTP_USER_AGENT'] = 'cms';

// Set parent system flag
  if( !defined('_JEXEC') ){
    define('_JEXEC', 1);
  }

// Define Base
  if( !defined('JPATH_BASE') ){
    define('JPATH_BASE', dirname(dirname(dirname(__DIR__))));
  }

// Check Installation Folder
  if (is_dir(JPATH_BASE . '/installation')) {
    $_deleteInstallationFolder = JPATH_BASE . '/installation.' . time();
    rename( JPATH_BASE . '/installation', $_deleteInstallationFolder );
  }

// Define CLI
  if( !defined('CLI') && function_exists('php_sapi_name') && substr(php_sapi_name(), 0, 3) == 'cli' ){
    define('CLI', 1);
  }

// Load system defines
  if( file_exists(JPATH_BASE . '/defines.php') ){
    require_once JPATH_BASE . '/defines.php';
  }

// Load defaut defines
  if( !defined('_JDEFINES') ){
    require_once JPATH_BASE . '/includes/defines.php';
  }

// Load Framework
  require_once JPATH_BASE . '/includes/framework.php';

// Update Error Reporting
  error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_STRICT ^ E_DEPRECATED);
  ini_set('error_log', 'error_log');
  ini_set('display_errors', 0);
  set_time_limit(0);

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

// Load the configuration
  require_once JPATH_CONFIGURATION . '/configuration.php';

// Load Libraries
  jimport('joomla.updater.update');
  jimport('joomla.application.component.helper');
  jimport('joomla.filesystem.folder');
  jimport('joomla.filesystem.file');

// Prepare Logger
  JLog::addLogger(array(
    'text_file' => 'wbsitemanager.standaloneInstaller.php',
    'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
    ), JLog::ALL, array('jerror', 'jinfo', 'Update'));

// Mark
  $installer->log('- Postflight loaded');

// Delete Installation Folder
  if (isset($_deleteInstallationFolder)) {
    $installer->log('- Removed Installation folder');
    JFolder::delete($_deleteInstallationFolder);
  }

// Execute Script
  if (file_exists(JPATH_BASE .'/administrator/components/com_admin/script.php')){
    try {
      $installer->log('Loading Joomla Installer');
      define("_JEXEC", 1);
      include_once JPATH_BASE .'/administrator/components/com_admin/script.php';
      if (class_exists("JoomlaInstallerScript")) {
        $installer->log('- Initialise installer');
        $script = new JoomlaInstallerScript;
        $installer->log('- Performing cleanup');
        $script->deleteUnexistingFiles();
        $installer->log('- Cleanup complete');
      }
      else  {
        $installer->log("- Installer not found");
      }
    }
    catch (Exception $e) {
      $installer->log('- Cleanup error: ' . $e->getMessage());
    }
  }

/**
500 Error...
// Process Database Updates
  if (file_exists(JPATH_BASE . '/administrator/components/com_joomlaupdate/models/default.php')){
    require_once JPATH_BASE . '/administrator/components/com_joomlaupdate/models/default.php';
    $model = new JoomlaupdateModelDefault();
    try {
      $installer->log('Processing Manifest Updates');
      if (!$model->finaliseUpgrade()) {
        $error_msg = $installer->getError();
        $installer->log('- Error: Manifest ' . $error_msg);
        return false;
      }
      $installer->log('- Manifest Updates Complete');
    } catch (Exception $e) {
      $installer->log('- Manifest Update Failed: ' . $e->getMessage());
    }
  }
  **/

// Done
  $installer->log('Done!');
