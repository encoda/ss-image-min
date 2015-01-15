<?php

class SSDeleteImageCacheTask extends BuildTask {

  protected $title = 'Delete Cached Images';

  protected $description = 'A class to Delete Cached Images';

  protected $enabled = true;

  function run($request) {
    $image = new CachedImage();
    $image->deleteFiles();
  }
}