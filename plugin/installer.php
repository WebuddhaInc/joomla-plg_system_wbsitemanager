<?php

class PlgsystemwbsitemanagerInstallerScript {

  public function update(){
    self::install();
  }

  public function install(){
    if( file_exists(__DIR__ . '/cli/autoupdate.php') ){
      @copy(__DIR__ . '/cli/autoupdate.php', JPATH_ROOT . '/cli/autoupdate.php');
    }
  }

  public function uninstall(){
    if( file_exists(JPATH_ROOT . '/cli/autoupdate.php') ){
      @unlink(JPATH_ROOT . '/cli/autoupdate.php');
    }
  }

}