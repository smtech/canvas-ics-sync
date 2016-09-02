<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use vevent;

class Event extends Syncable
{
    /**
     * Wrapped VEVENT
     * @var vevent
     */
    protected $vevent;

    /**
     * Calendar ID
     * @var string
     */
    protected $calendar;

    /**
     * Unique hash identifying this event
     * @var string
     */
    protected $hash;

    protected $canvasId;

    public function __construct($veventOrHash, $calendar, $calendarEventId = null)
    {
        if (empty($veventOrHash)) {
            throw new Exception("Valid VEVENT or hash required");
        }
        if (is_a($veventOrHash, vevent::class)) {
            $this->vevent = $veventOrHash;
        } else {
            $this->hash = (string) $veventOrHash;
        }

        if (empty($calendar)) {
            throw new Exception("Calendar ID required");
        }
        $this->calendar = (string) $calendar;

        $this->getHash();
    }

    public function getProperty($property)
    {
        return (empty($this->vevent) ? false : $this->vevent->getProperty($property));
    }

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

    public function isCached()
    {
        return !empty(static::load($this->getHash(), $this->calendar));
    }

    public function save()
    {
        $db = static::getDatabase();

        $find = $db->prepare(
            "SELECT *
                FROM `events`
                WHERE
                    `event_hash` = :event_hash AND
                    `calendar` = :calendar"
        );
        $update = $db->prepare(
            "UPDATE `events`
                SET
                    `calendar` = :calendar,
                    `calendar_event[id]` = :calendar_event_id"
        )
    }

    public static function load($id, $calendar)
    {
        $find = static::getDatabase()->prepare(
            "SELECT *
                FROM `events`
                WHERE
                    `event_hash` = :event_hash AND
                    `calendar` = :calendar"
        );
        $find->execute([
            'event_hash' => $id,
            'calendar' => $calendar
        ]);
        if (($cache = $find->fetch()) !== false) {
            return new Event($cache['event_hash'], $cache['calendar']);
        }
        return null;
    }
}
