<?php

class SSImageCacheTask extends BuildTask
{

    protected $title = 'Caching Images';

    protected $description = 'A class to Cache Images';

    protected $enabled = true;

    public function run($request)
    {
        if (isset($_POST['id'])) {
            $image = Image::get()->byID($_POST['id']);
            echo $image->getCachedImage();
        } elseif (isset($_GET['ids'])) {
            $ids = array();
            foreach (Image::get() as $image) {
                array_push($ids, $image->ID);
            }
            echo json_encode($ids);
        } else {
            $this->cacheImages();
        }
    }

    public function cacheImages()
    {
        echo '<script type="text/javascript">startCaching()</script>';
    }
}

?>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>

<script type="text/javascript">
  function startCaching(argument) {
    jQuery(function($) {
      (function getIDs () {
        $.ajax({
          url: location.href,
          data: {
            ids: "all"
          },
          success: function(data, status, xhr) {
            var matches = data.match(/\[.*\]/g),
                idsString = matches[matches.length - 1],
                idsArray = idsString.replace(/[\[\]]/g, "").split(",");

            cacheById(idsArray);
          }
        });
      })();

      function cacheById(ids) {
        if (ids.length == 0) {
          $("body").append("<p>Finished</p>");
          $(window).scrollTop($("body").height());
          return;
        }

        $.ajax({
          url: location.href,
          context: document.body,
          type: "POST",
          data: {
            id: parseInt(ids.shift())
          },
          success: function(data, status, xhr) {
            $("body").append("This Image" + data + " is cached. " + ids.length + " remain<br />");
            $("h1").last().remove();
            $(window).scrollTop($("body").height());
            cacheById(ids);
          }
        });
      }

    });
  }
</script>