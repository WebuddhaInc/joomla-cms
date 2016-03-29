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

  function __sleep(){
    return array_diff(array_keys(get_object_vars($this)), array(
      'fileList'
      ));
  }

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
    $file = 0;

    debugMsg( $this );

    while( $status && ($timer->getTimeLeft() > 0) ){

      switch( $this->runState ){

        case AK_STATE_NOFILE:
          // Report
            $message = new stdClass;
            $message->type = 'startfile';
            $message->content = new stdClass;
            $message->content->compressed   = 0; // $this->totalSize;
            $message->content->uncompressed = 0;
            $this->notify($message);

        case AK_STATE_DATAREAD:
          // Queue
            if( $file++ >= 1000 )
              return true;
            if( $this->nextFile() && $this->fp ){
              $this->runState = AK_STATE_DATA;
              debugMsg( [$this->fileDetails, $this->currentPartNumber, $this->currentPartOffset] );
              // Report
                $message = new stdClass;
                $message->type = 'startfile';
                $message->content = new stdClass;
                $message->content->compressed   = $this->fileDetails->size;
                $message->content->uncompressed = 0;
                $this->notify($message);
            }
            else {
              $this->runState = AK_STATE_POSTPROC;
            }
          break;

        case AK_STATE_DATA:
          $status = $this->processFileData();
          break;

        case AK_STATE_POSTPROC:
          debugMsg(__CLASS__.'::_run() - Calling post-processing class');
          $this->postProcEngine->timestamp = $this->fileHeader->timestamp;
          $status = $this->postProcEngine->process();
          $this->propagateFromObject( $this->postProcEngine );
          $this->runState = AK_STATE_DONE;
          break;

        case AK_STATE_DONE:
        default:
          // Report
            $message = new stdClass;
            $message->type = 'endfile';
            $message->content = new stdClass;
            $this->notify($message);
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

    // Incrementw
      ++$this->currentPartNumber;

    // Debug
      debugMsg('Current part is ' . $this->currentPartNumber . '; opening the next part');

    // Close
      if(is_resource($this->fp)){
        @fclose($this->fp);
        $this->fp = null;
      }

    // Reset
      $this->fileDetails = (object)array();

    // Lookup
      if( empty($this->fileList) )
        $file = $this->__scanFolderRecursively( $this->getFilename() );
      if( isset($this->fileList[ $this->currentPartNumber ]) )
        $file = $this->fileList[ $this->currentPartNumber ];
      if( !$file || !is_readable($file) ){
        $this->setState('postrun');
        return false;
      }

    // Debug
      debugMsg(' - Found ' . $file);

    // Translate
      $root = AKFactory::get('kickstart.setup.destdir');
      $this->fileDetails = (object)array(
        'source' => $file,
        'size'   => filesize( $file ),
        'target' => $root . substr($file, strlen($this->getFilename()))
        );

    // Open
      debugMsg(' - Opening file ' . $file);
      $this->fp = @fopen($file, 'rb');
      if($this->fp === false){
        debugMsg('Could not open file - crash imminent');
      }
      fseek($this->fp, 0);
      $this->currentPartOffset = 0;

    // Complete
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
        $this->totalWritten += $reallyReadBytes;
        if($reallyReadBytes < $toReadBytes){
          debugMsg('Not enough data in file / the file is corrupt.');
          $this->setError( AKText::_('ERR_CORRUPT_FILE') );
          return false;
        }
        if( !AKFactory::get('kickstart.setup.dryrun','0') )
          if(is_resource($outfp))
            @fwrite( $outfp, $data );
      }

    // Debug
      debugMsg(' - Wrote to file ' . $target);

    // Close the file pointer
      if( !AKFactory::get('kickstart.setup.dryrun','0') )
        if(is_resource($outfp))
          @fclose($outfp);

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
      $this->totalWritten      = 0;
      $this->fileCount         = 0;
      $this->fileList          = 0;

    // Scan
      $this->__scanFolderRecursively( $this->getFilename() );

    // Send start of file notification
      $message = new stdClass;
      $message->type = 'totalsize';
      $message->content = new stdClass;
      $message->content->totalsize = $this->totalSize;
      $message->content->filelist  = array();
      $this->notify($message);

  }

  /**
   * [__scanFolderRecursively description]
   * @param  [type] $path [description]
   * @return [type]       [description]
   */
  protected function __scanFolderRecursively( $base, $path=null, $seekPart=null, $partCount=0 ){
    $files = scandir( $base.DS.$path );
    foreach( $files AS $file ){
      if( !preg_match('/^\.+$/', $file) ){
        if( is_dir($base.DS.$path.$file) ){
          $res = $this->__scanFolderRecursively($base, $path.$file.DS, $seekPart, $partCount);
          if( $res )
            return $res;
        }
        else if( is_readable($base.DS.$path.$file) ){
          $this->fileCount++;
          $this->totalSize += filesize($base.DS.$path.$file);
          $this->fileList[] = $base.DS.$path.$file;
          $partCount++;
          if( !is_null($seekPart) && $partCount > $seekPart )
            return $base.DS.$path.$file;
        }
      }
    }
  }

}

