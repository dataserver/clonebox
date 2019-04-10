<?php
class MyPDO
{
    protected $transactionCounter = 0;

    protected static $instance;
    protected $pdo;

    
    public function __construct()
    {
        try {
            $this->pdo = new PDO("sqlite:". CONFIG['database']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw $e;
        }
    }
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    // a proxy to native PDO methods
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->pdo, $method), $args);
    }
    
    public function beginTransaction()
    {
        if (!$this->transactionCounter++) {
            return $this->pdo->beginTransaction();
        }

        return $this->transactionCounter >= 0;
    }

    public function commit()
    {
        if (!--$this->transactionCounter) {
            return $this->pdo->commit();
        }

        return $this->transactionCounter >= 0;
    }

    public function rollback()
    {
        if ($this->transactionCounter >= 0) {
            $this->transactionCounter = 0;

            return $this->pdo->rollback();
        }
        $this->transactionCounter = 0;

        return false;
    }
}
