<?php
namespace Core\Database;

use Core\Database;

class Schema
{
    protected static $db;

    public static function setConnection(Database $db)
    {
        self::$db = $db;
    }

    /**
     * Create a new Table
     */
    public static function create($table, callable $callback)
    {
        $blueprint = new Blueprint($table, true); // true = Creating
        $callback($blueprint);
        self::execute($blueprint);
    }

    /**
     * Modify an existing Table
     */
    public static function table($table, callable $callback)
    {
        $blueprint = new Blueprint($table, false); // false = Altering
        $callback($blueprint);
        self::execute($blueprint);
    }

    /**
     * Rename a Table
     */
    public static function rename($from, $to)
    {
        self::$db->query("RENAME TABLE `$from` TO `$to`");
    }

    /**
     * Drop a Table
     */
    public static function drop($table)
    {
        self::$db->query("DROP TABLE `$table`");
    }

    public static function dropIfExists($table)
    {
        self::$db->query("DROP TABLE IF EXISTS `$table`");
    }

    /**
     * Execute the Blueprint SQL
     */
    private static function execute(Blueprint $blueprint)
    {
        $sql = $blueprint->build();
        if ($sql) {
            self::$db->query($sql);
        }
    }
}