<?php

if (php_sapi_name() == 'cli') {
    // TODO there is a more streamlined way of doing this that escapes me just this second
    $_REQUEST['cal'] = $argv[1];
    $_REQUEST['canvas_url'] = $argv[2];
    $_REQUEST['schedule'] = $argv[3];

    define('IGNORE_LTI', true);
}

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\LTI\ToolProvider;
use Battis\Educoder\Pest_Unauthorized;
use Battis\Educoder\Pest_ClientError;
use Battis\BootstrapSmarty\NotificationMessage;

/*
 * TODO: it would be nice to be able to cleanly remove a synched calendar
 */
/*
 * TODO: it would be nice to be able unschedule a scheduled sync without
 * removing the calendar
 */
/*
 * TODO: how about something to extirpate non-synced data (could be done right
 * now by brute force -- once overwrite is implemented -- by marking all of the
 * cached events as invalid and then importing the calendar and overwriting,
 * but that's a little icky)
 */
/*
 * TODO: right now, if a user changes a synced event in Canvas, it will never
 * get "corrected" back to the ICS feed... we could cache the Canvas events as
 * well as the ICS feed and do a periodic (much less frequent, given the speed
 * of looking everything up in the API) check and re-sync modified events too
 */

/* do we have the vital information (an ICS feed and a URL to a canvas object)? */
if (empty($_REQUEST['canvas_url'])) {
    $_REQUEST['canvas_url'] =
        $toolbox->config('TOOL_CANVAS_API')['url'] .
        '/courses/' . $_SESSION[ToolProvider::class]['canvas']['course_id'];
}
if (isset($_REQUEST['cal']) && isset($_REQUEST['canvas_url'])) {
    if ($canvasContext = $toolbox->getCanvasContext($_REQUEST['canvas_url'])) {
        /* check ICS feed to be sure it exists */
        if ($toolbox->urlExists($_REQUEST['cal'])) {
            /* look up the canvas object -- mostly to make sure that it exists! */
            $canvasObject = false;
            try {
                $canvasObject = $toolbox->api_get($canvasContext['verification_url']);
            } catch (Exception $e) {
                $toolbox->postMessage(
                    "Error accessing Canvas object",
                    $canvasContext['verification_url'],
                    NotificationMessage::DANGER
                );
            }
            if ($canvasObject) {
                /* calculate the unique pairing ID of this ICS feed and canvas object */
                $pairingHash = $toolbox->getPairingHash($_REQUEST['cal'], $canvasContext['canonical_url']);
                $log = Log::singleton('file', __DIR__ . "/logs/$pairingHash.log");
                $toolbox->postMessage('Sync started', $toolbox->getSyncTimestamp(), NotificationMessage::INFO);

                /* tell users that it's started and to cool their jets */
                if (php_sapi_name() != 'cli') {
                    $toolbox->smarty_assign(
                        'calendarPreviewUrl',
                        $toolbox->config('TOOL_CANVAS_API')['url'] .
                        "/calendar?include_contexts={$canvasContext['context']}_{$canvasObject['id']}"
                    );
                    $toolbox->smarty_display('import-started.tpl');
                }

                /* parse the ICS feed */
                $ics = new vcalendar(
                    array(
                        'unique_id' => $toolbox->config('TOOL_ID'),
                        'url' => $_REQUEST['cal']
                    )
                );
                $ics->parse();

                /* log this pairing in the database cache, if it doesn't already exist */
                $calendarCacheResponse = $toolbox->mysql_query("
                    SELECT *
                        FROM `calendars`
                        WHERE
                            `id` = '$pairingHash'
                ");
                $calendarCache = $calendarCacheResponse->fetch_assoc();

                /* if the calendar is already cached, just update the sync timestamp */
                if ($calendarCache) {
                    $toolbox->mysql_query("
                        UPDATE `calendars`
                            SET
                                `synced` = '" . $toolbox->getSyncTimestamp() . "'
                            WHERE
                                `id` = '$pairingHash'
                    ");
                } else {
                    $toolbox->mysql_query("
                        INSERT INTO `calendars`
                            (
                                `id`,
                                `name`,
                                `ics_url`,
                                `canvas_url`,
                                `synced`,
                                `enable_regexp_filter`,
                                `include_regexp`,
                                `exclude_regexp`
                            )
                            VALUES (
                                '$pairingHash',
                                '" . $toolbox->getMySQL()->escape_string($ics->getProperty('X-WR-CALNAME')[1]) . "',
                                '{$_REQUEST['cal']}',
                                '{$canvasContext['canonical_url']}',
                                '" . $toolbox->getSyncTimestamp() . "',
                                '" . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER) . "',
                                " . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER ?
                                    "'" . $toolbox->getMySQL()->escape_string($_REQUEST['include_regexp']) . "'" : 'NULL'
                                ) . ",
                                " . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER ?
                                    "'" . $toolbox->getMySQL()->escape_string($_REQUEST['exclude_regexp']) . "'" : 'NULL'
                                ) . "
                            )
                    ");
                }

                /* refresh calendar information from cache database */
                $calendarCacheResponse = $toolbox->mysql_query("
                    SELECT *
                        FROM `calendars`
                        WHERE
                            `id` = '$pairingHash'
                ");
                $calendarCache = $calendarCacheResponse->fetch_assoc();

                /*
                 * walk through $master_array and update the Canvas calendar to
                 * match the ICS feed, caching changes in the database */
                /*
                 * TODO: would it be worth the performance improvement to just
                 * process things from today's date forward? (i.e. ignore old
                 * items, even if they've changed...)
                 */
                /*
                 * TODO: the best window for syncing would be the term of the
                 * course in question, right?
                 */
                /*
                 * TODO: Arbitrarily selecting events in for a year on either
                 * side of today's date, probably a better system?
                 */
                foreach ($ics->selectComponents(
                    date('Y') - 1, // startYear
                    date('m'), // startMonth
                    date('d'), // startDay
                    date('Y') + 1, // endYEar
                    date('m'), // endMonth
                    date('d'), // endDay
                    'vevent', // cType
                    false, // flat
                    true, // any
                    true // split
                ) as $year) {
                    foreach ($year as $month => $days) {
                        foreach ($days as $day => $events) {
                            foreach ($events as $i => $event) {
                                /* does this event already exist in Canvas? */
                                $eventHash = $toolbox->getEventHash($event);

                                /* if the event should be included... */
                                if ($toolbox->filterEvent($event, $calendarCache)) {
                                    /* have we cached this event already? */
                                    $eventCacheResponse = $toolbox->mysql_query("
                                        SELECT *
                                            FROM `events`
                                            WHERE
                                                `calendar` = '{$calendarCache['id']}' AND
                                                `event_hash` = '$eventHash'
                                    ");


                                    /* if we already have the event cached in its current form, just update
                                       the timestamp */
                                    $eventCache = $eventCacheResponse->fetch_assoc();
                                    if ($eventCache) {
                                        $toolbox->mysql_query("
                                            UPDATE `events`
                                                SET
                                                    `synced` = '" . $toolbox->getSyncTimestamp() . "'
                                                WHERE
                                                    `id` = '{$eventCache['id']}'
                                        ");

                                    /* otherwise, add this new event and cache it */
                                    } else {
                                        /* multi-day event instance start times need to be changed to _this_ date */
                                        $start = new DateTime(
                                            iCalUtilityFunctions::_date2strdate($event->getProperty('DTSTART'))
                                        );
                                        $end = new DateTime(
                                            iCalUtilityFunctions::_date2strdate($event->getProperty('DTEND'))
                                        );
                                        if ($event->getProperty('X-RECURRENCE')) {
                                            $start = new DateTime($event->getProperty('X-CURRENT-DTSTART')[1]);
                                            $end = new DateTime($event->getProperty('X-CURRENT-DTEND')[1]);
                                        }
                                        $start->setTimeZone(new DateTimeZone(LOCAL_TIMEZONE));
                                        $end->setTimeZone(new DateTimeZone(LOCAL_TIMEZONE));

                                        try {
                                            $calendarEvent = $toolbox->api_post(
                                                "/calendar_events",
                                                array(
                                                    'calendar_event[context_code]' => "{$canvasContext['context']}_{$canvasObject['id']}",
                                                    'calendar_event[title]' => preg_replace('%^([^\]]+)(\s*\[[^\]]+\]\s*)+$%', '\\1', strip_tags($event->getProperty('SUMMARY'))),
                                                    'calendar_event[description]' => \Michelf\Markdown::defaultTransform(str_replace('\n', "\n\n", $event->getProperty('DESCRIPTION', 1))),
                                                    'calendar_event[start_at]' => $start->format(CANVAS_TIMESTAMP_FORMAT),
                                                    'calendar_event[end_at]' => $end->format(CANVAS_TIMESTAMP_FORMAT),
                                                    'calendar_event[location_name]' => $event->getProperty('LOCATION')
                                                )
                                            );
                                            $toolbox->mysql_query("
                                                INSERT INTO `events`
                                                    (
                                                        `calendar`,
                                                        `calendar_event[id]`,
                                                        `event_hash`,
                                                        `synced`
                                                    )
                                                    VALUES (
                                                        '{$calendarCache['id']}',
                                                        '{$calendarEvent['id']}',
                                                        '$eventHash',
                                                        '" . $toolbox->getSyncTimestamp() . "'
                                                    )
                                            ");
                                        } catch (Exception $e) {
                                            $toolbox->postMessage(
                                                'Error creating calendar event',
                                                $eventHash,
                                                NotificationMessage::ERROR
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                /* clean out previously synced events that are no longer correct */
                $deletedEventsResponse = $toolbox->mysql_query("
                    SELECT * FROM `events`
                        WHERE
                            `calendar` = '{$calendarCache['id']}' AND
                            `synced` != '" . $toolbox->getSyncTimestamp() . "'
                ");
                while ($deletedEventCache = $deletedEventsResponse->fetch_assoc()) {
                    try {
                        $deletedEvent = $toolbox->api_delete(
                            "calendar_events/{$deletedEventCache['calendar_event[id]']}",
                            array(
                                'cancel_reason' => $toolbox->getSyncTimestamp(),
                                'as_user_id' => ($canvasContext['context'] == 'user' ? $canvasObject['id'] : '') // TODO: this feels skeevy -- like the empty string will break
                            )
                        );
                    } catch (Pest_Unauthorized $e) {
                        /* if the event has been deleted in Canvas, we'll get an error when
                           we try to delete it a second time. We still need to delete it from
                           our cache database, however */
                        $toolbox->postMessage(
                            'Cache out-of-sync',
                            "calendar_event[{$deletedEventCache['calendar_event[id]']}] no longer exists and will be purged from cache.",
                            NotificationMessage::INFO
                        );
                    } catch (Pest_ClientError $e) {
                        $toolbox->postMessage(
                            'API Client Error',
                            '<pre>' . print_r(array(
                                'Status' => $PEST->lastStatus(),
                                'Error' => $PEST->lastBody(),
                                'Verb' => $verb,
                                'URL' => $url,
                                'Data' => $data
                            ), false) . '</pre>',
                            NotificationMessage::ERROR
                        );
                        if (php_sapi_name() != 'cli') {
                            $toolbox->smarty_display('page.tpl');
                        }
                        exit;
                    }
                    $toolbox->mysql_query("
                        DELETE FROM `events`
                            WHERE
                                `id` = '{$deletedEventCache['id']}'
                    ");
                }

                /* if this was a scheduled import (i.e. a sync), update that schedule */
                if (isset($_REQUEST['schedule'])) {
                    $toolbox->mysql_query("
                        UPDATE `schedules`
                            SET
                                `synced` = '" . $toolbox->getSyncTimestamp() . "'
                            WHERE
                                `id` = '{$_REQUEST['schedule']}'
                    ");
                }
                /* are we setting up a regular synchronization? */
                if (isset($_REQUEST['sync']) && $_REQUEST['sync'] != SCHEDULE_ONCE) {
                    // FIXME CRON SYNC SETUP GOES HERE

                    /* add to the cache database schedule, replacing any schedules for this
                       calendar that are already there */
                    $schedulesResponse = $toolbox->mysql_query("
                        SELECT *
                            FROM `schedules`
                            WHERE
                                `calendar` = '{$calendarCache['id']}'
                    ");

                    if ($schedule = $schedulesResponse->fetch_assoc()) {
                        /* only need to worry if the cached schedule is different from the
                           new one we just set */
                        if ($shellArguments[INDEX_SCHEDULE] != $schedule['schedule']) {
                            /* was this the last schedule to require this trigger? */
                            $schedulesResponse = $toolbox->mysql_query("
                                SELECT *
                                    FROM `schedules`
                                    WHERE
                                        `calendar` != '{$calendarCache['id']}' AND
                                        `schedule` == '{$schedule['schedule']}'
                            ");
                            /* we're the last one, delete it from crontab */
                            if ($schedulesResponse->num_rows == 0) {
                                $crontabs = preg_replace("%^.*{$schedule['schedule']}.*" . PHP_EOL . '%', '', shell_exec('crontab -l'));
                                $filename = md5($toolbox->getSyncTimestamp()) . '.txt';
                                file_put_contents("/tmp/$filename", $crontabs);
                                shell_exec("crontab /tmp/$filename");
                                $toolbox->postMessage('Unused schedule', "removed schedule '{$schedule['schedule']}' from crontab", NotificationMessage::INFO);
                            }

                            $toolbox->mysql_query("
                                UPDATE `schedules`
                                    SET
                                        `schedule` = '" . $shellArguments[INDEX_SCHEDULE] . "',
                                        `synced` = '" . $toolbox->getSyncTimestamp() . "'
                                    WHERE
                                        `calendar` = '{$calendarCache['id']}'
                            ");
                        }
                    } else {
                        $toolbox->mysql_query("
                            INSERT INTO `schedules`
                                (
                                    `calendar`,
                                    `schedule`,
                                    `synced`
                                )
                                VALUES (
                                    '{$calendarCache['id']}',
                                    '" . $_REQUEST['sync'] . "',
                                    '" . $toolbox->getSyncTimestamp() . "'
                                )
                        ");
                    }
                }

                /* if we're ovewriting data (for example, if this is a recurring sync, we
                   need to remove the events that were _not_ synced this in this round */
                if (isset($_REQUEST['overwrite']) && $_REQUEST['overwrite'] == VALUE_OVERWRITE_CANVAS_CALENDAR) {
                    // TODO: actually deal with this
                }

                // TODO: deal with messaging based on context

                $toolbox->postMessage('Finished sync', $toolbox->getSyncTimestamp(), NotificationMessage::INFO);
                exit;
            } else {
                $toolbox->postMessage(
                    'Canvas Object  Not Found',
                    'The object whose URL you submitted could not be found.<pre>' . print_r(array(
                        'Canvas URL' => $_REQUEST['canvas_url'],
                        'Canvas Context' => $canvasContext,
                        'Canvas Object' => $canvasObject
                    ), false) . '</pre>',
                    NotificationMessage::ERROR
                );
            }
        } else {
            $toolbox->postMessage(
                'ICS feed  Not Found',
                'The calendar whose URL you submitted could not be found.<pre>' . $_REQUEST['cal'] . '</pre>',
                NotificationMessage::ERROR
            );
        }
    } else {
        $toolbox->postMessage(
            'Invalid Canvas URL',
            'The Canvas URL you submitted could not be parsed.<pre>' . $_REQUEST['canvas_url'] . '</pre>',
            NotificationMessage::ERROR
        );
        if (php_sapi_name() != 'cli') {
            $toolbox->smarty_display('page.tpl');
        }
        exit;
    }
} else {
    if (php_sapi_name() != 'cli') {
        $toolbox->smarty_display('import.tpl');
    }
}
