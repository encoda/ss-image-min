<?php

class CachedImage extends Image {

  public function RelativeLink() {
    return $this->getCachedImage();
  }

  public function onBeforeWrite() {
    parent::onBeforeWrite();
    $this->getCachedImage();
  }

  public function getCachedImage() {
    if ($this->isInDB()) {
      return SSImageMin::get_cached_image($this->getFullPath());
    } else {
      if (!$this->exists()) return;
      return SSImageMin::get_cached_image(Director::baseFolder() . '/' . $this->fileName);
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
