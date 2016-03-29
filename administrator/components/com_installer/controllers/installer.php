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
     *
     * We are going to create a standalone installer, then load a dialog
     * to monitor that installer.
     *
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
      if( !$model->initialize( array('package' => $package) ) ){
        $app->enqueueMessage(JText::_('COM_INSTALLER_UNABLE_TO_INITIALIZE_INSTALLER'), 'error');
        $this->setRedirect(JRoute::_('index.php?option=com_installer', false));
        return false;
      }

    // Stage to View
      $view->setModel($model, true);

    // Trigger View
      $view->display();

  }

  /**
   * [finalise description]
   * @return [type] [description]
   */
  public function finalise(){

    /**
     *
     * The installer has finished and we are not going to cleanup.
     *
     * NO package specific post-processing can be performed here.
     *
     * This operation requires a successful return to the previous session,
     * which could be broken by the update.
     *
     */

    // Stage
      $app     = JFactory::getApplication();
      $model   = $this->getModel('installer');
      $success = $app->input->getInt('success') ? 1 : 0;
      $message = $app->input->getVar('message');

    // Finalize Model
      $model->finalize( array(
        'success' => $success,
        'message' => $message
        ) );

    // We're done
      if( $success ){
        $app->enqueueMessage(sprintf(JText::_('COM_INSTALLER_INSTALL_SUCCESS'), $package_name), 'message');
        $this->setRedirect(JRoute::_('index.php?option=com_installer', false));
      }
      else {
        $app->enqueueMessage(sprintf(JText::_('COM_INSTALLER_INSTALL_ERROR'), $message), 'error');
        $this->setRedirect(JRoute::_('index.php?option=com_installer', false));
      }
      return false;

  }

}
