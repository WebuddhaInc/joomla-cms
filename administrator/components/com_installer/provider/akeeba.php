<?php

class JInstallerStandaloneProviderAkeeba extends JInstallerStandaloneProvider {

  /**
   * [reset description]
   * @return [type] [description]
   */
  public function reset(){

    // Delete Previous Files
      $installer_path = $this->get('installer_path');
      if( is_readable($installer_path . 'debug.txt') ){
        @unlink($installer_path . 'debug.txt');
      }

  }

  /**
   * [appendBuildList description]
   * @param  [type] &$buildList [description]
   * @return [type]             [description]
   */
  public function appendBuildList( &$buildList ){
    $buildList = array_merge($buildList, array(
      $this->createInstallerConfig(),
      __DIR__ . '/akeeba/init.php',
      __DIR__ . '/akeeba/master.php',
      __DIR__ . '/akeeba/text.php',
      __DIR__ . '/akeeba/json.php',
      __DIR__ . '/akeeba/functions.php',
      __DIR__ . '/akeeba/factory.php',
      __DIR__ . '/akeeba/coreTimer.php',
      __DIR__ . '/akeeba/abstractObject.php',
      __DIR__ . '/akeeba/abstractPart.php',
      __DIR__ . '/akeeba/abstractPartObserver.php',
      __DIR__ . '/akeeba/abstractUnarchiver.php',
      __DIR__ . '/akeeba/abstractPostproc.php',
      __DIR__ . '/akeeba/postProdDirect.php',
      __DIR__ . '/akeeba/postProcFtp.php',
      __DIR__ . '/akeeba/postProcSftp.php',
      __DIR__ . '/akeeba/postProcHybrid.php',
      __DIR__ . '/akeeba/unarchiverJpa.php',
      __DIR__ . '/akeeba/unarchiverJps.php',
      __DIR__ . '/akeeba/unarchiverZip.php',
      __DIR__ . '/akeeba/unarchiverFolder.php',
      __DIR__ . '/akeeba/utilsListener.php',
      __DIR__ . '/akeeba/encryotionAes.php',
      __DIR__ . '/akeeba/controller.php'
      ));
  }

  /**
   * Create installer configuration file.
   *
   * @param   string  $basename  Optional base path to the file.
   *
   * @return  boolean True if successful; false otherwise.
   *
   * @since  2.5.4
   */
  private function createInstallerConfig( $filePath = null )
  {

    // Stage
      $app        = JFactory::getApplication();
      $config     = JFactory::getConfig();
      $method     = $app->input->get('method', 'direct');
      $package    = $this->get('package');
      $params     = $this->get('params');
      $siteroot   = JPATH_SITE;
      $dryrun     = 0;
      $tempdir    = $config->get('tmp_path');
      if( $filePath === true )
        $filePath = JPATH_ROOT . '/media/com_installer/standalone/installer.config.php';

    // Create / Store Password
      $password = JUserHelper::genRandomPassword(32);
      $app->setUserState('com_installer.password', $password);

    // Identify File Location / Size
      $file = $package['dir'];
      $app->setUserState('com_installer.filesize', $this->__foldersize($file));

    // Identify Filetype
      $filetype = is_dir($file) ? 'folder' : 'file';

    // Remove the old file, if it's there...
      if( !is_null($filePath) && JFile::exists($filePath) ){
        JFile::delete($filePath);
      }

    // Start Output Generation
      $config_output = array();
      $config_output[] = "\$restoration_setup = array(";

    // Start Config Generation
      $config_output_config = array();
      $config_output_config[] ="'kickstart.security.password' => '$password'";
      $config_output_config[] ="'kickstart.tuning.max_exec_time' => '5'";
      $config_output_config[] ="'kickstart.tuning.run_time_bias' => '75'";
      $config_output_config[] ="'kickstart.tuning.min_exec_time' => '0'";
      $config_output_config[] ="'kickstart.procengine' => '$method'";
      $config_output_config[] ="'kickstart.setup.sourcefile' => '$file'";
      $config_output_config[] ="'kickstart.setup.destdir' => '$siteroot'";
      $config_output_config[] ="'kickstart.setup.restoreperms' => '0'";
      $config_output_config[] ="'kickstart.setup.filetype' => '$filetype'";
      $config_output_config[] ="'kickstart.setup.dryrun' => '$dryrun'";

    // Push FTP Environment (do a lot of folder validation)
      if( $method == 'ftp' ){

        /*
         * Fetch the FTP parameters from the request. Note: The password should be
         * allowed as raw mode, otherwise something like !@<sdf34>43H% would be
         * sanitised to !@43H% which is just plain wrong.
         */
          $ftp_host = $app->input->get('ftp_host', '');
          $ftp_port = $app->input->get('ftp_port', '21');
          $ftp_user = $app->input->get('ftp_user', '');
          $ftp_pass = $app->input->get('ftp_pass', '', 'default', 'none', 2);
          $ftp_root = $app->input->get('ftp_root', '');

        /**
         * Below validates the availability of a remote tmp folder for writing
         */

        // Is the tempdir really writable?
          $writable = @is_writeable($tempdir);
          if ($writable) {
            // Let's be REALLY sure.
            $fp = @fopen($tempdir . '/test.txt', 'w');
            if ($fp === false) {
              $writable = false;
            }
            else {
              fclose($fp);
              unlink($tempdir . '/test.txt');
            }
          }

        // If the tempdir is not writable, create a new writable subdirectory.
          if (!$writable) {
            $FTPOptions = JClientHelper::getCredentials('ftp');
            $ftp = JClientFtp::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);
            $dest = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $tempdir . '/admintools'), '/');
            if (!@mkdir($tempdir . '/admintools')){
              $ftp->mkdir($dest);
            }
            if (!@chmod($tempdir . '/admintools', 511)){
              $ftp->chmod($dest, 511);
            }
            $tempdir .= '/admintools';
          }

        // Just in case the temp-directory was off-root, try using the default tmp directory.
          $writable = @is_writeable($tempdir);
          if (!$writable) {
            $tempdir = JPATH_ROOT . '/tmp';
            // Does the JPATH_ROOT/tmp directory exist?
            if (!is_dir($tempdir)) {
              JFolder::create($tempdir, 511);
              JFile::write($tempdir . '/.htaccess', "order deny,allow\ndeny from all\nallow from none\n");
            }
            // If it exists and it is unwritable, try creating a writable admintools subdirectory.
            if (!is_writable($tempdir)) {
              $FTPOptions = JClientHelper::getCredentials('ftp');
              $ftp = JClientFtp::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);
              $dest = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $tempdir . '/admintools'), '/');
              if (!@mkdir($tempdir . '/admintools')) {
                $ftp->mkdir($dest);
              }
              if (!@chmod($tempdir . '/admintools', 511)) {
                $ftp->chmod($dest, 511);
              }
              $tempdir .= '/admintools';
            }
          }

        // If we still have no writable directory, we'll try /tmp and the system's temp-directory.
          $writable = @is_writeable($tempdir);
          if (!$writable) {
            if (@is_dir('/tmp') && @is_writable('/tmp')) {
              $tempdir = '/tmp';
            }
            else {
              // Try to find the system temp path.
              $tmpfile = @tempnam("dummy", "");
              $systemp = @dirname($tmpfile);
              @unlink($tmpfile);
              if (!empty($systemp)) {
                if (@is_dir($systemp) && @is_writable($systemp)) {
                  $tempdir = $systemp;
                }
              }
            }
          }

        // Did we Succeed?
          if( !empty($tempdir) ){
            $config_output_config[] = "'kickstart.ftp.ssl' => '0'";
            $config_output_config[] = "'kickstart.ftp.passive' => '1'";
            $config_output_config[] = "'kickstart.ftp.host' => '$ftp_host'";
            $config_output_config[] = "'kickstart.ftp.port' => '$ftp_port'";
            $config_output_config[] = "'kickstart.ftp.user' => '$ftp_user'";
            $config_output_config[] = "'kickstart.ftp.pass' => '$ftp_pass'";
            $config_output_config[] = "'kickstart.ftp.dir' => '$ftp_root'";
            $config_output_config[] = "'kickstart.ftp.tempdir' => '$tempdir'";
          }

      }

    // Append Config Options to Output;
      if( $config_output_config ){
        $config_output[] = implode(",\n", $config_output_config);
      }
      $config_output[] = ');';

    // Return Output?
      if( is_null($filePath) )
        return $config_output;

    // Prepend for File
      $config_output_header = array();
      $config_output_header[] = "<?php";
      $config_output_header[] = "";
      $config_output_header[] = "/*";
      $config_output_header[] = "  Generated by " . __FILE__;
      $config_output_header[] = "  " . gmdate('Y-m-d H:i:s');
      $config_output_header[] = "*/";
      $config_output_header[] = "";
      $config_output = array_merge( $config_output_header, $config_output );

    // Compile Config Output
      $config_output = implode("\n", $config_output);

    // Write new file. First try with JFile.
      $result = JFile::write($filePath, $config_output);

    // In case JFile used FTP but direct access could help.
      if (!$result){
        if (function_exists('file_put_contents')) {
          $result = @file_put_contents($filePath, $config_output);
          if ($result !== false) {
            $result = true;
          }
        }
        else {
          $fp = @fopen($filePath, 'wt');
          if ($fp !== false) {
            $result = @fwrite($fp, $config_output);
            if ($result !== false) {
              $result = true;
            }
            @fclose($fp);
          }
        }
      }

    // Complete
      return $result;

  }

  protected function __foldersize( $base, $path=null ){
    $size = 0;
    $files = scandir( $base.'/'.$path );
    foreach( $files AS $file ){
      if( !preg_match('/^\.+$/', $file) ){
        if( is_dir($base.'/'.$path.$file) ){
          $size += $this->__foldersize($base, $path.$file.'/');
        }
        else if( is_readable($base.'/'.$path.$file) ){
          $size += filesize($base.'/'.$path.$file);
        }
      }
    }
    return $size;
  }

}