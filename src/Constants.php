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

    const VALUE_OVERWRITE_CANVAS_CALENDAR = 'overwrite';
    const VALUE_ENABLE_REGEXP_FILTER = 'enable_filter';

    const CANVAS_INSTANCE_URL = 'CANVAS_INSTANCE_URL';
}
