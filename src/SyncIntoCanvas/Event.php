<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use vevent;
use iCalUtilityFunctions;
use Michelf\Markdown;

class Event extends Syncable
{
    /*
     * TODO:0 Can we detect the timezone for the Canvas instance and use it? issue:18
     */
    const LOCAL_TIMEZONE = 'US/Eastern';
    const CANVAS_TIMESTAMP_FORMAT = 'Y-m-d\TH:iP';

    const FIELD_MAP = [
        'calendar_event[title]' => 'SUMMARY',
        'calendar_event[description]' => 'DESCRIPTION',
        'calendar_event[start_at]' => [
            0 => 'X-CURRENT-DTSTART',
            1 => 'DTSTART'
        ],
        'calendar_event[end_at]' => [
            0 => 'X-CURRENT-DTEND',
            1 => 'DTEND'
        ],
        'calendar_event[location_name]' => 'LOCATION'
    ];

    /**
     * VEVENT
     * @var vevent
     */
    protected $vevent;

    /**
     * Calendar
     * @var Calendar
     */
    protected $calendar;

    /**
     * Unique hash identifying this version of this event
     * @var string
     */
    protected $hash;

    protected $canvasId;

    public function __construct(vevent $vevent, Calendar $calendar)
    {
        if (empty($vevent)) {
            throw new Exception(
                'Valid VEVENT required'
            );
        }
        $this->vevent = $vevent;

        if (empty($calendar)) {
            throw new Exception(
                'Valid Calendar required'
            );
        }
        $this->calendar = $calendar;

        $this->getHash();
    }

    public function getProperty($property)
    {
        return (empty($this->vevent) ? false : $this->vevent->getProperty($property));
    }

    public function getCalendar()
    {
        return $this->calendar;
    }

    /**
     * Generate a hash of this version of an event to cache in the database
     **/
    public function getHash($algorithm = 'md5')
    {
        if (empty($this->hash)) {
            $blob = '';
            foreach (static::$FIELD_MAP as $field) {
                if (is_array($field)) {
                    foreach ($field as $option) {
                        if (!empty($property = $this->getProperty($option))) {
                            $blob .= serialize($property);
                            break;
                        }
                    }
                } else {
                    if (!empty($property = $this->getProperty($field))) {
                        $blob .= serialize($property);
                    }
                }
            }
            $this->hash = hash($algorithm, $blob);
        }
        return $this->hash;
    }

    public function save()
    {
        $db = static::getDatabase();
        $api = static::getApi();

        $select = $db->prepare(
            "SELECT *
                FROM `events`
                WHERE
                    `event_hash` = :event_hash AND
                    `calendar` = :calendar"
        );
        $update = $db->prepare(
            "UPDATE `events`
                SET
                    `synced` = :synced
                WHERE
                    `event_hash` = :event_hash AND
                    `calendar` = :calendar"
        );
        $insert = $db->prepare(
            "INSERT INTO `events`
                (
                    `calendar`,
                    `calendar_event[id]`,
                    `event_hash`,
                    `synced`
                ) VALUES (
                    :calendar,
                    :calendar_event_id,
                    :event_hash,
                    :synced
                )"
        );

        $params = [
            'calendar' => $this->getCalendar()->getId(),
            'event_hash' => $this->getHash(),
            'synced' => static::getTimestamp()
        ];

        $select->execute($params);
        if ($select->fetch() !== false) {
            $update->execute($params);
        } else {
            /*
             * FIXME: how sure are we of this? issue:14
             */
            /*
             * multi-day event instance start times need to be changed to
             * _this_ date
             */
            $start = new DateTime(
                iCalUtilityFunctions::_date2strdate(
                    $this->getProperty('DTSTART')
                )
            );
            $end = new DateTime(
                iCalUtilityFunctions::_date2strdate(
                    $this->getProperty('DTEND')
                )
            );
            if ($this->getProperty('X-RECURRENCE')) {
                $start = new DateTime($this->getProperty('X-CURRENT-DTSTART')[1]);
                $end = new DateTime($this->getProperty('X-CURRENT-DTEND')[1]);
            }
            $start->setTimeZone(new DateTimeZone(self::LOCAL_TIMEZONE));
            $end->setTimeZone(new DateTimeZone(self::LOCAL_TIMEZONE));

            $calendarEvent = $api->post(
                "/calendar_events",
                [
                    'calendar_event' => [
                        'context_code' => $this->getCalendar()->getContextCode(),
                        /*
                         * TODO this should be configurable issue:5
                         */
                        /* removing trailing [TAGS] from event title */
                        'title' => preg_replace(
                            '%^([^\]]+)(\s*\[[^\]]+\]\s*)+$%',
                            '\\1',
                            strip_tags($this->getProperty('SUMMARY'))
                        ),
                        'description' => Markdown::defaultTransform(
                            str_replace(
                                '\n',
                                "\n\n",
                                $this->getProperty('DESCRIPTION', 1)
                            )
                        ),
                        'start_at' => $start->format(self::CANVAS_TIMESTAMP_FORMAT),
                        'end_at' => $end->format(self::CANVAS_TIMESTAMP_FORMAT),
                        'location_name' => $this->getProperty('LOCATION')
                    ]
                ]
            );
            $params['calendar_event_id'] = $calendarEvent['id'];
            $insert->execute($params);
        }
    }

    public static function purgeUnmatched($timestamp, $calendar)
    {
        $db = static::getDatabase();
        $api = static::getApi();

        $findDeletedEvents = $db->prepare(
            "SELECT *
                FROM `events`
                WHERE
                    `calendar` = :calendar AND
                    `synced` != :synced"
        );
        $deleteCachedEvent = $db->prepare(
            "DELETE FROM `events` WHERE `id` = :id"
        );

        $findDeletedEvents->execute([
            'calendar' => $calendar->getId(),
            'synced' => $timestamp
        ]);
        if (($deletedEvents = $findDeletedEvents->fetchAll()) !== false) {
            foreach ($deletedEvents as $event) {
                $params['cancel_reason'] = $timestamp;
                if ($calendar->getContext()->getContext() == 'user') {
                    $params['as_user_id'] = $calendar->getContext()->getId();
                }
                try {
                    $api->delete(
                        "/calendar_events/{$event['calendar_event[id]']}",
                        $params
                    );
                } catch (Pest_Unauthorized $e) {
                    /*
                     * Do nothing: an event was cached that had been deleted
                     * from Canvas -- deleting the cached event is
                     * inconsequential
                     */
                }
                $deleteCachedEvent->execute($event['id']);
            }
        }
    }
}
