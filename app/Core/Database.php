<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

class Database
{
    public static function connection(): PDO
    {
        return getDatabaseConnection();
    }
}