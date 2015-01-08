<?php

class CachedImage extends Image {
  const cached_image_directory = '/cache/images';

  public static function getCacheDirectory() {
    return ASSETS_PATH . static::cached_image_directory;
  }

  public function RelativeLink() {
    return $this->getCachedImage();
  }

  public function onBeforeWrite() {
    parent::onBeforeWrite();
    $this->getCachedImage();
  }

  public function getCachedImage() {
    if(!$this->isInDB()) return;
    $image_cache = new SSImageCache();
    $image_cache->cached_image_directory = static::getCacheDirectory();

    return $image_cache->cache($this->getFullPath());
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
