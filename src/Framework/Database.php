<?php

namespace Tunnela\TremedCodingChallenge\Framework;

class Database
{
    protected $connection;

    protected $options = [];

    public function __construct($options = [])
    {
        $defaults = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'password' => '',
            'username' => 'root',
            'database' => '',
            'file' => ''
        ];

        $this->options = array_merge($defaults, $options);
    }

    public function connect()
    {
        if ($this->connection) {
            return $this;
        }
        $options = $this->options;

        if (empty($options['file'])) {
            $dsn = "{$options['driver']}:{$options['file']}dbname={$options['database']};" .
            "host={$options['host']};port={$options['port']}";
        } else {
            $dsn = "{$options['driver']}:{$options['file']}";
        }
        $this->connection = $connection = new \PDO(
            $dsn, 
            $options['username'] ?? null, 
            $options['password'] ?? null, 
            $options['options'] ?? null
        );

        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $connection;
    }

    public function __call($name, $arguments)
    {
        if (!$this->connection) {
            $this->connect();
        }
        return call_user_func_array([$this->connection, $name], $arguments);
    }
}
