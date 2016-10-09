<?php
// Includes
require_once('config.php');

use Comics\Functions;
use Comics\Storage\DB;

if (isset($_GET['addRSSLink'])) {
    // Add RSS Link
    $result = Functions::addRSSLink($_POST['name'], $_POST['link']);

    echo json_encode($result);
}

?>