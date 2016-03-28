<?php

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

// OS specific
  defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// Unarchiver run states
  define('AK_STATE_NOFILE', 0); // File header not read yet
  define('AK_STATE_HEADER', 1); // File header read; ready to process data
  define('AK_STATE_DATA', 2); // Processing file data
  define('AK_STATE_DATAREAD', 3); // Finished processing file data; ready to post-process
  define('AK_STATE_POSTPROC', 4); // Post-processing
  define('AK_STATE_DONE', 5); // Done with post-processing

/* Windows system detection */
  if (!defined('_AKEEBA_IS_WINDOWS')) {
    if (function_exists('php_uname')) {
      define('_AKEEBA_IS_WINDOWS', stristr(php_uname(), 'windows'));
    }
    else {
      define('_AKEEBA_IS_WINDOWS', DIRECTORY_SEPARATOR == '\\');
    }
  }

// Get the file's root
  if (!defined('KSROOTDIR')) {
    define('KSROOTDIR', dirname(__FILE__));
  }
  if (!defined('KSLANGDIR')) {
    define('KSLANGDIR', KSROOTDIR);
  }

// Make sure the locale is correct for basename() to work
if( function_exists('setlocale')) {
  @setlocale(LC_ALL, 'en_US.UTF8');
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The Master Setup will read the configuration parameters from installer.config.php or
 * the JSON-encoded "configuration" input variable and return the status.
 *
 * @return bool True if the master configuration was applied to the Factory object
 */
function masterSetup()
{
  // ------------------------------------------------------------
  // 1. Import basic setup parameters
  // ------------------------------------------------------------

  $ini_data = null;

  // In installer.php mode, require installer.config.php or fail
  if (!defined('KICKSTART'))
  {
    // This is the standalone mode, used by Akeeba Backup Professional. It looks for a installer.config.php
    // file to perform its magic. If the file is not there, we will abort.
    $setupFile = 'installer.config.php';

    if (!file_exists($setupFile))
    {
      AKFactory::set('kickstart.enabled', false);

      return false;
    }

    // Load installer.config.php. It creates a global variable named $restoration_setup
    require_once $setupFile;

    $ini_data = $restoration_setup;

    if (empty($ini_data))
    {
      // No parameters fetched. Darn, how am I supposed to work like that?!
      AKFactory::set('kickstart.enabled', false);

      return false;
    }

    AKFactory::set('kickstart.enabled', true);
  }
  else
  {
    // Maybe we have $restoration_setup defined in the head of kickstart.php
    global $restoration_setup;

    if (!empty($restoration_setup) && !is_array($restoration_setup))
    {
      $ini_data = AKText::parse_ini_file($restoration_setup, false, true);
    }
    elseif (is_array($restoration_setup))
    {
      $ini_data = $restoration_setup;
    }
  }

  // Import any data from $restoration_setup
  if (!empty($ini_data))
  {
    foreach ($ini_data as $key => $value)
    {
      AKFactory::set($key, $value);
    }
    AKFactory::set('kickstart.enabled', true);
  }

  // Reinitialize $ini_data
  $ini_data = null;

  // ------------------------------------------------------------
  // 2. Explode JSON parameters into $_REQUEST scope
  // ------------------------------------------------------------

  // Detect a JSON string in the request variable and store it.
  $json = getQueryParam('json', null);

  // Remove everything from the request, post and get arrays
  if (!empty($_REQUEST))
  {
    foreach ($_REQUEST as $key => $value)
    {
      unset($_REQUEST[$key]);
    }
  }

  if (!empty($_POST))
  {
    foreach ($_POST as $key => $value)
    {
      unset($_POST[$key]);
    }
  }

  if (!empty($_GET))
  {
    foreach ($_GET as $key => $value)
    {
      unset($_GET[$key]);
    }
  }

  // Decrypt a possibly encrypted JSON string
  $password = AKFactory::get('kickstart.security.password', null);

  if (!empty($json))
  {
    if (!empty($password))
    {
      $json = AKEncryptionAES::AESDecryptCtr($json, $password, 128);

      if (empty($json))
      {
        die('###{"status":false,"message":"Invalid login"}###');
      }
    }

    // Get the raw data
    $raw = json_decode($json, true);

    if (!empty($password) && (empty($raw)))
    {
      die('###{"status":false,"message":"Invalid login"}###');
    }

    // Pass all JSON data to the request array
    if (!empty($raw))
    {
      foreach ($raw as $key => $value)
      {
        $_REQUEST[$key] = $value;
      }
    }
  }
  elseif (!empty($password))
  {
    die('###{"status":false,"message":"Invalid login"}###');
  }

  // ------------------------------------------------------------
  // 3. Try the "factory" variable
  // ------------------------------------------------------------
  // A "factory" variable will override all other settings.
  $serialized = getQueryParam('factory', null);

  if (!is_null($serialized))
  {
    // Get the serialized factory
    AKFactory::unserialize($serialized);
    AKFactory::set('kickstart.enabled', true);

    return true;
  }

  // ------------------------------------------------------------
  // 4. Try the configuration variable for Kickstart
  // ------------------------------------------------------------
  if (defined('KICKSTART'))
  {
    $configuration = getQueryParam('configuration');

    if (!is_null($configuration))
    {
      // Let's decode the configuration from JSON to array
      $ini_data = json_decode($configuration, true);
    }
    else
    {
      // Neither exists. Enable Kickstart's interface anyway.
      $ini_data = array('kickstart.enabled' => true);
    }

    // Import any INI data we might have from other sources
    if (!empty($ini_data))
    {
      foreach ($ini_data as $key => $value)
      {
        AKFactory::set($key, $value);
      }

      AKFactory::set('kickstart.enabled', true);

      return true;
    }
  }
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

// Mini-controller for installer.php
if(!defined('KICKSTART'))
{
  // The observer class, used to report number of files and bytes processed
  class RestorationObserver extends AKAbstractPartObserver
  {
    public $compressedTotal = 0;
    public $uncompressedTotal = 0;
    public $filesProcessed = 0;

    public function update($object, $message)
    {
      if(!is_object($message)) return;

      if( !array_key_exists('type', get_object_vars($message)) ) return;

      if( $message->type == 'startfile' )
      {
        $this->filesProcessed++;
        $this->compressedTotal += $message->content->compressed;
        $this->uncompressedTotal += $message->content->uncompressed;
      }
    }

    public function __toString()
    {
      return __CLASS__;
    }

  }

  // Import configuration
  masterSetup();

  $retArray = array(
    'status'  => true,
    'message'  => null
  );

  $enabled = AKFactory::get('kickstart.enabled', false);

  if($enabled)
  {
    $task = getQueryParam('task');

    switch($task)
    {
      case 'ping':
        // ping task - realy does nothing!
        $timer = AKFactory::getTimer();
        $timer->enforce_min_exec_time();
        break;

      case 'startRestore':
        AKFactory::nuke(); // Reset the factory

        // Let the control flow to the next step (the rest of the code is common!!)

      case 'stepRestore':
        $engine = AKFactory::getUnarchiver(); // Get the engine
        $observer = new RestorationObserver(); // Create a new observer
        $engine->attach($observer); // Attach the observer
        $engine->tick();
        $ret = $engine->getStatusArray();
        if( $ret['Error'] != '' )
        {
          $retArray['status'] = false;
          $retArray['done'] = true;
          $retArray['message'] = $ret['Error'];
        }
        elseif( !$ret['HasRun'] )
        {
          $retArray['files'] = $observer->filesProcessed;
          $retArray['bytesIn'] = $observer->compressedTotal;
          $retArray['bytesOut'] = $observer->uncompressedTotal;
          $retArray['status'] = true;
          $retArray['done'] = true;
        }
        else
        {
          $retArray['files'] = $observer->filesProcessed;
          $retArray['bytesIn'] = $observer->compressedTotal;
          $retArray['bytesOut'] = $observer->uncompressedTotal;
          $retArray['status'] = true;
          $retArray['done'] = false;
          $retArray['factory'] = AKFactory::serialize();
        }
        break;

      case 'finalizeRestore':
        $root = AKFactory::get('kickstart.setup.destdir');
        // Remove the installation directory
        recursive_remove_directory( $root.'/installation' );

        $postproc = AKFactory::getPostProc();

        // Rename htaccess.bak to .htaccess
        if(file_exists($root.'/htaccess.bak'))
        {
          if( file_exists($root.'/.htaccess')  )
          {
            $postproc->unlink($root.'/.htaccess');
          }
          $postproc->rename( $root.'/htaccess.bak', $root.'/.htaccess' );
        }

        // Rename htaccess.bak to .htaccess
        if(file_exists($root.'/web.config.bak'))
        {
          if( file_exists($root.'/web.config')  )
          {
            $postproc->unlink($root.'/web.config');
          }
          $postproc->rename( $root.'/web.config.bak', $root.'/web.config' );
        }

        // Remove installer.config.php
        $basepath = KSROOTDIR;
        $basepath = rtrim( str_replace('\\','/',$basepath), '/' );
        if(!empty($basepath)) $basepath .= '/';
        $postproc->unlink( $basepath.'installer.config.php' );

        // Import a custom finalisation file
        if (file_exists(dirname(__FILE__) . '/installer.finalization.php'))
        {
          include_once dirname(__FILE__) . '/installer.finalization.php';
        }

        // Run a custom finalisation script
        if (function_exists('finalizeRestore'))
        {
          finalizeRestore($root, $basepath);
        }
        break;

      default:
        // Invalid task!
        $enabled = false;
        break;
    }
  }

  // Maybe we weren't authorized or the task was invalid?
  if(!$enabled)
  {
    // Maybe the user failed to enter any information
    $retArray['status'] = false;
    $retArray['message'] = AKText::_('ERR_INVALID_LOGIN');
  }

  // JSON encode the message
  $json = json_encode($retArray);

  // Do I have to encrypt?
  $password = AKFactory::get('kickstart.security.password', null);
  if(!empty($password))
  {
    $json = AKEncryptionAES::AESEncryptCtr($json, $password, 128);
  }

  // Return the message
  echo "###$json###";

}
