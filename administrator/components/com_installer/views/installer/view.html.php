<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_installer
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

include_once JPATH_ADMINISTRATOR . '/components/com_installer/views/default/view.php';
require_once JPATH_ADMINISTRATOR . '/components/com_installer/helpers/installer.php';

/**
 * Extension Manager Install View
 *
 * @since  1.5
 */
class InstallerViewInstaller extends InstallerViewDefault
{

  /**
   * [display description]
   * @param  [type] $tpl [description]
   * @return [type]      [description]
   */
  public function display($tpl = null)
  {
    $paths = new stdClass;
    $paths->first = '';
    $state = $this->get('state');
    $this->paths = &$paths;
    $this->state = &$state;
    parent::display($tpl);
  }

  /**
   * [addToolbar description]
   */
  protected function addToolbar()
  {
    JToolbarHelper::title(JText::_('COM_INSTALLER_HEADER_INSTALLER'), 'puzzle install');
    /*
    JToolbarHelper::custom('discover.install', 'upload', 'upload', 'JTOOLBAR_INSTALL', true);
    InstallerHelper::addSubmenu('installer');
    parent::addToolbar();
    */
  }

}
