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
 * Installer controller for Joomla! installer class.
 *
 * @since  1.5
 */
class InstallerControllerInstaller extends JControllerLegacy
{

  /**
   * [install description]
   * @return [type] [description]
   */
  public function install()
  {

    /**
     * This installer will create
     * This installer will monitor activity on a forked installer
     * The forked installer API
     *   ping
     *   install
     */

    // Check for request forgeries.
      JSession::checkToken('get') or jexit(JText::_('JINVALID_TOKEN'));

    // Stage
      $app   = JFactory::getApplication();
      $model = $this->getModel('installer');
      $view  = $this->getView('installer', 'html');

    // Stage Package
      $package = $app->getUserState('com_installer.package');
      if( empty($package) || empty($package['type']) ){
        $app->enqueueMessage(JText::_('COM_INSTALLER_UNABLE_TO_FIND_INSTALL_PACKAGE'), 'error');
        $this->setRedirect(JRoute::_('index.php?option=com_installer', false));
        return false;
      }

    // Confirm Valid Package

    // Check Request Authorization

    // Initialize Installer
      if( !$model->initialize( $package, array() ) ){
        $app->enqueueMessage(JText::_('COM_INSTALLER_UNABLE_TO_INITIALIZE_INSTALLER'), 'error');
        $this->setRedirect(JRoute::_('index.php?option=com_installer', false));
        return false;
      }

    // Stage to View
      $view->setModel($model, true);

    // Trigger View
      $view->display();

  }

  public function finalise(){
    die(__LINE__.': '.__FILE__);
  }

}
