<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use PDO;
use smtech\CanvasPest\CanvasPest;

/**
 * A syncable object
 *
 * @author Seth Battis <sethbattis@stmarksschool.org>
 */
abstract class Syncable
{
    /**
     * Database connection
     *
     * @var PDO|null
     */
    protected static $database;

    /**
     * Canvas API connection
     *
     * @var CanvasPest|null
     */
    protected static $api;

    /**
     * Unique timestamp for the current sync
     * @var string
     */
    protected static $syncTimestamp;

    /**
     * Generate a unique identifier for this synchronization pass
     **/
    public static function getSyncTimestamp()
    {
        if (empty(static::$syncTimestamp)) {
            $timestamp = new DateTime();
            static::$syncTimestamp =
                $timestamp->format(Constants::SYNC_TIMESTAMP_FORMAT) .
                Constants::SEPARATOR . md5(
                    (php_sapi_name() == 'cli' ?
                        'cli' : $_SERVER['REMOTE_ADDR']
                    ) . time()
                );
        }
        return static::$syncTimestamp;
    }

    /**
     * Update the MySQL connection
     *
     * @param PDO $db
     * @throws Exception If `$db` is null
     */
    public static function setDatabase(PDO $database)
    {
        if (empty($database)) {
            throw new Exception(
                'A non-null database connection is required'
            );
        } else {
            static::$database = $database;
        }
    }

    /**
     * Get the MySQL connection
     *
     * @return mysqli
     */
    public static function getDatabase()
    {
        return static::$db;
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
     * @return Syncable|null
     */
    abstract public static function load($id);
}
