<?php

define('CONFIG_FILE', __DIR__ . '/config.xml');
define('CANVAS_INSTANCE_URL', 'canvas_instance_url');

/* argument values for sync */
define('SCHEDULE_ONCE', 'once');
define('SCHEDULE_WEEKLY', 'weekly');
define('SCHEDULE_DAILY', 'daily');
define('SCHEDULE_HOURLY', 'hourly');
define('SCHEDULE_CUSTOM', 'custom');

/*
 * TODO:0 Can we detect the timezone for the Canvas instance and use it?
 * issue:18
 */
define('LOCAL_TIMEZONE', 'US/Eastern');
define('SEPARATOR', '_'); // used when concatenating information in the cache database
define('CANVAS_TIMESTAMP_FORMAT', 'Y-m-d\TH:iP');
define('SYNC_TIMESTAMP_FORMAT', 'Y-m-d\TH:iP'); // same as CANVAS_TIMESTAMP_FORMAT, FWIW

define('VALUE_OVERWRITE_CANVAS_CALENDAR', 'overwrite');
define('VALUE_ENABLE_REGEXP_FILTER', 'enable_filter');
