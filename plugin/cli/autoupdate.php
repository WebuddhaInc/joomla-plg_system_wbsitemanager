<?php

/**
 *
 * This is a CLI Script only
 *   /usr/bin/php /path/to/site/cli/autoupdate.php
 *
 * For Help
 *   php autoupdate.php -h
 *
 */

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
    define('JPATH_BASE', dirname(getcwd()));
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
  ini_set('display_errors', 1);
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

// Required Libraries
  jimport('joomla.updater.update');
  jimport('joomla.application.component.helper');
  jimport('joomla.filesystem.folder');
  jimport('joomla.filesystem.file');

// Prepare Logger
  JLog::addLogger(array(
    'text_file' => 'autoupdate.php',
    'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
    ), JLog::ALL, array('jerror', 'jinfo', 'Update'));

/**
 * This script will download and install all available updates.
 *
 * @since  3.4
 */
  class JoomlaCliAutoUpdate extends JApplicationCli {

    /**
     * [$__outputBuffer description]
     * @var null
     */
    public $__outputBuffer = null;
    public $db             = null;
    public $updater        = null;
    public $installer      = null;
    public $config         = null;

    /**
     * [__construct description]
     * @param JInputCli|null   $input      [description]
     * @param JRegistry|null   $config     [description]
     * @param JDispatcher|null $dispatcher [description]
     */
    public function __construct(JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null){

      // CLI Constructor
        parent::__construct($input, $config, $dispatcher);

      // Error Handlers
        JError::setErrorHandling(E_NOTICE, 'callback', array($this, 'throwNotice'));
        JError::setErrorHandling(E_WARNING, 'callback', array($this, 'throwWarning'));
        JError::setErrorHandling(E_ERROR, 'callback', array($this, 'throwError'));

      // Utilities
        $this->db        = JFactory::getDBO();
        $this->updater   = JUpdater::getInstance();
        $this->installer = JComponentHelper::getComponent('com_installer');

      // Validate Log Path
        $logPath = $this->config->get('log_path');
        if( !is_dir($logPath) || !is_writeable($logPath) ){
          $logPath = JPATH_BASE . '/logs';
          if( !is_dir($logPath) || !is_writeable($logPath) ){
            $this->out('Log Path not found - ' . $logPath);
          }
          $this->config->set('log_path', JPATH_BASE . '/logs');
        }

      // Validate Tmp Path
        $tmpPath = $this->config->get('tmp_path');
        if( !is_writeable($tmpPath) || (!defined('CLI') && strpos($tmpPath, JPATH_BASE) !== 0) ){
          $tmpPath = JPATH_BASE . '/tmp';
          if( !is_dir($tmpPath) || !is_writeable($tmpPath) ){
            $this->out('Tmp Path not found - ' . $tmpPath);
            if (!defined('CLI')) {
              $this->out('Remote execution requires public accessible tmp path - ' . $tmpPath);
            }
          }
          $this->config->set('tmp_path', JPATH_BASE . '/tmp');
        }

      // Push to Global Config
        $config = JFactory::getConfig();
        $config->set('tmp_path', $this->config->get('tmp_path'));
        $config->set('log_path', $this->config->get('log_path'));

      // Remote Access
        if (!defined('CLI')) {
          $config->set('site_base', str_replace('plugins/system/wbsitemanager/', '', JUri::base()));
          $this->config->set('site_base', $config->get('site_base'));
          $this->config->set('tmp_url', $config->get('site_base') . substr($tmpPath, strlen(JPATH_BASE)+1) . '/');
        }

    }

    /**
     * [throwNotice description]
     * @param  [type] $error [description]
     * @return [type]        [description]
     */
    public function throwNotice( $error ){
      $this->out('Notice #' . $error->getCode() .' - ' . JText::_($error->getMessage()));
    }

    /**
     * [throwWarning description]
     * @param  [type] $error [description]
     * @return [type]        [description]
     */
    public function throwWarning( $error ){
      $this->out('Warning #' . $error->getCode() .' - ' . JText::_($error->getMessage()));
    }

    /**
     * [throwError description]
     * @param  [type] $error [description]
     * @return [type]        [description]
     */
    public function throwError( $error ){
      $this->out('Error #' . $error->getCode() .' - ' . JText::_($error->getMessage()));
      exit(1);
    }

    /**
     * [doPurgeUpdatesCache description]
     * @return [type] [description]
     */
    public function doPurgeUpdatesCache(){

      // Purge Updates Table
        $this->db
          ->setQuery(
            $this->db->getQuery(true)
              ->delete($this->db->quoteName('#__updates'))
              )
          ->execute();
        $this->out('Purged Updates');

      // Reset Cache
        $this->db
          ->setQuery(
            $this->db->getQuery(true)
              ->update($this->db->quoteName('#__update_sites'))
              ->set($this->db->quoteName('last_check_timestamp') . ' = 0')
              )
          ->execute();
        $this->out('Reset Update Cache');

      // Floor Cache Timeout
        if( $this->installer ){
        $this->installer->params->set('cachetimeout', 0);
        }

    }

    /**
     * [doFetchUpdates description]
     * @return [type] [description]
     */
    public function doFetchUpdates(){

      // Get the update cache time
        $cache_timeout = ($this->installer ? $this->installer->params->get('cachetimeout', 6, 'int') : 6);
        $cache_timeout = 3600 * $cache_timeout;

      // Find all updates
        $this->updater->findUpdates(0, $cache_timeout);
        $this->out('Fetched Updates');

    }

    /**
     * [getUpdateRows description]
     * @param  [type] $lookup [description]
     * @param  [type] $start  [description]
     * @param  [type] $limit  [description]
     * @return [type]         [description]
     */
    public function getUpdateRows( $lookup = null, $start = null, $limit = null ){

      // Prepare Query
        $query = $this->db->getQuery(true)
          ->select('*')
          ->from('#__updates')
          ->where($this->db->quoteName('extension_id') . ' != ' . $this->db->quote(0));

      // Prepare Filter
        if( is_numeric($lookup) ){
          $lookup = array('extension_id' => $lookup);
        }
        else if( is_string($lookup) ){
          $lookup = array('element' => $lookup);
        }
        else if( is_array($lookup) ){
          $lookup = (array)$lookup;
        }
        if( $lookup ){
          foreach( $lookup AS $key => $val ){
            $query->where($this->db->quoteName( $key ) . ' = ' . $this->db->quote($val));
          }
        }

      // Query
        return
          $this->db
            ->setQuery($query, $start, $limit)
            ->loadObjectList();

    }

    /**
     * [doInstallUpdate description]
     * @param  [type] $update_id   [description]
     * @param  [type] $build_url   [description]
     * @param  [type] $package_url [description]
     * @return [type]              [description]
     */
    public function doInstallUpdate( $update_id, $build_url = null, $package_url = null ){

      // Load Build XML
        $updateRow = JTable::getInstance('update');
        if( $update_id || $build_url ){
          if( $update_id ){
            $this->out('Processing Update #'. $update_id);
            $updateRow->load( $update_id );
            $build_url = $updateRow->detailsurl;
          }
          else if( $parse_url = parse_url( $build_url ) ){
            $this->out('Processing Update from '. $parse_url['host']);
          }
          if( $build_url ){
            $update = new JUpdate();
            if( $this->installer && defined('JUpdater::STABILITY_STABLE') ){
              $update->loadFromXml($build_url, $this->installer->params->get('minimum_stability', JUpdater::STABILITY_STABLE, 'int'));
            }
            else {
              $update->loadFromXml($build_url);
            }
            if( !empty($updateRow->extra_query) ){
              $update->set('extra_query', $updateRow->extra_query);
            }
          }
        }
        else if ($package_url) {
          $this->out('Processing Update from URL');
        }

      // Pull Packge URL from Build
        if( isset($update) && empty($package_url) ){
          $package_url = $update->downloadurl->_data;
          if( $extra_query = $update->get('extra_query') ){
            $package_url .= (strpos($package_url, '?') === false) ? '?' : '&amp;';
            $package_url .= $extra_query;
          }
        }

      // Download
        $tmpPath = $this->config->get('tmp_path');
        $this->out(' - Download ' . $package_url);
        $t_file = JInstallerHelper::getFilenameFromUrl($package_url);
        if (!preg_match('/\.zip$/', $t_file)) {
          $t_file .= '.zip';
        }
        $p_file = JInstallerHelper::downloadPackage($package_url, $t_file);
        if( $p_file && is_file($tmpPath . '/' . $p_file) ){
          $filePath = $tmpPath . '/' . $p_file;
        }
        else {
          $this->out(' - Download Failed, Attempting alternate download method');
          $urlFile = preg_replace('/^.*\/(.*?)$/', '$1', $package_url);
          $filePath = $tmpPath . '/' . $urlFile;
          if( $fileHandle = @fopen($filePath, 'w+') ){
            $curl = curl_init($package_url);
            curl_setopt_array($curl, [
              CURLOPT_URL            => $package_url,
              CURLOPT_FOLLOWLOCATION => 1,
              CURLOPT_BINARYTRANSFER => 1,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_FILE           => $fileHandle,
              CURLOPT_TIMEOUT        => 50,
              CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'
            ]);
            $response = curl_exec($curl);
            if( $response === false ){
              $this->out(' - Download Failed: ' . curl_error($curl));
              $this->outStatus(400, 'Download Failed: ' . curl_error($curl));
              return false;
            }
          }
          else {
            $this->out(' - Download Failed, Error writing ' . $filePath);
            $this->outStatus(400, 'Download Failed, Error writing ' . $filePath);
            return false;
          }
        }

      // Catch Error
        if( !is_file($filePath) ){
          $this->out(' - Download Failed / File not found');
          $this->outStatus(400, 'Download Failed / File not found');
          return false;
        }

      // Extracting Package
        $this->out(' - Extracting Package');
        $package = JInstallerHelper::unpack($filePath, true);
        if( empty($package) || empty($package['extractdir']) ){
          $this->out(' - Extract Failed');
          $this->outStatus(400, 'Extract Failed');
          JFile::delete($filePath);
          return false;
        }
        JFile::delete($filePath);

      // No Type? Check if Core
        if (!$package['type'] && is_file($package['extractdir'] . '/administrator/manifests/files/joomla.xml')) {
          $package['type'] = 'file';
        }

      // Success Flag
        $success = true;

      // File Installations
        if ($package['type'] == 'file') {

          // Preload in case files change
            $app = JInstaller::getInstance();
            require_once JPATH_BASE . '/administrator/components/com_joomlaupdate/models/default.php';
            $model = new JoomlaupdateModelDefault();
            $model->finaliseUpgrade();
            $model = new JoomlaupdateModelDefault();

          // Build Standalone
            $installer_filename = 'installer_' . time() . '.php';
            $installer_filepath = $tmpPath . '/' . $installer_filename;
            $installer_script   = JPATH_BASE . '/plugins/system/wbsitemanager/standaloneInstaller.php';
            if ($fh = fopen($installer_filepath, 'w')) {
              $installer_code = array(
                '<?php',
                '/* wbSiteManager Installer ' . date('Y-m-d H:i:s') .' */',
                'ob_start();',
                'try {',
                '  $time = time();',
                '  include("'. JPATH_BASE .'/plugins/system/wbsitemanager/standaloneInstaller.php");',
                '  $installer = new wbSiteManager_StandaloneInstaller(array(',
                '    "cache_path"  => "'. $tmpPath .'",',
                '    "source_path" => "'. $package['extractdir'] .'",',
                '    "target_path" => "'. JPATH_BASE . '"',
                '    ));',
                '  $installer->execute();',
                '  include("'. JPATH_BASE .'/plugins/system/wbsitemanager/standaloneInstaller.postFlight.php");',
                '  echo ob_get_clean();',
                '} catch (Exception $e) {',
                '  echo ob_get_clean();',
                '  echo $e->getMessage();',
                '}'
                );
              fwrite( $fh, implode("\n", $installer_code) );
              fclose( $fh );
            }
            else {
              $this->out(' - Error: Failed to generate standalone installer');
              $this->outStatus(400, 'Failed to generate standalone installer');
              return false;
            }

          // Call standalone
          // CLI users will get a local run
          // Remote users will get a local callback
            if (defined('CLI')) {
              $this->out('Calling Standalone Installer via CLI');
              // $exec_output = shell_exec('php ' . $installer_file);
              $exec_output = `php {$installer_filepath}`;
              if ($exec_output) {
                foreach (array_filter(explode("\n", $exec_output), 'strlen') AS $line) {
                  $this->out(' - ' . $line);
                  if (preg_match('/Error: (.*)/', $line, $match)) {
                    $this->out('-  Error: ' . $match[1]);
                    $this->outStatus(400, $match[1]);
                    JFile::delete($installer_filepath);
                    return false;
                  }
                }
              }
              else {
                $this->out('- Error: No Response from Standalone Installer');
                $this->outStatus(400, 'No Response from Standalone Installer');
                JFile::delete($installer_filepath);
                return false;
              }
            }
            else {
              $headers = getallheaders();
              $authCredentials = null;
              if( !empty($headers['Authorization-Manager']) ){
                $headerAuth = explode(' ', $headers['Authorization-Manager'], 2);
                $authCredentials = array_combine(array('authkey', 'username', 'password'), explode(':', base64_decode(end($headerAuth)), 3));
              }
              else if( !empty($headers['Authorization']) ){
                $headerAuth = explode(' ', $headers['Authorization'], 2);
                $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
              }
              else if( @$_SERVER['PHP_AUTH_USER'] && @$_SERVER['PHP_AUTH_PW'] ){
                $authCredentials = array('username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']);
              }
              $headers = array(
                'User-Agent: wbSiteManager/0.0.0 curl/'. curl_version() .' PHP/' . phpversion()
                );
              if ($authCredentials) {
                $headers[] = 'Authorization: Basic ' . base64_encode($authCredentials['username'].':'.$authCredentials['password']);
              }
              $this->out('Calling Standalone Installer via HTTP');
              $installer_url = $this->config->get('tmp_url') . $installer_filename;
              $this->out('- ' . $installer_url);
              $ch = curl_init();
              curl_setopt_array($ch, [
                CURLOPT_URL            => $installer_url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_VERBOSE        => 1,
                CURLOPT_HEADER         => 1,
                CURLOPT_HTTPHEADER     => $headers,
                CURLINFO_HEADER_OUT    => 1,
              ]);
              $res         = curl_exec($ch);
              $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
              $header      = substr($res, 0, $header_size);
              $resContent  = substr($res, $header_size);
              $resSuccess  = curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200;
              $resMessage  = reset(explode("\r\n", $header));
              if ($resSuccess) {
                foreach (array_filter(explode("\n", $resContent), 'strlen') AS $line) {
                  $this->out(' - ' . $line);
                  if (preg_match('/Error: (.*)/', $line, $match)) {
                    $this->outStatus(400, $match[1]);
                    JFile::delete($installer_filepath);
                    return false;
                  }
                }
              }
              else {
                $this->out('- Error: No Response from Standalone Installer');
                $this->out('- ' . curl_error($ch));
                $this->out('- ' . $resMessage);
                $this->outStatus(400, 'No Response from Standalone Installer');
                JFile::delete($installer_filepath);
                return false;
              }
            }

          // Cleanup Installer
            $this->out('- File Upgrade Complete');
            JFile::delete($installer_filepath);

          // Delete Installation
            if (is_dir(JPATH_BASE . '/installation')) {
              $this->out('Deleting Installation Folder');
              JFolder::delete(JPATH_BASE . '/installation');
            }

          // Process Database Updates
            try {
              $this->out('Processing Manifest Updates');
              if (!$model->finaliseUpgrade()) {
                $error_msg = $installer->getError();
                $this->out('- Error: Manifest ' . $error_msg);
                $this->outStatus(400, 'Manifest ' . $error_msg);
                return false;
              }
              $this->out('- Manifest Updates Complete');
            } catch (Exception $e) {
              $this->out('- Manifest Update Failed: ' . $e->getMessage());
            }

          // Purge Updates
            $this->out('Purge Update Cache');
            $this->doPurgeUpdatesCache();

          // Fetch Updates
            $this->doFetchUpdates();

        }

      // Package Installations
        else if($package['type']) {

          // Log Selection
            $this->out('Processing ' . $package['type']);

          // Install the package
            $this->out(' - Installing ' . $package['dir']);
            $installer = JInstaller::getInstance();
            if( !$installer->update($package['dir']) ){
              $this->out(' - Error Installing Update');
              $this->outStatus(400, 'Error Installing Update');
              JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
              return false;
            }

          // Success
            $this->out(' - Update Success');
            JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);

        }

      // No Type
        else {

          $this->out('Invalid Package');
          $this->outStatus(400, 'Invalid Package');
          JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
          return false;

        }


      // Complete
        return $success ?: false;

    }

    /**
     * [doIterateUpdates description]
     * @return [type] [description]
     */
    public function doIterateUpdates(){

      // Build Update Filter
        $update_lookup = array();

      // All Items
        $update_all = false;
        if( $this->input->get('a', $this->input->get('all')) ){
          $update_all = true;
        }

      // Core Items
        if( $this->input->get('c', $this->input->get('core')) ){
          $lookup = array(
            'type'    => 'file',
            'element' => 'joomla'
            );
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Extension Lookup
        if( $extension_lookup = $this->input->get('e', $this->input->get('extension')) ){
          if( is_numeric($extension_lookup) ){
            $lookup = array(
              'extension_id' => (int)$extension_lookup
              );
          }
          else {
            $lookup = array(
              'element' => (string)$extension_lookup
              );
          }
          if( $type = $this->input->get('t', $this->input->get('type')) ){
            $lookup['type'] = $type;
          }
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Update ID
        if( $update_id = $this->input->get('i', $this->input->get('id')) ){
          $update_lookup[] = array(
            'update_id' => $update_id
            );
        }

      // List / Export / Process Updates
        $update_rows = null;
        if( $update_all || count($update_lookup) ){
          $update_rows = $this->getUpdateRows( array_shift($update_lookup) );
          if( $update_rows ){
            $do_list     = $this->input->get('l', $this->input->get('list'));
            $do_export   = $this->input->get('x', $this->input->get('export'));
            $do_update   = $this->input->get('u', $this->input->get('update'));
            $export_data = null;
            if( $do_export ){
              $export_data = array(
                'updates' => array()
                );
            }
            else if( $do_list ){
              $this->out(implode('',array(
                str_pad('uid', 10, ' ', STR_PAD_RIGHT),
                str_pad('eid', 10, ' ', STR_PAD_RIGHT),
                str_pad('element', 30, ' ', STR_PAD_RIGHT),
                str_pad('type', 10, ' ', STR_PAD_RIGHT),
                str_pad('version', 10, ' ', STR_PAD_RIGHT),
                str_pad('installed', 10, ' ', STR_PAD_RIGHT)
                )));
            }
            $run_update_rows = array();
            do {
              foreach( $update_rows AS $update_row ){
                $extension = $this->db
                  ->setQuery("
                    SELECT *
                    FROM `#__extensions`
                    WHERE `extension_id` = '". (int)$update_row->extension_id ."'
                    ")
                  ->loadObject();
                $update_row->installed_version = null;
                if( $extension->manifest_cache && $extension_manifest = json_decode($extension->manifest_cache) ){
                  $update_row->installed_version = $extension_manifest ? $extension_manifest->version : null;
                }
                if( $do_export ){
                  $export_data['updates'][] = $update_row;
                }
                else if( $do_list ){
                  $this->out(implode('',array(
                    str_pad($update_row->update_id, 10, ' ', STR_PAD_RIGHT),
                    str_pad($update_row->extension_id, 10, ' ', STR_PAD_RIGHT),
                    str_pad($update_row->element, 30, ' ', STR_PAD_RIGHT),
                    str_pad($update_row->type, 10, ' ', STR_PAD_RIGHT),
                    str_pad($update_row->version, 10, ' ', STR_PAD_RIGHT),
                    str_pad($update_row->installed_version, 10, ' ', STR_PAD_RIGHT)
                    )));
                }
              }
              if( $do_update ){
                $run_update_rows += $update_rows;
              }
            } while(
              count($update_lookup)
              && $update_rows = $this->getUpdateRows( array_shift($update_lookup) )
              );
            if( count($run_update_rows) ){
              foreach( $run_update_rows AS $update_row ){
                if( !$this->doInstallUpdate( $update_row->update_id ) ){
                  return false;
                }
              }
              $this->out('Update processing complete');
            }
            if( isset($export_data) ){
              $this->out( $export_data );
            }
          }
          else {
            $this->out('No updates found');
          }
        }
        else {
          $this->out('No update filter provided');
        }

    }

    /**
     * [startOutputBuffer description]
     * @return [type] [description]
     */
    public function startOutputBuffer(){
      $this->__outputBuffer = array(
        'status'  => 200,
        'message' => 'Success',
        'log'     => array(),
        'data'    => array()
        );
    }

    /**
     * [dumpOutputBuffer description]
     * @return [type] [description]
     */
    public function dumpOutputBuffer(){
      return parent::out( json_encode($this->__outputBuffer) );
    }

    /**
     * [out description]
     * @param  string  $text [description]
     * @param  boolean $nl   [description]
     * @return [type]        [description]
     */
    public function out( $text = '', $nl = true ){
      if( isset($this->__outputBuffer) ){
        if( is_string($text) ){
          JLog::add($text, JLog::INFO, 'Update');
          $this->__outputBuffer['log'][] = $text;
        }
        else {
          $this->__outputBuffer['data'] = array_merge( $this->__outputBuffer['data'], $text );
        }
        return $this;
      }
      return parent::out( $text, $nl );
    }

    /**
     * [outStatus description]
     * @param  [type] $status  [description]
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    public function outStatus( $status, $message ){
      JLog::add($message, JLog::INFO, 'Update');
      $this->__outputBuffer['status']  = $status;
      $this->__outputBuffer['message'] = $message;
    }

    /**
     * [doExecute description]
     * @return [type] [description]
     */
    public function doExecute(){

      if( $this->input->get('x', $this->input->get('export')) ){
        $this->startOutputBuffer();
      }

      if( $this->input->get('p', $this->input->get('purge')) ){
        $this->doPurgeUpdatesCache();
      }

      if( $this->input->get('f', $this->input->get('fetch')) ){
        $this->doFetchUpdates();
      }

      if(
        $this->input->get('l', $this->input->get('list'))
        ||
        $this->input->get('u', $this->input->get('update'))
        ){
        $this->doIterateUpdates();
      }

      $build_url = $this->input->getRaw('B', $this->input->getRaw('build-xml'));
      if( $build_url && $build_url != 1 ){
        $this->doInstallUpdate( null, $build_url );
      }

      $package_url = $this->input->getRaw('P', $this->input->getRaw('package-archive'));
      if( $package_url && $package_url != 1 ){
        $this->doInstallUpdate( null, null, $package_url );
      }

      if( $this->input->get('h', $this->input->get('help')) ){
        $this->doEchoHelp();
      }

      if( $this->input->get('x', $this->input->get('export')) ){
        $this->dumpOutputBuffer();
      }

    }

    /**
     * [doEchoHelp description]
     * @return [type] [description]
     */
    public function doEchoHelp(){
      $version = _JoomlaCliAutoUpdateVersion;
      echo <<<EOHELP
Joomla! CLI Autoupdate by Webuddha v{$version}
This script can be used to examine the extension of a local Joomla!
installation, fetch available updates, download and install update packages.

Operations
  -f, --fetch                 Run Fetch
  -u, --update                Run Update
  -l, --list                  List Updates
  -p, --purge                 Purge Updates
  -P, --package-archive URL   Install from Package Archive
  -B, --build-xml URL         Install from Package Build XML

Update Filters
  -i, --id ID                 Update ID
  -a, --all                   All Packages
  -V, --version VER           Version Filter
  -c, --core                  Joomla! Core Packages
  -e, --extension LOOKUP      Extension by ID/NAME
  -t, --type VAL              Type

Additional Flags
  -x, --export                Output in JSON format
  -h, --help                  Help
  -v, --verbose               Verbose

EOHELP;
    }

  }

// Trigger Execution
  JApplicationCli::getInstance('JoomlaCliAutoUpdate')
    ->execute();
