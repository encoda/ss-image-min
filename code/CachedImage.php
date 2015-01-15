<?php

class CachedImage extends Image {
  public static $image_directory = '/home/alexandre/workspace/autinpack/assets/cache/images';

  public function RelativeLink() {
    return $this->getCachedImage();
  }

  public function onBeforeWrite() {
    parent::onBeforeWrite();
    $this->getCachedImage();
  }

  public function getCachedImage() {
    if (!file_exists(static::$image_directory))
      mkdir(static::$image_directory, 0777, true);
    $image_cache = new SSImageMin();
    $image_cache->cached_image_directory = static::$image_directory;

    if ($this->isInDB()) {
      return $image_cache->cache($this->getFullPath());
    } else {
      if (!$this->exists()) return;
      return $image_cache->cache(Director::baseFolder() . '/' . $this->fileName);
    }
  }

  public function deleteFiles($target = null) {
    if($target == null)
      $target = static::$image_directory;

    if(file_exists($target)) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
      );

      foreach ($files as $fileinfo) {
          $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
          $todo($fileinfo->getRealPath());
      }

      if(rmdir($target))
        echo $target . ' Successfully removed';
    } else {
      echo 'Nothing to delete';
    }
  }

  /**
   * Gets the absolute URL accessible through the web.
   *
   * @uses Director::absoluteBaseURL()
   * @return string
   */
  public function getAbsoluteURL() {
    return Director::absoluteBaseURL() . $this->RelativeLink();
  }

  /**
   * Gets the relative URL accessible through the web.
   *
   * @uses Director::baseURL()
   * @return string
   */
  public function getURL() {
    return Director::baseURL() . $this->RelativeLink();
  }

}
