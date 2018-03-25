<?php
class Db {
    protected $_client;

    static private $_instance = null;

    protected $_config = [
        'pdn'  => 'mysql:dbname=foppes;host=127.0.0.1;charset=utf8',
        'user' => 'foppes',
        'pass' => 'ClVEewp3L3jjyARw' // yeah, yeah, I know, it's my local machine, alright?
    ];

    private function __construct() {
        $this->_client = new PDO($this->_config['pdn'], $this->_config['user'], $this->_config['pass']);
        $this->_client->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    static public function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function getClient() {
        return $this->_client;
    }

    public function getError() {
        return $this->_client->errorInfo();
    }

    public function prepare($sql, $data) {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleaned = array_merge($cleaned, array_values($value));
            } else {
                $cleaned[] = $value;
            }
        }

        $stmt = $this->_client->prepare($sql);
        $stmt->execute($cleaned);

        return $stmt;
    }

    public function fetchAll($table, $where = [], $fields = '*') {
        return $this->prepare($this->_selectString($table, $where, $fields), $where)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetch($table, $where = [], $fields = '*') {
        return $this->prepare($this->_selectString($table, $where, $fields), $where)->fetch(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $values = implode(',', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (" . implode(',', array_keys($data)) . ") VALUES ($values)";

        $this->prepare($sql, $data);

        return $this->_client->lastInsertId();
    }

    public function update($table, $data, $where) {
        $sql = "UPDATE `$table` SET " . $this->_getString($data, ',') . " WHERE " . $this->_getString($where, ' AND ');

        $values = array_merge(array_values($data), array_values($where));

        return $this->prepare($sql, $values);
    }

    public function upsert($table, $data, $where) {
        if ($row = $this->fetch($table, $where)) {
            $this->update($table, $data, $where);
            return $row['id'];
        } else {
            $data = array_merge($where, $data);

            return $this->insert($table, $data);
        }
    }

    protected function _selectString($table, $where = "", $fields = '*') {
        $sql = "SELECT $fields FROM " . $this->_getTableString($table);

        if ($where) {
            $sql .= " WHERE " . $this->_getString($where, ' AND ');
        }

        return $sql;
    }

    protected function _getTableCode($table) {
        $code = substr($table, 0, 1);
        $code .= strlen($table);

        return $code;
    }

    protected function _getTableString($table) {
        $code = $this->_getTableCode($table);

        return "`$table` AS $code";
    }

    protected function _getString($where, $glue) {
        $sql = [];

        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $fields = implode(',', array_fill(0, count($value), '?'));
                $sql[]  = "$key IN ($fields)";
            } else {
                $sql[] = "$key = ?";
            }

        }

        return implode($glue, $sql);
    }
}
