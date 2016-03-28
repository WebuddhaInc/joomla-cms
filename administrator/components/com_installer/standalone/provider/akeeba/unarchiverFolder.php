<?php

/**
 *
 * Folder extraction class
 *
 * It was faster to hack this then refactor the base restore operation to
 * recognize a folder.
 *
 */

class AKUnarchiverFolder extends AKUnarchiverJPA
{

  var $expectDataDescriptor = false;

  /**
   * [readArchiveHeader description]
   * @return [type] [description]
   */
  protected function readArchiveHeader()
  {

    // Prepare
      $this->currentPartOffset = 0;
      $this->dataReadLength    = 0;

    // Yuup..
      return true;

  }

  /**
   * [readFileHeader description]
   * @return [type] [description]
   */
  protected function readFileHeader(){
    return true;
  }

  protected function _run(){

    if($this->getState() == 'postrun') return;
    $this->setState('running');
    $timer = AKFactory::getTimer();
    $status = true;

    while( $status && ($timer->getTimeLeft() > 0) ){
      switch( $this->runState ){
        case AK_STATE_NOFILE:
          // Send start of file notification
            $message = new stdClass;
            $message->type = 'startfile';
            $message->content = new stdClass;
            $message->content->compressed   = 0;
            $message->content->uncompressed = 0;
            $this->notify($message);
        case AK_STATE_DATAREAD:
          // Queue
            $this->nextFile();
            if( $this->fp ){
              $this->runState = AK_STATE_DATA;
            }
            else {
              $this->runState = AK_STATE_DONE;
            }
          break;
        case AK_STATE_DATA:
          // Process
            $status = $this->processFileData();
          break;
        case AK_STATE_DONE:
        default:
          $this->setState('finished');
          return true;
          break;
      }
    }

  }

  /**
   * Opens the next part file for reading
   */
  protected function nextFile(){
    debugMsg('Current part is ' . $this->currentPartNumber . '; opening the next part');
    ++$this->currentPartNumber;

    $file = $this->__scanFolderRecursively( $this->getFilename(), null, $this->currentPartNumber );
    if( !is_readable($file) ){
      $this->setState('postrun');
      return false;
    }

    $root = AKFactory::get('kickstart.setup.destdir');
    $this->fileDetails = array(
      'source' => $file,
      'size'   => filesize( $file ),
      'target' => $root . DS . substr($file, strlen($this->getFilename()))
      );

    if(is_resource($this->fp)){
      @fclose($this->fp);
    }

    debugMsg('Opening file ' . $file);
    $this->fp = @fopen($file, 'rb');
    if($this->fp === false){
      debugMsg('Could not open file - crash imminent');
    }
    fseek($this->fp, 0);
    $this->currentPartOffset = 0;

    return true;
  }

  /**
   * [processFileData description]
   * @return [type] [description]
   */
  protected function processFileData(){

    // Stage
      $size   = $this->fileDetails->size;
      $source = $this->fileDetails->source;
      $target = $this->fileDetails->target;

inspect( $size, $source, $target );die(__LINE__.': '.__FILE__);

    // Uncompressed files are being processed in small chunks, to avoid timeouts
      if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') ){
        // Before processing file data, ensure permissions are adequate
        $this->setCorrectPermissions( $source );
      }

    // Open the output file
      if( !AKFactory::get('kickstart.setup.dryrun','0') ){
        $ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($source);
        if ($this->dataReadLength == 0) {
          $outfp = @fopen( $target, 'wb' );
        } else {
          $outfp = @fopen( $target, 'ab' );
        }
        // Can we write to the file?
        if( ($outfp === false) && (!$ignore) ) {
          // An error occured
          debugMsg('Could not write to output file');
          $this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $target) );
          return false;
        }
      }

    // Reference to the global timer
      $timer = AKFactory::getTimer();
      $toReadBytes = 0;
      $leftBytes = $size - $this->dataReadLength;

    // Loop while there's data to read and enough time to do it
      while( ($leftBytes > 0) && ($timer->getTimeLeft() > 0) ){
        $toReadBytes = ($leftBytes > $this->chunkSize) ? $this->chunkSize : $leftBytes;
        $data = $this->fread( $this->fp, $toReadBytes );
        $reallyReadBytes = akstringlen($data);
        $leftBytes -= $reallyReadBytes;
        $this->dataReadLength += $reallyReadBytes;
        if($reallyReadBytes < $toReadBytes){
          // Nope. The archive is corrupt
          debugMsg('Not enough data in file / the file is corrupt.');
          $this->setError( AKText::_('ERR_CORRUPT_FILE') );
          return false;
        }
        if( !AKFactory::get('kickstart.setup.dryrun','0') )
          if(is_resource($outfp))
            @fwrite( $outfp, $data );
      }

    // Close the file pointer
      if( !AKFactory::get('kickstart.setup.dryrun','0') )
        if(is_resource($outfp)) @fclose($outfp);

    // Was this a pre-timeout bail out?
      if( $leftBytes > 0 ){
        $this->runState = AK_STATE_DATA;
      }
      else {
        // Oh! We just finished!
        $this->runState = AK_STATE_DATAREAD;
        $this->dataReadLength = 0;
      }

    // Complete
      return true;

  }


  /**
   * Scans for archive parts
   */
  protected function scanArchives()
  {

    // Reset
      $this->currentPartNumber = -1;
      $this->currentPartOffset = 0;
      $this->runState          = AK_STATE_NOFILE;
      $this->totalSize         = 0;
      $this->fileList          = array();

    // Send start of file notification
      $message = new stdClass;
      $message->type = 'totalsize';
      $message->content = new stdClass;
      $message->content->totalsize = 0;
      $message->content->filelist  = array();
      $this->notify($message);

  }

  /**
   * [__scanFolderRecursively description]
   * @param  [type] $path [description]
   * @return [type]       [description]
   */
  protected function __scanFolderRecursively( $base, $path=null, $seekPart=0, $partCount=0 ){
    $files = scandir( $base.DS.$path );
    foreach( $files AS $file ){
      if( !preg_match('/^\.+$/', $file) ){
        if( is_dir($base.DS.$path.$file) ){
          $res = $this->__scanFolderRecursively($base, $path.$file.DS, $seekPart, $partCount);
          if( $res )
            return $res;
        }
        else if( is_readable($base.DS.$path.$file) ){
          $this->totalSize += filesize($base.DS.$path.$file);
          $partCount++;
          if( $partCount > $seekPart )
            return $base.DS.$path.$file;
        }
      }
    }
  }

}

