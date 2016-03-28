<?php

abstract class JInstallerStandaloneProvider extends JObject {

  public function __construct( $package, $params ){
    $this->set( 'package', $package );
    $this->set( 'params', $params );
  }

  public function appendBuildList( &$buildList ){
  }

}