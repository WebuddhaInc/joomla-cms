<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_installer
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Extension Manager Install Model
 *
 * @since  1.5
 */
class InstallerModelInstaller extends JModelLegacy
{

  /**
   * [initialize description]
   * @return [type] [description]
   */
  public function initialize( $params ){

    // Stage Model
      $this->setState( 'package', $params['package'] );
      $this->setState( 'params', new JRegistry($params['params']) );

    // Identify standalog staging location
      $root_path = JPATH_ROOT;
      $tmp_path  = JFactory::getConfig()->get('tmp_path');
      if( strpos($tmp_path, $root_path) === 0 ){
        $this->setState('installer_path', $tmp_path . '/com_installer/');
        $this->setState('installer_site', substr($tmp_path,strlen($root_path)) . '/com_installer/');
      }
      else {
        $this->setState('installer_path', JPATH_ROOT . '/media/com_installer/standalone/');
        $this->setState('installer_site', '/media/com_installer/standalone/');
      }

    // Reset Installer
      if( !$this->reset() ){
        return false;
      }

    // Create Installer
      if( !$this->createInstaller() ){
        return false;
      }

    // Prepare Session
      $this->storeSessionState();
      JFactory::getApplication()->setUserState('com_installer.ajaxurl', $this->getState('installer_site') . 'installer.php');
      JFactory::getApplication()->setUserState('com_installer.returnurl', 'index.php?option=com_installer&task=installer.finalise');

    // Success
      return true;

  }

  /**
   * [finalize description]
   * @param  [type] $params [description]
   * @return [type]         [description]
   */
  public function finalize( $params ){

    // Stage Model
      $this->stageSessionState();
      $this->setState( 'success', $params['success'] );
      $this->setState( 'message', $params['message'] );

    // Stage
      $package = $this->getState('package');

    // This event allows a custom a post-flight:
      JEventDispatcher::getInstance()
        ->trigger('onInstallerAfterInstaller', array($this, $package, null, $this->getState('success'), $this->getState('message')));

    // Cleanup the package files
      if( isset($package['packagefile']) ){
        if( !is_file($package['packagefile']) ){
          $config = JFactory::getConfig();
          $package['packagefile'] = $config->get('tmp_path') . '/' . $package['packagefile'];
        }
        JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
      }

    // Finalize Model
      $this->reset();

  }

  /**
   * [reset description]
   * @return [type] [description]
   */
  public function reset(){

    // Allow provider to reset
      $provider = $this->getInstallerProvider();
      $provider->reset();

    // Delete Previous Files
      $installer_path = $this->getState('installer_path');
      if( is_readable($installer_path . 'installer.php') ){
        @unlink($installer_path . 'installer.php');
      }
      if( is_dir($installer_path) ){
        @rmdir($installer_path);
      }

    // Reset Session
      JFactory::getApplication()->setUserState('com_installer', null);

    // Complete
      return true;

  }

  /**
   * [storeSessionState description]
   * @return [type] [description]
   */
  public function storeSessionState(){

    // Stage
      $app = JFactory::getApplication();
      $state = $this->getState();

    // Push to Session
      foreach( $state AS $key => $val ){
        if( strpos($key, '_') !== 0 ){
          $app->setUserState( 'com_installer.' . $key, $state->{$key} );
        }
      }

  }

  /**
   * [stageSessionState description]
   * @return [type] [description]
   */
  public function stageSessionState(){

    // Stage
      $installer = JFactory::getApplication()->getUserState('com_installer');

    // Pull from Session
      if( $installer ){
        foreach( $installer AS $key => $val ){
          $this->setState( $key, $installer->{$key} );
        }
      }

  }

  /**
   * [getInstallerProvider description]
   * @return [type] [description]
   */
  public function getInstallerProvider(){
    require_once JPATH_ADMINISTRATOR . '/components/com_installer/provider/standaloneProvider.php';
    require_once JPATH_ADMINISTRATOR . '/components/com_installer/provider/akeeba.php';
    return new JInstallerStandaloneProviderAkeeba( array(
      'installer_path' => $this->getState('installer_path'),
      'installer_site' => $this->getState('installer_site'),
      'package'        => $this->getState('package'),
      'params'         => $this->getState('params')
      ));
  }

  /**
   * [createInstaller description]
   * @return [type] [description]
   */
  public function createInstaller(){

    // Stage
      $app = JFactory::getApplication();
      $installer_path = $this->getState('installer_path');

    // Copy Installer to Media
      if(
        (
        is_dir($installer_path)
        ||
        @mkdir($installer_path, 0755, true)
        )
        &&
        is_writeable($installer_path)
        ){

        // Remove Existing
          if( is_readable($installer_path . 'installer.php') ){
            @unlink($installer_path . 'installer.php');
          }
          if( is_readable($installer_path . 'installer.php') ){
            $app->enqueueMessage(JText::_('COM_INSTALLER_STANDALONE_NOT_WRITABLE'), 'error');
            return false;
          }

        // Prepare build instructions
        // TODO: This is ugly and needs a class
          $targetPathRegex = trim(str_replace('/', '.', $this->getState('installer_site')), '.');
          $installerBuildList = array(
            array(
              "",
              "/*",
              "  Generated by " . __FILE__,
              "  " . gmdate('Y-m-d H:i:s'),
              "*/",
              "",
              "if( !preg_match('/{$targetPathRegex}/', __DIR__) ){ exit; }",
              ""
              )
            );

        // Load installer build extensions
        // TODO: This is hardcoded and needs logic
          $provider = $this->getInstallerProvider();
          $provider->appendBuildList( $installerBuildList );

        // Append Main Runtime to List
          $installerBuildList[] = JPATH_ADMINISTRATOR . '/components/com_installer/standalone/installer.php';

        // Build installer file
        // TODO: There is probably a good standard for replacing this
          $fh = fopen( $installer_path . 'installer.php', 'w+' );
          if( $fh ){
            $bytesWritten = 0;
            foreach( $installerBuildList AS $item ){
              if( !$bytesWritten ){
                $tmpBuffer = '<?php';
                fwrite( $fh, $tmpBuffer );
                $bytesWritten += strlen( $tmpBuffer );
              }
              if( is_array($item) ){
                fwrite( $fh, "\n\n");
                fwrite( $fh, "/*\n  Code Block \n*/");
                fwrite( $fh, "\n\n");
                fwrite( $fh, implode("\n", $item) );
              }
              else if( is_string($item) && is_readable($item) ){
                $fa = fopen( $item, 'r' );
                if( $fa ){
                  fwrite( $fh, "\n\n");
                  fwrite( $fh, "/*\n  Source\n  ".$item.' / '.filesize($item)."\n*/");
                  fwrite( $fh, "\n\n");
                  $bytesRead = 0;
                  while( !feof($fa) && $buffer = fread($fa, 1024 * 10) ){
                    if( $bytesRead == 0 && preg_match('/^\<\?(php|)[\s\r\n]+/',$buffer) ){
                      $bytesRead += strlen( $buffer );
                      $tmpBuffer = preg_replace('/^\<\?(php|)[\s\r\n]+/', '', $buffer);
                      $bytesWritten += strlen( $tmpBuffer );
                      $buffer = $tmpBuffer;
                    }
                    else {
                      $bytesRead += strlen( $buffer );
                      $bytesWritten += strlen( $buffer );
                    }
                    fwrite( $fh, $buffer );
                  }
                  fclose( $fa );
                }
                else {
                  $app->enqueueMessage(JText::_('COM_INSTALLER_STANDALONE_BUILD_FAILED'), 'error');
                  return false;
                }
              }
            }
            fclose( $fh );
          }
          else {
            $app->enqueueMessage(JText::_('COM_INSTALLER_STANDALONE_NOT_WRITABLE'), 'error');
            return false;
          }
          if( !is_readable($installer_path . 'installer.php') ){
            $app->enqueueMessage(JText::_('COM_INSTALLER_STANDALONE_NOT_WRITABLE'), 'error');
            return false;
          }

      }
      else {
        $app->enqueueMessage(JText::_('COM_INSTALLER_STANDALONE_NOT_WRITABLE'), 'error');
        return false;
      }

    // Complete
      return true;

  }

}
