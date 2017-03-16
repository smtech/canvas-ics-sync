<?php
namespace smtech\CanvasICSSync;

use smtech\LTI\Configuration\Option;
use Battis\HierarchicalSimpleCache;
use Battis\BootstrapSmarty\NotificationMessage;
use DateTime;

/**
 * St. Marks Reflexive Canvas LTI Example toolbox
 *
 * Adds some common, useful methods to the St. Mark's-styled
 * ReflexiveCanvasLTI Toolbox
 *
 * @author  Seth Battis <SethBattis@stmarksschool.org>
 * @version v1.2
 */
class Toolbox extends \smtech\StMarksReflexiveCanvasLTI\Toolbox
{
    private $FIELD_MAP = array(
        'calendar_event[title]' => 'SUMMARY',
        'calendar_event[description]' => 'DESCRIPTION',
        'calendar_event[start_at]' => array(
            0 => 'X-CURRENT-DTSTART',
            1 => 'DTSTART'
        ),
        'calendar_event[end_at]' => array(
            0 => 'X-CURRENT-DTEND',
            1 => 'DTEND'
        ),
        'calendar_event[location_name]' => 'LOCATION'
    );

    private $SYNC_TIMESTAMP = null;

    /**
     * Configure course and account navigation placements
     *
     * @return Generator
     */
    public function getGenerator()
    {
        parent::getGenerator();
        $this->generator->setOptionProperty(
            Option::COURSE_NAVIGATION(),
            'visibility',
            'admins'
        );
        return $this->generator;
    }

    /**
     * Load the app schema into the database
     *
     * @return void
     */
    public function loadSchema()
    {
        /* ...so that we can find the LTI_Tool_Provider database schema (oy!) */
        foreach (explode(';', file_get_contents(dirname(__DIR__) . '/schema.sql')) as $query) {
            if (!empty(trim($query))) {
                /*
                 * TODO should there be some sort of testing or logging here?
                 *      If _some_ tables are present, that will trigger
                 *      reloading all tables, which will generate ignorable
                 *      errors.
                 */
                $this->mysql_query($query);
            }
        }
        $this->log('Application database schema loaded.');
    }

    /**
     * Check to see if a URL exists
     **/
    public function urlExists($url)
    {
        $handle = fopen($url, 'r');
        return $handle !== false;
    }

    /**
     * compute the calendar context for the canvas object based on its URL
     **/
    public function getCanvasContext($canvasUrl)
    {
        /*
         * TODO: accept calendar2?contexts links too (they would be an intuitively
         * obvious link to use, after all)
         */
        /*
         * FIXME: users aren't working
         */
        /*
         * TODO: it would probably be better to look up users by email address than
         * URL
         */
        /* get the context (user, course or group) for the canvas URL */
        $canvasContext = array();
        if (preg_match(
            '%(https?://)?(' .
            parse_url($this->config('TOOL_CANVAS_API')['url'], PHP_URL_HOST) .
            '/((about/(\d+))|(courses/(\d+)(/groups/(\d+))?)|(accounts/\d+/groups/(\d+))))%',
            $canvasUrl,
            $matches
        )) {
            $canvasContext['canonical_url'] = "https://{$matches[2]}"; // https://stmarksschool.instructure.com/courses/953

            // course or account groups
            if (isset($matches[9]) || isset($matches[11])) {
                $canvasContext['context'] = 'group'; // used to for context_code in events
                $canvasContext['id'] = ($matches[9] > $matches[11] ? $matches[9] : $matches[11]);

                /* used once to look up the object to be sure it really exists */
                $canvasContext['verification_url'] = "groups/{$canvasContext['id']}";

            // courses
            } elseif (isset($matches[7])) {
                $canvasContext['context'] = 'course';
                $canvasContext['id'] = $matches[7];
                $canvasContext['verification_url'] = "courses/{$canvasContext['id']}";

            // users
            } elseif (isset($matches[5])) {
                $canvasContext['context'] = 'user';
                $canvasContext['id'] = $matches[5];
                $canvasContext['verification_url'] = "users/{$canvasContext['id']}/profile";

            // we're somewhere where we don't know where we are
            } else {
                return false;
            }
            return $canvasContext;
        }
        return false;
    }

    /**
     * Filter and clean event data before posting to Canvas
     *
     * This must happen AFTER the event hash has been calculated!
     **/
    public function filterEvent($event, $calendarCache)
    {
            return (
            (
                // TODO actual multi-day events would be nice
                // only include first day of multi-day events
                $event->getProperty('X-OCCURENCE') == false ||
                preg_match('/^day 1 of \d+$/i', $event->getProperty('X-OCCURENCE')[1])
            ) &&
            (
                // include this event if filtering is off...
                    $calendarCache['enable_regexp_filter'] == false ||
                 (
                    (
                        ( // if filtering is on, and there's an include pattern test that pattern...
                            !empty($calendarCache['include_regexp']) &&
                            preg_match("%{$calendarCache['include_regexp']}%", $event->getProperty('SUMMARY'))
                        )
                    ) &&
                    !( // if there is an exclude pattern, make sure that this event is NOT excluded
                        !empty($calendarCache['exclude_regexp']) &&
                        preg_match("%{$calendarCache['exclude_regexp']}%", $event->getProperty('SUMMARY'))
                    )
                )
            )
        );
    }

    public function postMessage($subject, $body, $flag = NotificationMessage::INFO)
    {
        global $toolbox;
        if (php_sapi_name() != 'cli') {
            $this->smarty_addMessage($subject, $body, $flag);
        } else {
            $logEntry = "[$flag] $subject: $body";
            $this->log($logEntry);
        }
    }

    /**
     * Generate a unique ID to identify this particular pairing of ICS feed and
     * Canvas calendar
     **/
    public function getPairingHash($icsUrl, $canvasContext)
    {
        return md5($icsUrl . $canvasContext . $this->config('CANVAS_INSTANCE_URL'));
    }

    /**
     * Generate a hash of this version of an event to cache in the database
     **/
    public function getEventHash($event)
    {
        $blob = '';
        foreach ($this->FIELD_MAP as $field) {
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
     * Generate a unique identifier for this synchronization pass
     **/
    public function getSyncTimestamp()
    {
        if ($this->SYNC_TIMESTAMP) {
            return $this->SYNC_TIMESTAMP;
        } else {
            $timestamp = new DateTime();
            $this->SYNC_TIMESTAMP = $timestamp->format(SYNC_TIMESTAMP_FORMAT) . SEPARATOR .
                md5((php_sapi_name() == 'cli' ? 'cli' : $_SERVER['REMOTE_ADDR']) . time());
            return $this->SYNC_TIMESTAMP;
        }
    }
}
