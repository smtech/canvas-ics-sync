<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

class Event
{
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
    public function getEventHash($event)
    {
        $blob = '';
        foreach (static::$FIELD_MAP as $field) {
            if (is_array($field)) {
                foreach ($field as $option) {
                    if (!empty($property = $event->getProperty($option))) {
                        $blob .= serialize($property);
                        break;
                    }
                }
            } else {
                if (!empty($property = $event->getProperty($field))) {
                    $blob .= serialize($property);
                }
            }
        }
        return md5($blob);
    }
}
