<?php

namespace smtech\CanvasICSSync;

use Battis\BootstrapSmarty\NotificationMessage;
use Log;

class Toolbox extends \smtech\StMarksReflexiveCanvasLTI\Toolbox
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

    protected $syncTimestamp;

    /**
     * Generate a unique identifier for this synchronization pass
     **/
    public function getSyncTimestamp()
    {
        if (empty($this->syncTimestamp)) {
            $timestamp = new DateTime();
            $this->syncTimestamp =
                $timestamp->format(Constants::SYNC_TIMESTAMP_FORMAT) .
                Constants::SEPARATOR . md5(
                    (php_sapi_name() == 'cli' ?
                        'cli' : $_SERVER['REMOTE_ADDR']
                    ) . time()
                );
        }
        return $this->syncTimestamp;
    }

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

    /**
     * Generate a unique ID to identify this particular pairing of ICS feed and
     * Canvas calendar
     **/
    public function getPairingHash($icsUrl, $canvasContext)
    {
        global $metadata;
        return md5($icsUrl . $canvasContext . $metadata[Constants::CANVAS_INSTANCE_URL]);
    }

    public function postMessage($subject, $body, $flag = NotificationMessage::INFO)
    {
        if (php_sapi_name() != 'cli') {
            $this->smarty_addMessage($subject, $body, $flag);
        } else {
            $logEntry = "[$flag] $subject: $body";
            $this->log($logEntry);
        }
    }
}
