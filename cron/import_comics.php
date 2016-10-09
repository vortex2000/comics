<?php
// Includes
require_once('../config.php');

use Comics\Functions;
use Comics\Storage\DB;

// Establish DB Connection
$DB = DB::setup(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Set Variables
$max_entries = 1;

// Get Comic Links
$comics = $DB->execute("SELECT * FROM bw_comics");

foreach ($comics as $comic) {
    Functions::getComic($comic['id'], $comic['link'], $max_entries);
}
?>