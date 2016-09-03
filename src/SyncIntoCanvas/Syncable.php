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
    protected static $timestamp;

    /**
     * Generate a unique identifier for this synchronization pass
     **/
    public static function getTimestamp()
    {
        if (empty(static::$timestamp)) {
            $timestamp = new DateTime();
            static::$timestamp =
                $timestamp->format(Constants::SYNC_TIMESTAMP_FORMAT) .
                Constants::SEPARATOR . md5(
                    (php_sapi_name() == 'cli' ?
                        'cli' : $_SERVER['REMOTE_ADDR']
                    ) . time()
                );
        }
        return static::$timestamp;
    }

    /**
     * Update the MySQL connection
     *
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
     * Get unique identifier for this object
     *
     * @return string|int
     */
    abstract public function getId();

    /**
     * Save the syncable object to the database and Canvas
     *
     * @return void
     */
    abstract public function save();

    /**
     * Delete the syncable object from the database and Canvas
     *
     * @return void
     */
    abstract public function delete();
}
