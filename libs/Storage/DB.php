<?php
namespace Comics\Storage;

use PDO;
use Comics\Storage\PDOWrapper;

class DB
{

    static $connections;

    /**
     * Clear cached MySQL connections.
     */
    static function clearConnectionCache()
    {
        self::$connections = null;
    }

    /**
     * Connect to a MySQL server and return the wrapped PDO connection.
     *
     * @param string MySQL database host.
     * @param string MySQL database username.
     * @param string MySQL database password.
     * @param string MySQL database database name.
     *
     * @return PDOWrapper Requested database connection.
     */
    static function setup($host, $username, $password, $dbname)
    {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname";
            $options = [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"];
            $connection = new PDO($dsn, $username, $password, $options);
            // $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            if ($dbname != 'grove' and Authentication::checkACL("view_detailed_debug_info")) {
                die("Failed to connect to MySQL database ($dsn) with error: " . str_ireplace($password, '[REDACTED]', $e->getMessage()));
            } else {
                die("Failed to connect to MySQL database with error: " . str_ireplace($password, '[REDACTED]', $e->getMessage()));
            }
        }

        return new PDOWrapper($connection, $dsn, $username, $password);
    }
}