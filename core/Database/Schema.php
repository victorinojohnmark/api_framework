<?php
namespace Core\Database;

class Schema
{
    protected static $db;

    public static function setConnection($db)
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
        // Check if we are using PDO (exec) or Custom DB (query)
        if (self::$db instanceof \PDO) {
            self::$db->exec("RENAME TABLE `$from` TO `$to`");
        } else {
            self::$db->query("RENAME TABLE `$from` TO `$to`");
        }
    }

    /**
     * Drop a Table
     */
    public static function drop($table)
    {
        if (self::$db instanceof \PDO) {
            self::$db->exec("DROP TABLE `$table`");
        } else {
            self::$db->query("DROP TABLE `$table`");
        }
    }

    public static function dropIfExists($table)
    {
        if (self::$db instanceof \PDO) {
            self::$db->exec("DROP TABLE IF EXISTS `$table`");
        } else {
            self::$db->query("DROP TABLE IF EXISTS `$table`");
        }
    }

    /**
     * Execute the Blueprint SQL
     */
    private static function execute(Blueprint $blueprint)
    {
        $sql = $blueprint->build();
        if ($sql) {
            // Support both Raw PDO and your Core\Database wrapper
            if (self::$db instanceof \PDO) {
                self::$db->exec($sql);
            } else {
                self::$db->query($sql);
            }
        }
    }
}