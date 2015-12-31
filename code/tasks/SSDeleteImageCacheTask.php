<?php

class SSDeleteImageCacheTask extends BuildTask
{

    protected $title = 'Delete Cached Images';

    protected $description = 'A class to Delete Cached Images';

    protected $enabled = true;

    public function run($request)
    {
        SSImageMin::delete_cache_files();
    }
}
