<?php

namespace App\Entities;

use PDO;
use Symfony\Component\VarDumper\Cloner\Data;

class Database
{
    private PDO $pdo;
    private static Database $instance;


    private function __construct()
    {
        $this->pdo = new PDO( "mysql:host=db;dbname=tp;charset=utf8mb4", "root", "root" );
    }

    /**
     * @return PDO
     */
    public static function get(): PDO
    {
       if(!isset (self::$instance))
           self::$instance = new Database();
       return self::$instance->pdo;
    }

}