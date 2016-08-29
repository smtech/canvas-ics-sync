<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use mysqli;
use smtech\CanvasPest\CanvasPest;

/**
 * A syncable object
 *
 * @author Seth Battis <sethbattis@stmarksschool.org>
 */
abstract class Syncable
{
    /**
     * MySQL connection
     *
     * @var mysqli|null
     */
    protected static $mysql;

    /**
     * Canvas API connection
     *
     * @var CanvasPest|null
     */
    protected static $api;

    /**
     * Update the MySQL connection
     *
     * @param mysqli $mysql
     * @throws Exception If `$mysql` is null
     */
    public static function setMySql(mysqli $mysql)
    {
        if (empty($mysql)) {
            throw new Exception(
                'A non-null MySQL connection is required'
            );
        } else {
            static::$mysql = $mysql;
        }
    }

    /**
     * Get the MySQL connection
     *
     * @return mysqli
     */
    public static function getMySql()
    {
        return static::$mysql;
    }

    /**
     * Update the API connection
     *
     * @param CanvasPest $api
     * @throws Exception if `$api` is null
     */
    public static function setApi(CanvasPest $api)
    {
        if (empty($api)) {
            throw new Exception(
                'A non-null API connection is required'
            );
        } else {
            static::$api = $api;
        }
    }

    /**
     * Get the API connection
     *
     * @return CanvasPest|null
     */
    public static function getApi()
    {
        return static::$api;
    }

    /**
     * Save the syncable object to the MySQL database
     *
     * @return [type] [description]
     */
    abstract public function save();

    /**
     * Load a syncable object from the MySQL database
     *
     * @param int $id
     * @return Syncable
     */
    abstract public static function load($id);
}
