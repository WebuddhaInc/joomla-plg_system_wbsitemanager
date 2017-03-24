<?php

/**

  Webuddha OTA Update
  (c)2014-2016 Webuddha.com, The Holodyn Corporation - All Rights Reserved

**/

class wbSiteManager_StandaloneInstaller {

  /* ************************************************************************************************************************
   * The following configuration is defined on class instance, then used for all requests
   * ************************************************************************************************************************/
  private $config = array(
    'cache_path'  => null,
    'source_file' => null,
    'target_path' => null
    );

  /* ************************************************************************************************************************
   * Constructor
   * ************************************************************************************************************************/
  public function __construct( $config=array() ){
    if( isset($this->config) )
      foreach( $config AS $k=>$v )
        if( array_key_exists($k, $this->config) )
          $this->config[$k] = $v;
  }

  /* ************************************************************************************************************************
   * This function renders a log line
   *
   * @param String  Log Message
   * @param Boolean Exit after Message
   * @param Int     Sleep before return
   *
   * @return  void
   */
  function log( $msg, $error = false, $usleep = 0 ){
    if( !is_array($msg) )
      $msg = array($msg);
    foreach( $msg AS $m )
      echo date('H:i:s').': '.($error?'Error: ':'').$m."\n";
    ob_flush();
    if( $error )
      exit(1);
    if( $usleep )
      usleep( $usleep );
  }

  /* ************************************************************************************************************************
   * Execute
   * ************************************************************************************************************************/
  public function execute(){

    // Log
      $this->log('Executing Standalone Installer');

    // Initialize
      $time           = time();
      $cache_path     = $this->config['cache_path'];
      $source_file    = $this->config['source_file'];
      $target_path    = $this->config['target_path'];
      $install_tmp    = $cache_path . '/installer_' . $time . '/';
      $backup_path    = $cache_path . '/installer_' . $time . '_backup/';
      $install_target = $target_path . '/';

    // Required
      if (!is_file($source_file))
        $this->log('Invalid `source_file`', true);
      if (!is_dir($cache_path))
        $this->log('Invalid `cache_path`', true);
      if (!is_dir($target_path))
        $this->log('Invalid `target_path`', true);

    // Unpack File
      $this->log('Extracting Package');
      $unzipResult = $this->unzip_package( $source_file, $install_tmp );
      if (empty($unzipResult)) {
        $this->unlink( $install_tmp );
        $this->log('Extract Failed', true);
      }

    // Compare Results
      $this->log('Comparing Package');
      $compareResult = $this->compare(
        $install_tmp,
        $install_target,
        array(
          'translate' => array(
            )
          )
        );
      if (empty($compareResult)) {
        $this->unlink( $install_tmp );
        $this->log('Comparison Failed', true);
      }
      if (!empty($compareResult['errors'])) {
        $this->unlink( $install_tmp );
        $error = reset($compareResult['errors']);
        $this->log('Comparison Failed: ' . $error['msg'], true);
      }
      $this->log('Prepared to Create '. count($compareResult['create']) .', Update '. count($compareResult['update']) .' file(s)');

// Test Failure
$this->unlink( $install_tmp );
$this->log('Upgrade Failed', true);
return false;

    // Install Package
      $this->log('Upgrading Package');
      $upgradeResult = $this->upgrade(
        $install_target,
        $compareResult,
        array(
          'backup_path' => $backup_tmp
          )
        );
      if (!$upgradeResult) {
        $this->unlink( $install_tmp );
        $this->unlink( $backup_tmp );
        $this->log('Upgrade Failed', true);
      }

    // Cleanup
      $this->log('Cleanup');
      $this->unlink( $install_tmp );
      $this->unlink( $backup_tmp );

    // Complete
      $this->log('Done!');

  }

  /* ************************************************************************************************************************
   * Unzip the source_file in the destination dir
   *
   * @param   string      The path to the ZIP-file.
   * @param   string      The path where the zipfile should be unpacked, if false the directory of the zip-file is used
   * @param   boolean     Indicates if the files will be unpacked in a directory with the name of the zip-file (true) or not (false) (only if the destination directory is set to false!)
   * @param   boolean     Overwrite existing files (true) or not (false)
   * @param   string      Permission String
   *
   * @return  boolean     Succesful or not
   */
  function unzip_package($src_file, $dest_dir=false, $create_zip_name_dir=true, $overwrite=true, $permission=0644)
  {
    if( !function_exists('zip_open') ){
      $this->log("Required PHP function 'zip_open' not found.");
      return false;
    }
    if ($zip = zip_open($src_file))
    {
      if ($zip)
      {
        $splitter = ($create_zip_name_dir === true) ? "." : "/";
        if ($dest_dir === false) $dest_dir = substr($src_file, 0, strrpos($src_file, $splitter))."/";

        // Create the directories to the destination dir if they don't already exist
        if (!$this->create_dir($dest_dir))
        {
          return false;
        }

        // For every file in the zip-packet
        while ($zip_entry = zip_read($zip))
        {
          // Now we're going to create the directories in the destination directories

          // If the file is not in the root dir
          $pos_last_slash = strrpos(zip_entry_name($zip_entry), "/");
          if ($pos_last_slash !== false)
          {
            // Create the directory where the zip-entry should be saved (with a "/" at the end)
            if (!$this->create_dir($dest_dir.substr(zip_entry_name($zip_entry), 0, $pos_last_slash+1)))
            {
              return false;
            }
          }

          // Open the entry
          if (zip_entry_open($zip,$zip_entry,"r"))
          {

            // The name of the file to save on the disk
            $file_name = $dest_dir.zip_entry_name($zip_entry);

            // Check if the files should be overwritten or not
            if ($overwrite === true || $overwrite === false && !is_file($file_name))
            {
              // Get the content of the zip entry
              $fstream = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
              if( DIRECTORY_SEPARATOR != substr($file_name, strlen($file_name)-1, 1) ){
                file_put_contents($file_name, $fstream);
                chmod($file_name, $permission);
                // echo "save: ".$file_name."<br />";
              }
            }

            // Close the entry
            zip_entry_close($zip_entry);
          }
        }
        // Close the zip-file
        zip_close($zip);
      }
      else {
        return false;
      }
    }
    else
    {
      return false;
    }

    return true;
  }

  /* ************************************************************************************************************************
   * This function creates recursive directories if it doesn't already exist
   *
   * @param String  The path that should be created
   *
   * @return  void
   */
  function create_dir($path){
    if( !is_dir($path) ){
      $directories = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
      $directory_path = strpos(DIRECTORY_SEPARATOR, $path) == 0 ? DIRECTORY_SEPARATOR : '';
      foreach( $directories as $directory ){
        $directory_path .= $directory . DIRECTORY_SEPARATOR;
        if( !is_dir($directory_path) ){
          @mkdir( $directory_path );
          if( is_dir($directory_path) )
            @chmod( $directory_path, 0755 );
        }
      }
    }
    return is_dir($path);
  }

  /* ************************************************************************************************************************
   * This function deletes files / directories recursively
   *
   * @param String  The path that should be removed
   *
   * @return  void
   */
  function unlink($path, $empty = false) {
    if( substr($path,-1) == DIRECTORY_SEPARATOR )
      $path = substr($path,0,-1);
    if( !is_readable($path) ){
      $this->log("Path is NOT Readable $path");
      return false;
    }
    else if( is_dir($path) ){
      $dirHandle = opendir($path);
      if( $dirHandle ){
        while( $contents = readdir($dirHandle) ){
          if( !preg_match('/^\.+$/',$contents) ){
            $item_path = $path . DIRECTORY_SEPARATOR . $contents;
            if( is_dir($item_path) ){
              if( !$this->unlink($item_path) ){
                return false;
              }
            }
            else if( !@unlink($item_path) ){
              $this->log("Error removing $item_path");
              return false;
            }
          }
        }
        closedir($dirHandle);
      }
      if( is_dir($path) ){
        if( !@rmdir($path) ){
          $this->log("Error removing $path");
          return false;
        }
      }
      return true;
    }
    else if( !@unlink($path) ){
      $this->log("Error removing $path");
      return false;
    }
  }

  /* ************************************************************************************************************************
   * This function creates recursive directories if it doesn't already exist
   *
   * @param Array   Runtime Parameters
   *
   * @return  void
   */
  function compare( $source, $target, $params = array(), $depth=0 ){

    $log_active = isset($params['log']) ? $params['log'] : true;
    $result = array(
      'errors' => array(),
      'update' => array(),
      'create' => array()
      );

    // Check Source Exists
    if( !is_dir($source) )
      $this->log("Folder $source NOT FOUND", true);
    // Check Target Exists or is Writable
    if( !is_dir($target) )
      $result['create'][] = array('source' => $source, 'target' => $target);
    else if( !is_writable($target) )
      $result['errors'][] = array('source' => $source, 'target' => $target, 'msg' => implode( DIRECTORY_SEPARATOR, array_slice( explode( DIRECTORY_SEPARATOR, $target ), count(explode( DIRECTORY_SEPARATOR, $target )) - ($depth + 1) )). ' is NOT WRITABLE');
    $dirList = scandir( $source );
    foreach( $dirList AS $dirItem ){
      if( !preg_match('/^\.+$/',$dirItem) ){
        // Translate Path
        $thisTarget = $target;
        $dirItem_target = $dirItem;
        if( isset($params['translate']) ){
          $tmp_source = $source . $dirItem;
          foreach( $params['translate'] AS $tranKey => $tranTarget ){
            if( strlen($tmp_source) >= strlen($tranKey) ){
              if( count( explode( DIRECTORY_SEPARATOR, $tranKey ) ) == ($depth + 1)
                && substr($tmp_source, strlen($tmp_source)-strlen($tranKey)) == $tranKey ){
                $thisTarget = substr( $thisTarget.$dirItem_target, 0, strlen($thisTarget.$dirItem_target) - strlen($tranKey));
                $dirItem_target = $tranTarget;
                break;
              }
            }
          }
        }
        // Check Source is Directory
        if( is_dir( $source . $dirItem ) ){
          $tmpResult = $this->compare( $source . $dirItem . DIRECTORY_SEPARATOR, $thisTarget . $dirItem_target . DIRECTORY_SEPARATOR, $params, $depth + 1 );
          $result['errors'] = array_merge( $result['errors'], $tmpResult['errors'] );
          $result['update'] = array_merge( $result['update'], $tmpResult['update'] );
          $result['create'] = array_merge( $result['create'], $tmpResult['create'] );
        }
        // Check File NOT Exists
        else if( !file_exists($thisTarget . $dirItem_target) )
          $result['create'][] = array('source' => $source . $dirItem, 'target' => $thisTarget . $dirItem_target);
        // Check Target is Writable
        else if( !is_writable($thisTarget . $dirItem_target) )
          $result['errors'][] = array('source' => $source . $dirItem, 'target' => $thisTarget . $dirItem_target, 'msg' => implode( DIRECTORY_SEPARATOR, array_slice( explode( DIRECTORY_SEPARATOR, $thisTarget . $dirItem_target ), count(explode( DIRECTORY_SEPARATOR, $thisTarget . $dirItem_target )) - ($depth + 1) )). ' is NOT WRITABLE');
        // Target Exists and Is Writable
        else
          $result['update'][] = array('source' => $source . $dirItem, 'target' => $thisTarget . $dirItem_target);
      }
    }
    return $result;
  }

  /* ************************************************************************************************************************
   * This function creates recursive directories if it doesn't already exist
   *
   * @param Array   Runtime Parameters
   *
   * @return  void
   */
  function upgrade( $basePath, $procList, $params=array() ){

    // Prepare Log
      $log = isset($params['log']) ? $params['log'] : true;
      $backupPath = isset($params['backup_path']) ? $params['backup_path'] : null;
      $backupLog = array(
        'created_dir'   => array(),
        'created_file'  => array(),
        'updated_dir'   => array(),
        'updated_file'  => array()
        );

    // Validation
      if( empty($procList) ){
        $this->log("Missing Process List");
        return false;
      }
      if( $backupPath ){
        if( $log ) $this->log("Checking Backup Path");
        if( !is_dir($backupPath) && !file_exists($backupPath) )
          $this->create_dir($backupPath);
        if( !is_writable($backupPath) ){
          $this->log("Backup Path $backupPath NOT WRITABLE");
          return false;
        }
        if( $log ) $this->log("Backup Path $backupPath is READY");
      }

    // From here on we are committed - if the backup failed we are restoring
    // Flag for Restoration on Error
      $restoreBackup = false;

    // Validate / Create Folders
      if( !$restoreBackup ){
        if( $log ) $this->log("Creating ".count($procList['create'])." file(s) and folder(s)");
        if( isset($procList['create']) ){
          foreach( $procList['create'] AS $create_row ){
            if( is_dir($create_row['source']) ){
              if( is_dir($create_row['target']) ){
                if( $log ) $this->log("Target Path Exists: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              else if( file_exists($create_row['target']) ){
                if( $log ) $this->log("Target Path is a File: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              else if( !$this->create_dir($create_row['target']) ){
                if( $log ) $this->log("Failed to create folder: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              $backupLog['created_dir'][] = $create_row['target'];
            }
            else {
              if( is_dir($create_row['target']) ){
                if( $log ) $this->log("Target Path is a Folder: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              else if( file_exists($create_row['target']) ){
                if( $log ) $this->log("Target File Exists: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              else if( !@copy($create_row['source'], $create_row['target']) ){ // !true ){ //
                if( $log ) $this->log("Failed to copy file: ".$create_row['target']);
                $restoreBackup = true;
                break;
              }
              $backupLog['created_file'][] = $create_row['target'];
            }
          }
        }
        if( $log ) $this->log(" -> Created ".count($backupLog['created_dir'])." folders(s)");
        if( $log ) $this->log(" -> Created ".count($backupLog['created_file'])." file(s)");
      }

    // Validate / Create Files
      if( !$restoreBackup ){
        if( $log ) $this->log("Updating ".count($procList['update'])." file(s) and folder(s)");
        if( isset($procList['update']) ){
          foreach( $procList['update'] AS $update_row ){
            $backup_target = $backupPath . substr($update_row['target'], strlen($basePath));
            $backup_target_info = pathinfo( $backup_target );
            $backup_target_dir = $backup_target_info['dirname'] . DIRECTORY_SEPARATOR;
            if( !is_dir($backup_target_dir) && !$this->create_dir($backup_target_dir) ){
              if( $log ) $this->log("Failed to create backup folder: ".$backup_target_dir);
              break;
            }
            if( is_dir($update_row['source']) ){
              if( file_exists($update_row['target']) ){
                if( $log ) $this->log("Target Path is a File: ".$update_row['target']);
                break;
              }
              else if( !$this->create_dir($backup_target) ){
                if( $log ) $this->log("Failed to backup folder: ".$backup_target);
                break;
              }
              else if( !$this->create_dir($update_row['target']) ){
                if( $log ) $this->log("Failed to update folder: ".$update_row['target']);
                break;
              }
              $backupLog['updated_dir'][] = $update_row['target'];
            }
            else {
              if( is_dir($update_row['target']) ){
                if( $log ) $this->log("Target Path is a Folder: ".$update_row['target']);
                break;
              }
              else if( !@copy($update_row['target'], $backup_target) ){
                if( $log ) $this->log("Failed to backup file: ".$backup_target);
                break;
              }
              else if( !@copy($update_row['source'], $update_row['target']) ){ // !true ){ //
                if( $log ) $this->log("Failed to copy file: ".$update_row['target']);
                break;
              }
              $backupLog['updated_file'][] = $update_row['target'];
            }
          }
        }
        if( $log ) $this->log(" -> Updated ".count($backupLog['updated_dir'])." folders(s)");
        if( $log ) $this->log(" -> Updated ".count($backupLog['updated_file'])." file(s)");
      }

    // Restore / Remove Backup
      if( $restoreBackup ){
        $this->log(" ** Error Detected - Restoring Backup");
        $this->_revertBackup( $basePath, $backupPath, $backupLog, $params );
        return false;
      }
      $this->unlink( $backupPath );

    // Success
      return true;

  }

  /* ************************************************************************************************************************
   * ...
   *
   * @param Array   Runtime Parameters
   *
   * @return  void
   */
  private function _revertBackup( $basePath, $backupPath, $backupLog, $params=array() ){

    $log = isset($params['log']) ? $params['log'] : true;

    $count = 0;
    if( $log ) $this->log("Removing ".count($backupLog['created_file'])." file(s)");
    foreach( $backupLog['created_file'] AS $file ){
      if( !file_exists($file) || !$this->unlink($file) ){
        if( $log ) $this->log(" -> Failed to remove file: ".$file);
      }
      else
        $count++;
    }
    if( $log ) $this->log(" -> Removed ".$count." file(s)");

    $count = 0;
    if( $log ) $this->log("Removing ".count($backupLog['created_dir'])." folder(s)");
    foreach( $backupLog['created_dir'] AS $folder ){
      if( !is_dir($folder) || !$this->unlink($folder) ){
        if( $log ) $this->log(" -> Failed to remove folder: ".$folder);
      }
      else
        $count++;
    }
    if( $log ) $this->log(" -> Removed ".$count." folder(s)");

    $count = 0;
    if( $log ) $this->log("Restoring ".count($backupLog['updated_file'])." file(s)");
    foreach( $backupLog['updated_file'] AS $file ){
      $buFile = $backupPath . substr($file, strlen($basePath));
      if( !file_exists($buFile) ){
        if( $log ) $this->log(" -> Failed to locate backup file: ".$buFile);
        }
      else if( !@copy($buFile, $file) ){
        if( $log ) $this->log(" -> Failed to restore file: ".$file);
      }
      else
        $count++;
    }
    if( $log ) $this->log(" -> Restored ".$count." file(s)");

  }

}
