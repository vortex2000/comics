<?php

// Database Info
define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbname');
define('DB_USERNAME', 'dbusername');
define('DB_PASSWORD', 'dbpassword');

// Settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Includes
require_once('libs/Storage/DB.php');
require_once('libs/Storage/PDOWrapper.php');
require_once('libs/Functions/Functions.php');
?>