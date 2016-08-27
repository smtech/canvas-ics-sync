<?php

namespace smtech\CanvasICSSync;

class Constants
{
    /* argument values for sync */
    const SCHEDULE_ONCE = 'once';
    const SCHEDULE_WEEKLY = 'weekly';
    const SCHEDULE_DAILY = 'daily';
    const SCHEDULE_HOURLY = 'hourly';
    const SCHEDULE_CUSTOM = 'custom';

    /*
     * TODO:0 Can we detect the timezone for the Canvas instance and use it? issue:18
     */
    const LOCAL_TIMEZONE = 'US/Eastern';
    const SEPARATOR = '_'; // used when concatenating information in the cache database
    const CANVAS_TIMESTAMP_FORMAT = 'Y-m-d\TH:iP';
    const SYNC_TIMESTAMP_FORMAT = CANVAS_TIMESTAMP_FORMAT;

    const VALUE_OVERWRITE_CANVAS_CALENDAR = 'overwrite';
    const VALUE_ENABLE_REGEXP_FILTER = 'enable_filter';

    const CANVAS_INSTANCE_URL = 'CANVAS_INSTANCE_URL';
}
