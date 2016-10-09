<?php
namespace Comics\Storage;

use PDO;

class PDOWrapper
{
    function __construct($connection, $dsn, $username, $password)
    {
        $this->_old_reconnect_count = null;
        $this->connection = $connection;
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->reconnects = 0;
    }

    function __call($name, $parameters)
    {
        return call_user_func_array([$this->connection, $name], $parameters);
    }

    /**
     * Disable automatic reconnection when queries fail due to a disconnect.
     */
    function disableAutomaticReconnect()
    {
        $this->reconnects = 999;
    }

    /**
     * Re-initialize database connection used by wrapper.
     */
    function reconnect()
    {
        $this->connection = new PDO($this->dsn, $this->username, $this->password, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
        $this->reconnects++;
    }

    /**
     * Reset state of most recently recorded error information.
     */
    function resetErrors()
    {
        $this->sqlstate = null;
        $this->errormessage = null;
        $this->errornumber = null;
    }

    /**
     * Fetch one row from the MySQL database.
     *
     * The results of this call are cached.
     *
     * @param string  MySQL query
     * @param array   Bound parameters for MySQL query
     * @param integer Cache expiration time in seconds
     *
     * @return A single associative arrow representing the fetch row. If more
     * than one row is found, `false` is returned.
     */
    function fetchOneCachedRow($query, $parameters = [], $expire = 600)
    {
        $paramhash = sha1(serialize($parameters));
        $key = "{$this->dsn}:fetchOneCachedRow:$query:$paramhash";
        if (!($results = Cache::get($key))) {
            $results = $this->fetchOneRow($query, $parameters);
            Cache::set($key, $results, $expire);
        }

        return $results;
    }

    /**
     * Return associative array with a specified column value from a MySQL
     * result set acting the key for each row.
     *
     * The results of this call are cached.
     *
     * @param string  Name of the column that will act as the map's key.
     * @param string  MySQL query
     * @param array   Bound parameters for MySQL query
     * @param integer Cache expiration time in seconds
     *
     * @return array A single associative arrow representing the fetch row. If
     * more than one row is found, `false` is returned.
     */
    function selectCachedMap($key, $query, $parameters = [], $expire = 600)
    {
        if (($results = $this->cachedExecute($query, $parameters, $expire)) === false) {
            return false;
        }

        $map = [];
        foreach ($results as $result) {
            $map[$result[$key]] = $result;
        }

        return $map;
    }

    /**
     * Deprecated, do not use.
     */
    function cachedRowCount($query, $parameters = [], $expire = 600)
    {
        $paramhash = sha1(serialize($parameters));
        $key = "{$this->dsn}:cachedRowCount:$query:$paramhash";
        if (!($results = Cache::get($key))) {
            $results = $this->rowCount($query, $parameters);
            Cache::set($key, $results, $expire);
        }

        return $results;
    }

    /**
     * Return list of associative arrays, one array for each result row with
     * keys of the array representing the column for each value.
     *
     * The results of this call are cached.
     *
     * @param string  MySQL query
     * @param array   Bound parameters for MySQL query
     * @param integer Cache expiration time in seconds
     *
     * @return array|boolean If the query succeeds, an array is returned for
     * each row. If it fails, `false` is returned.
     */
    function cachedExecute($query, $parameters = [], $expire = 600)
    {
        $paramhash = sha1(serialize($parameters));
        $key = "{$this->dsn}:cachedExecute:$query:$paramhash";
        if (!($results = Cache::get($key))) {
            $results = $this->execute($query, $parameters);
            Cache::set($key, $results, $expire);
        }

        return $results;
    }

    /**
     * Return list of associative arrays, one array for each result row with
     * keys of the array representing the column for each value. If the option
     * to fetch all results is set to `false`, each you must call `nextRow` to
     * fetch each row from the result set.
     *
     * @param string  MySQL query
     * @param array   Bound parameters for MySQL query
     * @param boolean Determines whether or not all rows are turned at once.
     * @param boolean Determines whether or not query failure messages are
     *                logged.
     *
     * @return array|integer|boolean If the query succeeds, an array is
     * returned for each row or, if the option to fetch all results is disable,
     * an integer representing the number of rows available. If the query
     * fails, `false` is returned.
     */
    function execute($query, $parameters = [], $fetch_all = true, $logfailure = true)
    {
        $this->resetErrors();
        $this->statement = $statement = $this->connection->prepare($query);
        $this->lastinsertid = $this->updated = $this->inserted = null;

        if (is_object($parameters)) {
            $parameters = (array)$parameters;
        }

        if (is_array($parameters) and array_values($parameters) !== $parameters) {
            foreach ($parameters as $k => $v) {
                $original_k = $k;
                if (strpos($k, ':') !== 0) {
                    $k = ":$k";
                }

                if (!preg_match('/' . preg_quote($k) . '\b/', $query)) {
                    unset($parameters[$original_k]);
                }
            }
        }

        if (!($r = $statement->execute($parameters))) {
            list($this->sqlstate, $this->errornumber, $this->errormessage) = $statement->errorInfo();
            if (!$this->errormessage) {
                $this->errormessage = "SQLSTATE " . $this->sqlstate;
            } else if ($this->errormessage == "MySQL server has gone away" and !$this->reconnects) {
                $this->reconnect();

                return $this->execute($query, $parameters, $fetch_all, $logfailure);
            }

            if ($logfailure) {
                $tmp = $this->errormessage;
                addLogEntry('Database Query', "Error executing $query: " . $this->errormessage, LOG_MODULE_SYSTEM, LOG_TYPE_ERROR);
                $this->errormessage = $tmp;
            }

            return false;
        }

        $this->reconnects = 0;
        $this->lastinsertid = $this->connection->lastInsertID();
        $this->affectedrows = $statement->rowCount();
        $this->updated = $this->affectedrows == 2;
        $this->inserted = $this->affectedrows == 1;

        if ($fetch_all) {
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $this->statement->rowCount();
        }
    }

    /**
     * Begin a MySQL transaction. Disables automatic re-connects.
     *
     * @return boolean Value indicating success of this call.
     */
    function startTransaction()
    {
        if ($this->_old_reconnect_count) {
            throw new Exception('Transaction already in progress.');
        }

        $success = $this->execute('START TRANSACTION') !== false;
        if ($success) {
            $this->_old_reconnect_count = $this->reconnects;
            $this->disableAutomaticReconnect();
        }

        return $success;
    }

    /**
     * Commit the results of a transaction in progress.
     *
     * @return boolean Value indicating success of this call.
     */
    function commit()
    {
        if ($this->_old_reconnect_count === null) {
            throw new Exception('Transaction not in progress.');
        }

        $success = $this->execute('COMMIT') !== false;
        if ($success) {
            $this->reconnects = $this->_old_reconnect_count;
            $this->_old_reconnect_count = null;
        }

        return $success;
    }

    /**
     * Rollback the results of a transaction in progress.
     *
     * @return boolean Value indicating success of this call.
     */
    function rollBack()
    {
        if ($this->_old_reconnect_count === null) {
            throw new Exception('Transaction not in progress.');
        }

        $success = $this->execute('ROLLBACK') !== false;
        if ($success) {
            $this->reconnects = $this->_old_reconnect_count;
            $this->_old_reconnect_count = null;
        }

        return $success;
    }

    /**
     * Return associative array with a specified column value from a MySQL
     * result set acting the key for each row.
     *
     * The results of this call are cached.
     *
     * @param string  Name of the column that will act as the map's key.
     * @param string  MySQL query
     * @param array   Bound parameters for MySQL query
     * @param integer Cache expiration time in seconds
     *
     * @return array A single associative arrow representing the fetch row. If
     * more than one row is found, `false` is returned.
     */
    function selectMap($key, $query, $parameters = [])
    {
        if (($results = $this->execute($query, $parameters)) === false) {
            return false;
        }

        $map = [];
        foreach ($results as $result) {
            $map[$result[$key]] = $result;
        }

        return $map;
    }

    /**
     * When execute is used without fetching all results, this retrieves the
     * next row in the set.
     *
     * @return array Query result row.
     */
    function nextRow()
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    // TODO: replace this where used
    function next_row()
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch one row from the MySQL database.
     *
     * @param string MySQL query
     * @param array  Bound parameters for MySQL query
     *
     * @return A single associative arrow representing the fetch row. If more
     * than one row is found, `false` is returned.
     */
    function fetchOneRow($query, $parameters = [])
    {
        if (($result = $this->execute($query, $parameters)) === false) {
            return false;
        }

        if (count($result) < 2) {
            return array_pop($result);
        } else {
            $this->errormessage = "fetchOneRow called, but more than one row was returned. Parameters: " . implode(" ", $parameters);
            addLogEntry('Database Query', "Error executing $query: " . $this->errormessage, LOG_MODULE_SYSTEM, LOG_TYPE_ERROR);

            return false;
        }
    }

    /**
     * Deprecated, do not use.
     */
    function rowCount($query, $parameters = [])
    {
        $query = preg_replace('/;+\s*$/', '', $query);
        $results = $this->fetchOneRow("SELECT COUNT(*) as count FROM ($query) as t", $parameters);

        return $results['count'];
    }

    /**
     * Deprecated, do not use.
     */
    function lastInsertID()
    {
        return $this->connection->lastInsertID();
    }

    /**
     * Return a list of all values in a MySQL query that queries for only one
     * column.
     *
     * The results of this call are cached.
     *
     * @param string MySQL query. The SELECT clause must contain only one
     *               column.
     * @param array  Bound parameters for MySQL query
     *
     * @return array Array containing all values in the column.
     */
    function fetchCachedColumn($query, $parameters = [], $expire = 600)
    {
        // TODO: Figure out what's passing in null here.
        $paramhash = sha1(serialize($parameters));
        $key = "{$this->dsn}:fetchCachedColumn:$query:$paramhash";
        if (!($results = Cache::get($key))) {
            $results = $this->fetchColumn($query, $parameters);
            Cache::set($key, $results, $expire);
        }

        return $results;
    }

    /**
     * Return a list of all values in a MySQL query that queries for only one
     * column.
     *
     * @param string  MySQL query. The SELECT clause must contain only one
     *                column.
     * @param array   Bound parameters for MySQL query
     * @param integer Cache expiration time in seconds
     *
     * @return array Array containing all values in the column.
     */
    function fetchColumn($query, $parameters = [])
    {
        if (($result = $this->execute($query, $parameters)) === false) {
            return false;
        }

        $column = [];
        foreach ($result as $row) {
            $column[] = array_pop($row);
        }

        return $column;
    }

    /**
     * Insert values from an associative array into a table with each key /
     * value pair corresponding to columns and row values.
     *
     * @param               array  Associative array representing the row to be inserted.
     * @param               string Table in which the values will be inserted.
     * @param boolean|array Value  indicating whether or not an existing row
     *                             should be updating when a duplicate key is encountered. If this value is
     *                             an array, only the keys of the values specified in the array will be
     *                             updated. If this is an associative array, the key / value parameters in
     *                             the associative array will be used in place of those found in the first
     *                             parameter.
     *
     * @return boolean Value indicating whether or not the insert succeeded.
     */
    function insertArray($arr, $table, $updateondupe = true)
    {
        if (!preg_match('/^[a-z]\w+/i', $table)) {
            $this->errormessage = "Invalid table '$table' used for insertArray.";

            return false;
        }

        $parameters = [];
        $query = "INSERT INTO $table (";
        foreach ($arr as $key => $value) {
            $parameters[] = $value;
        }

        $query .= '`' . implode('`,`', array_keys($arr)) . '`) VALUES (' . nbinds($parameters) . ')';
        if ($updateondupe) {
            $query .= ' ON DUPLICATE KEY UPDATE ';
            $binds = [];
            foreach ($arr as $key => $value) {
                if ($updateondupe === true or in_array($key, $updateondupe) or isset($updateondupe[$key])) {
                    $binds[] = "`$key` = ?";
                    if ($updateondupe !== true) {
                        if (isset($updateondupe[$key])) {
                            $parameters[] = $updateondupe[$key];
                        } else {
                            $parameters[] = $value;
                        }
                    }
                }
            }
            $query .= implode(', ', $binds);

            if ($updateondupe === true) {
                $parameters = array_merge($parameters, $parameters);
            }
        }

        return $this->execute($query, $parameters) !== false;
    }

    /**
     * Insert values from multiple associative arrays. Works very much like
     * `insertArray` accept instead of a single associative array, multiple
     * ones are passed in, and a single query is used to insert all values at
     * once. All of the arrays should have identical, pre-sorted keys.
     *
     * @param array   Arrays of values to be inserted.
     * @param string  Table in which the values will be inserted.
     * @param boolean Value indicating whether or not an existing row
     * @param boolean Value indicating whether or not 'INSERT' or 'INSERT
     *                IGNORE' should be used for the query.
     *
     * @return boolean Value indicating whether or not the insert succeeded.
     */
    function insertArrays($arrays, $table, $updateondupe = true, $ignoreerrors = false)
    {
        if (!$arrays) {
            return true;
        }

        foreach ($arrays as $ignored => $first) {
            $columns = array_keys($first);
            break;
        }

        $ignore = $ignoreerrors ? 'IGNORE' : '';
        $query = "INSERT $ignore INTO $table (`" . implode('`,`', $columns) . '`) VALUES ';
        $block = '(' . nbinds($columns) . ')';
        $blocks = [];
        $parameters = [];
        foreach ($arrays as $key_is_ignored => $array) {
            foreach ($array as $element) {
                $parameters[] = $element;
            }
            $blocks[] = $block;
        }
        $query .= ' ' . implode(',', $blocks);

        if ($updateondupe) {
            $query .= ' ON DUPLICATE KEY UPDATE ';
            $updates = [];
            foreach ($columns as $column) {
                $updates[] = "`$column` = VALUES(`$column`)";
            }
            $query .= implode(', ', $updates);
        }

        return $this->execute($query, $parameters) !== false;
    }
}