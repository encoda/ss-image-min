<?php

ob_start();

class SSImageMin
{

    public static $image_directory = '';

    public static $increased_memory_limit = '2480M';

    public static $compress_rate = 85;

  /**
   * Stores the image source given for reference
   */
  public $image_src;

  /**
   * If the file is remote or not
   */
  public $is_remote;

  /**
   * Allow the user to set the options for the setup
   */
  public $options;

  /**
   * The location of the cached images directory
   */
  public $cached_image_directory;

  /**
   * The name of the cached file
   */
  public $cached_filename;

  /**
   * Stores the server's version of the GD Library, if enabled
   */
  protected $gd_version;

  /**
   * The memory limit currently established on the server
   */
  protected $memory_limit;

  /**
   * The file mime type
   */
  protected $file_mime_type;

  /**
   * The extension of the file
   */
  protected $file_extension;

  /**
   * The original size of the file
   */
  protected $local_image_src;

  /**
   * The original size of the file
   */
  protected $src_filesize;

  /**
   * The extension of the file
   */
  protected $cached_filesize;


    public static function get_cache_directory($should_create_folder = true)
    {
        if (empty(static::$image_directory)) {
            $path = ASSETS_PATH . '/cache/images';
            static::$image_directory = $path;
        } else {
            static::$image_directory;
        }
        if (!file_exists(static::$image_directory) && $should_create_folder) {
            mkdir(static::$image_directory, 0755, true);
        }
        return static::$image_directory;
    }

    public static function get_cached_image($image)
    {
        $instance = new static();
        $instance->cached_image_directory = static::get_cache_directory();

        if (!is_string($image)) {
            $instance->error('Image source given must be a string.');
        }

        $instance->image_src = $image;
        $instance->pre_set_class_vars();

    // If the image hasn't been server up at thi point, fetch, compress, cache, and return
    if ($instance->cached_file_exists()) {
        $instance->src_filesize = filesize($instance->image_src);
        $instance->cached_filesize = filesize($instance->cached_filename);
        if ($instance->src_filesize < $instance->cached_filesize) {
            return $instance->docroot_to_url($instance->image_src . '?' . md5_file($instance->image_src));
        }
        return $instance->docroot_to_url();
    }

        if ($instance->is_remote) {
            $instance->download_image();
        }

        if (!$instance->fetch_image()) {
            $instance->error('Could not copy image resource.');
        }

        $instance->src_filesize = filesize($instance->image_src);
        $instance->cached_filesize = filesize($instance->cached_filename);
        if ($instance->src_filesize < $instance->cached_filesize) {
            return $instance->docroot_to_url($instance->image_src . '?' . md5_file($instance->image_src));
        }

        return $instance->docroot_to_url();
    }

    public static function delete_cache_files($target = null)
    {
        if ($target == null) {
            $target = static::get_cache_directory(false);
        }

        if (file_exists($target)) {
            $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
      );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            if (rmdir($target)) {
                echo $target . ' Successfully removed';
            }
        } else {
            echo 'Nothing to delete';
        }
    }

  /**
   * Constructor function
   *
   * @param array $options Options include the keys 'echo' (boolean) and 'cache_time' (integer).  'cache_time' is currently not employed, but in place for future reference.
   * @return object Returns the class object for the user to reference it in the future.
   */
  public function __construct($options = array())
  {
      if (! $this->can_run_image_cache()) {
          $this->error('PHP Image Cache must be run on a server with a bundled GD version.');
      }
      $defaults = array(
      'echo' => false,        // Determines whether the resulting source should be echoed or returned
      'cache_time' => 0   // How long the image should be cached for. If the value is 0, then the cache never expires. Default is 0, never expires.
    );
      $this->options = (object) array_merge($defaults, $options);
      if (!$this->cached_image_directory) {
          $this->cached_image_directory = dirname(__FILE__) . '/php-image-cache';
      }
      return $this;
  }

  /**
   * Validates whether the user can use this class or not, based on the GD Version their server is carrying.
   *
   * @return bool
   */
  public function can_run_image_cache()
  {
      $gd_info = gd_info();
      $this->gd_version = false;
      if (preg_match('#bundled \((.+)\)$#i', $gd_info['GD Version'], $matches)) {
          $this->gd_version = (float) $matches[1];
      } else {
          $this->gd_version = (float) substr($gd_info['GD Version'], 0, 3);
      }
      return (bool) $this->gd_version;
  }

  /**
   * Downloads a remote file and stores it locally to be used for compression
   */
  protected function download_image()
  {
      $image_resource = file_get_contents($this->image_src);
      $basename = basename($this->image_src);
      if (! stripos($basename, '.' . $this->file_extension)) {
          $basename .= '.' . $this->file_extension;
      }
      $image_location = dirname($this->cached_image_directory) . '/' . $basename;
      if (! file_exists($image_location)) {
          if (! file_put_contents($image_location, $image_resource)) {
              $this->error('Could not download the remote image');
          }
      }
      $this->image_src = $image_location;
  }

  /**
   * Creates the cached directory
   *
   * @return bool If the directory was successfully created or not
   */
  protected function make_cache_directory()
  {
      if (is_dir($this->cached_image_directory)) {
          return true;
      }
      try {
          mkdir($this->cached_image_directory);
      } catch (Exception $e) {
          $this->error('There was an error creating the new directory:', $e);
          return false;
      }
      return true;
  }

  /**
   * Fetch the image as a resource and save it into the cache directory.
   *
   * @source http://stackoverflow.com/questions/9839150/image-compression-in-php
   * @return If the image was successfully created or not
   */
  protected function fetch_image()
  {
      $image_size = getimagesize($this->image_src);
      $image_width = $image_size[0];
      $image_height = $image_size[1];
      $file_mime_as_ext = end(@explode('/', $this->file_mime_type));
      $image_dest_func = 'imagecreate';
      if ($this->gd_version >= 2) {
          $image_dest_func = 'imagecreatetruecolor';
      }
      if (in_array($file_mime_as_ext, array( 'gif', 'jpeg', 'png' ))) {
          $image_src_func = 'imagecreatefrom' . $this->file_extension;
          $image_create_func = 'image' . $this->file_extension;
      } else {
          $this->error('The image you supply must have a .gif, .jpg/.jpeg, or .png extension.');
          return false;
      }
      $image_src = @call_user_func($image_src_func, $this->image_src);
      $image_dest = @call_user_func($image_dest_func, $image_width, $image_height);
      $this->increase_memory_limit();
      if ($file_mime_as_ext === 'jpeg') {
          $background = imagecolorallocate($image_dest, 255, 255, 255);
          imagefill($image_dest, 0, 0, $background);
      } elseif (in_array($file_mime_as_ext, array( 'gif', 'png' ))) {
          imagealphablending($image_src, false);
          imagesavealpha($image_src, true);
          imagealphablending($image_dest, false);
          imagesavealpha($image_dest, true);
      }
      imagecopy($image_dest, $image_src, 0, 0, 0, 0, $image_width, $image_height);
      switch ($file_mime_as_ext) {
      case 'jpeg':
        $created = imagejpeg($image_dest, $this->cached_filename, static::$compress_rate);
        break;
      case 'png':
        $created = imagepng($image_dest, $this->cached_filename, floor(static::$compress_rate / 10));
        break;
      case 'gif':
        $created = imagegif($image_dest, $this->cached_filename);
        break;
      default:
        return false;
        break;
    }
      imagedestroy($image_src);
      imagedestroy($image_dest);
      $this->reset_memory_limit();
      return $created;
  }

  /**
   * Returns
   *
   * @param string $url The url to check validate
   * @return string The URL of the image
   */
  protected function docroot_to_url($src = null)
  {
      if (is_null($src)) {
          $src = $this->cached_filename;
      }

      $doc_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
      $image_path = str_replace($doc_root, '', $src);

      if ($this->link_is_broken($image_path)) {
          $this->error('Final image URL "' . $image_path . '" is broken');
      }

      $image_url = substr($image_path, 1);
      return $image_url;
  }

  /**
   * Sets up all class variables in one central function.
   */
  protected function pre_set_class_vars()
  {
      $this->set_file_mime_type();
      $this->set_cached_filename();
      $this->set_memory_limit();
      $this->set_is_remote();
  }

  /**
   * Utility function to determine of the link in question is broken or not.
   *
   * @param string $url The url to check validate
   * @return bool Indicates whether or not the link is broken
   */
  protected function link_is_broken($url)
  {
      if (!function_exists('curl_init')) {
          return false;
      }

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_exec($ch);

      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($http_code == 404) {
          $broken = true;
      } else {
          $broken = false;
      }

      curl_close($ch);

      return $broken;
  }

  /**
   * Quick and dirty way to see if the file is remote or local.  Deeper checking comes
   * later if we don't find a compressed version of the file locally.
   *
   * @return bool Whether or not the image is remote
   */
  protected function is_image_local()
  {
      if (file_exists($this->image_src)) {
          return true;
      }
      $parsed_src = parse_url($this->image_src);
      if ($_SERVER['HTTP_HOST'] === $parsed_src['host']) {
          return true;
      }
      return false;
  }

  /**
   * Determines if the image is local or being referenced from a remote URL
   */
  protected function set_is_remote()
  {
      $this->is_remote = ! $this->is_image_local();
  }

  /**
   * This function creates the filename of the new image using an MD5 hash of the original filename.  This helps to create a unique
   * filename for the newly compressed image so that this class can easily find and determine if the image has already been compressed and stored locally.
   *
   * Employing use of the "alt" and "title" tags on the image (which hopefully you're already doing) will negate any potentially negative impacts on SEO.  See
   * this article for more information: http://searchenginewatch.com/article/2120682/Image-Optimization-How-to-Rank-on-Image-Search
   */
  protected function set_cached_filename()
  {
      $pathinfo = pathinfo($this->image_src);
      $this->cached_filename = $this->cached_image_directory . '/' . md5(basename($this->image_src)) . '.' . $this->file_extension;
  }

  /**
   * Simply determines if a compressed of the image that's sent is already compressed or not.
   */
  protected function cached_file_exists()
  {
      if ($this->is_remote) {
          $this->download_image();
      }
      if (file_exists($this->cached_filename)) {
          return true;
      }
      return false;
  }

  /**
   * Stores the file's mime type and validates that the file being compressed is indeed an image.
   */
  protected function set_file_mime_type()
  {
      $image_type = exif_imagetype($this->image_src);
      if (! $image_type) {
          $this->error('The file you supplied isn\'t a valid image.');
      }
      $this->file_mime_type = image_type_to_mime_type($image_type);
      $this->file_extension = image_type_to_extension($image_type, false);
  }

  /**
   * Stores the original value of the server's memory limit
   */
  protected function set_memory_limit()
  {
      $this->memory_limit = ini_get('memory_limit');
  }

  /**
   * Temporarily increases the servers memory limit to 2480 MB to handle building larger images.  Based on initial
   * tests, this seems to be a large enough increase to handle rebuilding jpg images as large as 4300px wide with no pre-compression.
   */
  protected function increase_memory_limit()
  {
      ini_set('memory_limit', static::$increased_memory_limit);
  }

  /**
   * Resets the servers memory limit to its original value
   */
  protected function reset_memory_limit()
  {
      ini_set('memory_limit', $this->memory_limit);
  }

  /**
   * Displays an error and kills the script
   *
   * @param String $status The message to be passed to the native `exit()` function
   */
  protected function error($status = null)
  {
      if (is_null($status)) {
          $status = 'Unknown Error:';
      }
      exit($status);
  }
}
