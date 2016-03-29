<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_installer
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Include jQuery.
JHtml::_('jquery.framework');

// Load the scripts
JHtml::script('com_installer/json2.js', false, true, false);
JHtml::script('com_installer/encryption.js', false, true, false);
JHtml::script('com_installer/update.js', false, true, false);

$password  = JFactory::getApplication()->getUserState('com_installer.password', null);
$filesize  = JFactory::getApplication()->getUserState('com_installer.filesize', null);
$ajaxUrl   = JFactory::getApplication()->getUserState('com_installer.ajaxurl', null);
$returnUrl = JFactory::getApplication()->getUserState('com_installer.returnurl', null);

JFactory::getDocument()->addScriptDeclaration("
var com_installer_password = '$password';
var com_installer_totalsize = '$filesize';
var com_installer_ajax_url = '$ajaxUrl';
var com_installer_return_url = '$returnUrl';
jQuery(document).ready(function(){ window.pingExtract(); });
");

?>

<p class="nowarning"><?php echo JText::_('COM_INSTALLER_VIEW_INSTALLER_INPROGRESS') ?></p>
<div id="update-progress">
  <div id="extprogress">
    <div id="progress" class="progress progress-striped active">
      <div id="progress-bar" class="bar bar-success" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <div class="extprogrow">
      <span class="extvalue" id="extpercent"></span>
      <span class="extlabel"><?php echo JText::_('COM_INSTALLER_VIEW_INSTALLER_PERCENT'); ?></span>
    </div>
    <div class="extprogrow">
      <span class="extvalue" id="extbytesin"></span>
      <span class="extlabel"><?php echo JText::_('COM_INSTALLER_VIEW_INSTALLER_BYTESREAD'); ?></span>
    </div>
    <div class="extprogrow">
      <span class="extvalue" id="extbytesout"></span>
      <span class="extlabel"><?php echo JText::_('COM_INSTALLER_VIEW_INSTALLER_BYTESEXTRACTED'); ?></span>
    </div>
    <div class="extprogrow">
      <span class="extvalue" id="extfiles"></span>
      <span class="extlabel"><?php echo JText::_('COM_INSTALLER_VIEW_INSTALLER_FILESEXTRACTED'); ?></span>
    </div>
  </div>
</div>
