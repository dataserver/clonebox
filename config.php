<?php
/*** OPTIONS ***/

define('CONFIG', [
    'title' => "Demo",

    'base_path_store' => APP_PATH . 'stored/',  // BASE path to where files are stored  (NO NEED for direct access via browser)
    'base_path_thumb' => APP_PATH . 'thumbs/',  // BASE path to thumbnails. Need be accessible via browser
    'base_path_tmp' => APP_PATH	. 'tmp/',       // BASE path to temporary file to store zip (after downloaded, zip file is deleted)
    'download_zipname' => 'clonebox.zip',       // default name for downloded zip file.
    
    // upload
    'upload_denied_extensions' => ["php", "py"],
    'thumbnail_prefix' => 'tn-',

    // database
    'database' => APP_PATH . 'database/database.sqlite3',  // sqlite3 database name


]);
