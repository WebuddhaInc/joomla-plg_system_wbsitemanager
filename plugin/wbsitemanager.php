<?php

// check that we have access
  defined( '_JEXEC' ) or die( 'Restricted access' );

// Load System
  jimport( 'joomla.plugin.plugin' );

// Class
  class plgSystemWbSiteManager extends JPlugin {

    public function __construct(&$subject, $config){
      parent::__construct($subject, $config);
    }

  }