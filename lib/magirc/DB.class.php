<?php

use Psr\Log\LoggerInterface;

// define the query types
define('SQL_NONE', 1);
define('SQL_ALL', 2);
define('SQL_INIT', 3);

// define the query formats
define('SQL_ASSOC', PDO::FETCH_ASSOC);
define('SQL_INDEX', PDO::FETCH_NUM);
define('SQL_OBJ', PDO::FETCH_OBJ);
define('SQL_NAME', PDO::FETCH_NAMED);

// define the parameter formats
define('SQL_NULL', PDO::PARAM_NULL);
define('SQL_BOOL', PDO::PARAM_BOOL);
define('SQL_INT', PDO::PARAM_INT);
define('SQL_STR', PDO::PARAM_STR);

class DB {
    private $pdo;
    private $result;
    public $error;
    public $record;
    private ?LoggerInterface $logger = null;

    private function logError(string $message, Throwable $exception): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error($message, ['exception_class' => $exception::class]);
            return;
        }
        error_log($message . ' [' . $exception::class . ']');
    }

    /** Quote only application-controlled SQL identifiers; values remain bound/quoted separately. */
    private function quoteIdentifier($identifier): string
    {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_$.-]+$/D', $identifier)) {
            throw new InvalidArgumentException('Unsafe SQL identifier.');
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function __construct($dsn, $username, $password, $args = null, ?LoggerInterface $logger = null) {
        $this->logger = $logger ?? (class_exists(\MagIRC\Logging\LoggerFactory::class) ? \MagIRC\Logging\LoggerFactory::get() : null);
        $this->connect($dsn, $username, $password, $args);
    }

    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Establish a connection to a Database
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @return boolean true: successful, false: failed
     */
    public function connect($dsn, $username, $password, $args) {
        $options = is_array($args) ? $args : [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        $options[PDO::ATTR_PERSISTENT] = false;
        if (str_starts_with($dsn, 'mysql:')) {
            $bufferedAttribute = class_exists(\Pdo\Mysql::class)
                ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
                : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
            $options[$bufferedAttribute] = true;
        }
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->error = null;
            return true;
        } catch (PDOException $exception) {
            $this->pdo = null;
            $this->logError('MagIRC database connection failed.', $exception);
            $this->error = 'Database connection failed.';
            return false;
        }
    }

    /**
     * Disconnect from the database server
     */
    public function disconnect() {
        $this->pdo = null;
    }

    /**
     * Get the tables
     * @return array
     */
    public function getTables() {
        $query = "SHOW TABLES";
        $this->query($query, SQL_ALL, SQL_INDEX);
        return $this->record;
    }

    /**
     * Create a prepared statement
     * @param string $query
     * @return PDOStatement
     */
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }

    /**
     * Runs the given query
     * @param string $query
     * @param int $type SQL_NONE: without result, SQL_INIT: one result, SQL_ALL: all results
     * @param int $format SQL_INDEX: indexed array, SQL_ASSOC: associative array, SQL_OBJ object
     * @return boolean true: success, false: failure
     */
    public function query($query, $type = SQL_NONE, $format = SQL_INDEX) {
        try {
            $this->record = null;
            $this->result = $this->pdo->query($query);
            switch ($type) {
                case SQL_ALL:
                    $this->record = $this->result->fetchAll($format);
                    break;
                case SQL_INIT:
                    $this->record = $this->result->fetch($format);
                    break;
                case SQL_NONE:
                default:
                    break;
            }
            $this->error = null;
            return true;
        } catch(Exception $e) {
            $this->logError('MagIRC database query failed.', $e);
            $this->record = false;
            $this->error = 'Database query failed.';
            return false;
        }
    }

    /**
     * Iterate over the next record
     * @param int $format SQL_INDEX: indexed array, SQL_ASSOC: associative array, SQL_OBJ object
     * @return mixed record on success, false on failure
     */
    public function next($format = SQL_INDEX) {
        $this->record = $this->result->fetch($format);
        if ($this->record) {
            return $this->record;
        }
        return false;
    }

    /**
     * Escape the given string
     * @param string $input
     * @return string Escaped string
     */
    public function escape($input) {
        return $this->pdo->quote($input);
    }

    /**
     * Get the ID of the last inserted row
     * @return int ID
     */
    public function lastInsertID() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Number of returned rows
     * @return int Count
     */
    public function numRows() {
        try {
            return $this->result->rowCount();
        } catch(Exception $e) {
            $this->logError('MagIRC database row count failed.', $e);
            return 0;
        }
    }

    /**
     * Fetch the first column of the last result
     * @return mixed Value
     */
    public function fetchColumn() {
        try {
            return $this->result->fetchColumn();
        } catch(Exception $e) {
            $this->logError('MagIRC database fetch failed.', $e);
            return false;
        }
    }

    /**
     * Return the amount of found rows from the last query
     * @return int Rows
     */
    public function foundRows() {
        $ps = $this->prepare("SELECT FOUND_ROWS()");
        $ps->execute();
        return $ps->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Build and run a SELECT query
     * @param string $table Table
     * @param array $where (column => value)
     * @param string $sort ORDER BY ...
     * @param string $order ASC/DESC
     * @param int $limit LIMIT ...
     * @param int $type SQL_NONE: without result, SQL_INIT: one result, SQL_ALL: all results
     * @param int $format SQL_INDEX: indexed array, SQL_ASSOC: associative array, SQL_OBJ object
     * @return mixed
     */
    private function select($table, $where = NULL, $sort = NULL, $order = 'ASC', $limit = 0, $type = SQL_ALL, $format = SQL_ASSOC) {
        $query = "SELECT * FROM " . $this->quoteIdentifier($table);

        if ($where) {
            $conditions = "";
            foreach($where as $key => $value) {
                $conditions .= $this->quoteIdentifier($key) . ' = ' . $this->escape($value) . ' AND ';
            }
            $query .= " WHERE " . substr($conditions, 0, -5);
        }

        if ($sort) {
            $direction = strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC';
            $query .= ' ORDER BY ' . $this->quoteIdentifier($sort) . ' ' . $direction;
        }

        if ($limit) {
            $safeLimit = filter_var($limit, FILTER_VALIDATE_INT);
            if ($safeLimit === false || $safeLimit < 1) {
                throw new InvalidArgumentException('Unsafe SQL limit.');
            }
            $query .= ' LIMIT ' . $safeLimit;
        }

        $this->query($query, $type, $format);
        return $this->record;
    }

    /**
     * Build and run a SELECT query and return one row
     * @param string $table Table
     * @param array $where (column => value)
     * @param string $sort ORDER BY ...
     * @param string $order ASC/DESC
     * @param int $limit LIMIT ...
     * @return mixed
     */
    public function selectOne($table, $where = NULL, $sort = NULL, $order = 'ASC', $limit = 0) {
        return $this->select($table, $where, $sort, $order, $limit, SQL_INIT, $format = SQL_ASSOC);
    }
    /**
     * Build and run a SELECT query and return all rows
     * @param string $table Table
     * @param array $where (column => value)
     * @param string $sort ORDER BY ...
     * @param string $order ASC/DESC
     * @param int $limit LIMIT ...
     * @return mixed
     */
    public function selectAll($table, $where = NULL, $sort = NULL, $order = 'ASC', $limit = 0) {
        return $this->select($table, $where, $sort, $order, $limit, SQL_ALL, $format = SQL_ASSOC);
    }

    /**
     * Build and run an INSERT query
     * @param string $table Table
     * @param array $array Values (column => value)
     * @return int Last inserted ID
     */
    public function insert($table, $array) {
        $query = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' SET ';

        foreach($array as $key => $value) {
            $query .= $this->quoteIdentifier($key) . ' = ' . $this->escape($value) . ', ';
        }
        $query = substr($query, 0, -2) . ";";

        if ($this->query($query)) {
            return $this->lastInsertID();
        }
        return 0;
    }

    /**
     * Build and run an UPDATE query
     * @param string $table Table
     * @param array $array Values (column => value)
     * @param array $where WHERE (column => value)
     * @return mixed
     */
    public function update($table, $array, $where) {
        $data = null;
        foreach($array as $key => $value) {
            $data .= $this->quoteIdentifier($key) . ' = ' . $this->escape($value) . ', ';
        }
        $data = substr($data, 0, -2);

        $conditions = null;
        foreach($where as $key => $value) {
            $conditions .= $this->quoteIdentifier($key) . ' = ' . $this->escape($value) . ' AND ';
        }
        $conditions = substr($conditions, 0, -5);

        $query = 'UPDATE ' . $this->quoteIdentifier($table) . " SET {$data} WHERE {$conditions}";

        return $this->query($query);
    }

    /**
     * Build and run a DELETE query
     * @param string $table Table
     * @param mixed $data int: id, array: (column => value)
     * @return mixed
     */
    public function delete($table, $data) {
        if (is_array($data)) {
            $query = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ';
            foreach($data as $key => $value) {
                $query .= $this->quoteIdentifier($key) . ' = ' . $this->escape($value) . ' AND ';
            }
            $query = substr($query, 0, -5);
        } else {
            $query = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE `id` = ' . $this->escape($data);
        }
        return $this->query($query);
    }

    /**
     * Gate the total data set length (used by DataTables)
     * @param string $sQuery SQL Query
     * @param array $aParams (column => value)
     * @return mixed
     */
    public function datatablesTotal($sQuery, $aParams = []) {
        $sQuery = preg_replace('#SELECT\s.*\s?FROM\s#is', 'SELECT COUNT(*) FROM ', $sQuery, 1);
        $ps = $this->prepare($sQuery);
        foreach ($aParams as $key => &$val) {
            $ps->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $ps->execute();
        return $ps->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Build the LIMIT portion of a query (used by DataTables)
     * @return string LIMIT statement
     */
    public function datatablesPaging() {
        if (!isset($_GET['start'], $_GET['length']) || !is_scalar($_GET['start']) || !is_scalar($_GET['length'])) {
            return 'LIMIT 0, 100';
        }
        $start = filter_var($_GET['start'], FILTER_VALIDATE_INT);
        $length = filter_var($_GET['length'], FILTER_VALIDATE_INT);
        $start = ($start === false || $start < 0) ? 0 : $start;
        $length = $length === false ? 100 : $length;
        if ($length === -1 || $length > 500) {
            $length = 500;
        }
        if ($length < 1) {
            $length = 100;
        }
        return 'LIMIT ' . $start . ', ' . $length;
    }

    /**
     * Build the ORDER BY portion of a query (used by DataTables)
     * @return string ORDER BY statement
     */
    public function datatablesOrdering(array $allowedColumns = []) {
        if (!$allowedColumns || !isset($_GET['order']) || !is_array($_GET['order']) || !isset($_GET['columns']) || !is_array($_GET['columns'])) {
            return '';
        }
        $parts = [];
        foreach ($_GET['order'] as $order) {
            if (!is_array($order) || !isset($order['column']) || !is_scalar($order['column']) || !ctype_digit((string) $order['column'])) {
                continue;
            }
            $index = (int) $order['column'];
            if (!isset($_GET['columns'][$index]) || !is_array($_GET['columns'][$index])) {
                continue;
            }
            $column = $_GET['columns'][$index];
            if (!isset($column['data']) || !is_string($column['data']) || !isset($allowedColumns[$column['data']]) || (isset($column['orderable']) && $column['orderable'] !== 'true' && $column['orderable'] !== true)) {
                continue;
            }
            $identifier = $allowedColumns[$column['data']];
            if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/D', $identifier)) {
                continue;
            }
            $quoted = '`' . str_replace('.', '`.`', $identifier) . '`';
            $direction = isset($order['dir']) && is_string($order['dir']) && strtolower($order['dir']) === 'desc' ? 'DESC' : 'ASC';
            $parts[] = $quoted . ' ' . $direction;
        }
        return $parts !== [] ? 'ORDER BY ' . implode(', ', $parts) : '';
    }

    /**
     * Build the WHERE portion of a query to filter results (used by DataTables)
     * @param array $columns Column names
     * @return string WHERE statement
     */
    public function datatablesFiltering($columns = []) {
        if (!$columns || !isset($_GET['search']['value']) || !is_string($_GET['search']['value']) || $_GET['search']['value'] === '') {
            return '';
        }
        $conditions = [];
        foreach ($columns as $column) {
            if (!is_string($column) || !preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/D', $column)) {
                continue;
            }
            $identifier = '`' . str_replace('.', '`.`', $column) . '`';
            $conditions[] = $identifier . ' LIKE ' . $this->escape('%' . $_GET['search']['value'] . '%');
        }
        return $conditions !== [] ? '(' . implode(' OR ', $conditions) . ')' : '';
    }

    /**
     * Output the server-side array for DataTables, to be converted in JSON
     * @param int $recordsTotal Total records
     * @param int $recordsFiltered Total displayed records
     * @param array $data Data
     * @return array (draw, recordsTotal, recordsFiltered, data)
     */
    public function datatablesOutput($recordsTotal, $recordsFiltered, $data) {
        return [
            'draw' => isset($_GET['draw']) && is_scalar($_GET['draw']) ? (int) $_GET['draw'] : 0,
            'recordsTotal' => (int) $recordsTotal,
            'recordsFiltered' => (int) $recordsFiltered,
            'data' => $data
        ];
    }

}
