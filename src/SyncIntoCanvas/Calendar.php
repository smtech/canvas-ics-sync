<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use DateTime;
use vcalendar;
use iCalUtilityFunctions;
use Battis\DataUtilities;
use Michelf\Markdown;

class Calendar
{
    /**
     * Canvas calendar context
     * @var CalendarContext
     */
    protected $context;

    /**
     * ICS or webcal feed URL
     * @var string
     */
    protected $feedUrl;

    /**
     * Name of this calendar (extracted from feed)
     * @var string
     */
    protected $name;

    /**
     * Filter for events in this calendar
     * @var Filter
     */
    protected $filter;

    /**
     * Construct a Calendar object
     *
     * @param string $canvasUrl URL of a Canvas calendar context
     * @param string $feedUrl URL of a webcal or ICS calendar feed
     * @param boolean $enableFilter (Optional, default `false`)
     * @param string $include (Optional) Regular expression to select events
     *     for inclusion in the calendar sync
     * @param string $exclude (Optional) Regular expression to select events
     *     for exclusion from the calendar sync
     */
    public function __construct($canvasUrl, $feedUrl, $enableFilter = false, $include = null, $exclude = null)
    {
        $this->setContext(new CalendarContext($canvasUrl));
        $this->setFeed($feedUrl);
        $this->setFilter(new Filter(
            $enableFilter,
            $include,
            $exclude
        ));
    }

    /**
     * Set the Canvas calendar context
     *
     * @param CalendarContext $context
     * @throws Exception If `$context` is null
     */
    public function setContext(CalendarContext $context)
    {
        if (!empty($context)) {
            $this->context = $context;
        } else {
            throw new Exception(
                'Context cannot be null'
            );
        }
    }

    /**
     * Get the Canvas calendar context
     *
     * @return CalendarContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set the webcal or ICS feed URl for this calendar
     *
     * @param string $feedUrl
     * @throws Exception If `$feedUrl` is not a valid URL
     */
    public function setFeedUrl($feedUrl)
    {
        if (!empty($feedUrl)) {
            /* crude test to see if the feed is a valid URL */
            $handle = fopen($feedUrl, 'r');
            if ($handle !== false) {
                $this->feedUrl = $feedUrl;
            }
        }
        throw new Exception(
            'Feed must be a valid URL'
        );
    }

    /**
     * Get the feed URL for this calendar
     *
     * @return string
     */
    public function getFeedUrl()
    {
        return $this->feedUrl;
    }

    /**
     * Set the name of the calendar
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = (string) $name;
    }

    /**
     * Get the name of the calendar
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the regular expression filter for this calendar
     *
     * @param Filter $filter
     */
    public function setFilter(Filter $filter)
    {
        $this->filter = $filter;
    }

    /**
     * Get the regular expression filter for this calendar
     *
     * @return Filter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Generate a unique ID to identify this particular pairing of ICS feed and
     * Canvas calendar
     **/
    protected function getPairingHash()
    {
        return md5($this->getContext()->getCanonicalUrl() . $this->getFeedUrl());
    }

    public function save()
    {
        $db = static::getDatabase();
        $find = $db->prepare(
            "SELECT * FROM `calendars` WHERE `id` = :id"
        );
        $update = $db->prepare(
            "UPDATE `calendars`
                SET
                    `name` = :name,
                    `canvas_url` = :canvas_url,
                    `ics_url` = :ics_url,
                    `synced` = :synced,
                    `enable_regex_filter` = :enable_regex_filter,
                    `include_regexp` = :include_regexp,
                    `exclude_regexp` = :exclude_regexp
                WHERE
                `id` = :id"
        );
        $insert = $db->prepare(
            "INSERT INTO `calendars`
                (
                    `id`,
                    `name`,
                    `canvas_url`,
                    `ics_url`,
                    `synced`,
                    `enable_regex_filter`,
                    `include_regexp`,
                    `exclude_regexp`
                ) VALUES (
                    :id,
                    :name,
                    :canvas_url,
                    :ics_url,
                    :synced
                    :enable_regex_filter,
                    :include_regexp,
                    :exclude_regexp
                )"
        );
        $params = [
            'id' => $this->getPairingHash(),
            'name' => $this->getName(),
            'canvas_url' => $this->getContext()->getCanonicalUrl(),
            'ics_url' => $this->getFeedUrl(),
            'synced' => static::getSyncTimestamp(),
            'enable_regex_filter' => $this->getFilter()->isEnabled(),
            'include_regexp' => $this->getFilter()->getIncludeExpression(),
            'exclude_regexp' => $this->getFilter()->getExcludeExpression()
        ];

        $find->execute($params);
        if ($find->fetch() !== false) {
            $update->execute($params);
        } else {
            $insert->execute($params);
        }
    }

    /**
     * Load a Calendar from the database
     *
     * @param int $id
     * @return Calendar
     */
    public static function load($id)
    {
        $find = static::getDatabase()->prepare(
            "SELECT * FROM `calendars` WHERE `id` = :id"
        );
        $find->execute($id);
        if (($calendar = $find->fetch()) !== false) {
            return new Calendar(
                $calendar['canvas_url'],
                $calendar['ics_url'],
                $calendar['enable_regex_filter'],
                $calendar['include_regexp'],
                $calendar['exclude_regexp']
            );
        }
        return null;
    }

    public function import(Log $log)
    {
        $db = static::getDatabase();
        $api = static::getApi();

        /*
         * This will throw an exception if it's not found
         */
        $canvasObject = $api->get($this->getContext()->getVerificationUrl());

        if (!DataUtilities::URLexists($this>getFeedUrl())) {
            throw new Exception(
                "Cannot sync calendars with a valid calendar feed"
            );
        }

        if ($log) {
            $log->log(static::getSyncTimestamp() . ' sync started', PEAR_LOG_INFO);
        }
        $this->save();

        $ics = new vcalendar([
            'unique_id' => __FILE__,
            'url' => $this->getFeedUrl()
        ]);
        $ics->parse();

        /*
         * TODO: would it be worth the performance improvement to just process
         *     things from today's date forward? (i.e. ignore old items, even
         *     if they've changed...)
         */
        /*
         * TODO:0 the best window for syncing would be the term of the course
         *     in question, right? issue:12
         */
        /*
         * TODO:0 Arbitrarily selecting events in for a year on either side of
         *     today's date, probably a better system? issue:12
         */
        foreach ($ics->selectComponents(
            date('Y')-1, // startYear
            date('m'), // startMonth
            date('d'), // startDay
            date('Y')+1, // endYEar
            date('m'), // endMonth
            date('d'), // endDay
            'vevent', // cType
            false, // flat
            true, // any
            true // split
        ) as $year) {
            foreach ($year as $month => $days) {
                foreach ($days as $day => $events) {
                    foreach ($events as $i => $_event) {
                        $event = new Event($_event, $this->getPairingHash());
                        if ($this->getFilter()->filterEvent($event)) {
                            $eventCache = Event::load($this->getPairingHash(), $eventHash);
                            if (empty($eventCache)) {
                                /* multi-day event instance start times need to be changed to _this_ date */
                                $start = new DateTime(
                                    iCalUtilityFunctions::_date2strdate(
                                        $event->getProperty('DTSTART')
                                    )
                                );
                                $end = new DateTime(
                                    iCalUtilityFunctions::_date2strdate(
                                        $event->getProperty('DTEND')
                                    )
                                );
                                if ($event->getProperty('X-RECURRENCE')) {
                                    $start = new DateTime($event->getProperty('X-CURRENT-DTSTART')[1]);
                                    $end = new DateTime($event->getProperty('X-CURRENT-DTEND')[1]);
                                }
                                $start->setTimeZone(new DateTimeZone(Constants::LOCAL_TIMEZONE));
                                $end->setTimeZone(new DateTimeZone(Constants::LOCAL_TIMEZONE));

                                try {
                                    $calendarEvent = $api->post(
                                        "/calendar_events",
                                        [
                                            'calendar_event' => [
                                                'context_code' => $this->getContext() . "_{$canvasObject['id']}",
                                                'title' => preg_replace(
                                                    '%^([^\]]+)(\s*\[[^\]]+\]\s*)+$%',
                                                    '\\1',
                                                    strip_tags($event->getProperty('SUMMARY'))
                                                ),
                                                'description' => Markdown::defaultTransform(str_replace(
                                                    '\n',
                                                    "\n\n",
                                                    $event->getProperty('DESCRIPTION', 1)
                                                )),
                                                'start_at' => $start->format(Constants::CANVAS_TIMESTAMP_FORMAT),
                                                'end_at' => $end->format(Constants::CANVAS_TIMESTAMP_FORMAT),
                                                'location_name' => $event->getProperty('LOCATION')
                                            ]
                                        ]
                                    );
                                } catch (Exception $e) {
                                    if ($log) {
                                        $log->log($e->getMessage(), PEAR_LOG_ERR);
                                    } else {
                                        throw $e;
                                    }
                                }

                                $eventCache = new Event($this->getPairingHash(), $canvasObject['id'], $eventHash);
                            }
                            $eventCache->save();
                        }
                    }
                }
            }
        }

        $findDeletedEvents = $db->prepare(
            "SELECT *
                FROM `events`
                WHERE
                    `calendar` = :calendar AND
                    `synced` != :synced
            "
        );
        $deleteCachedEvent = $db->prepare(
            "DELETE FROM `events` WHERE `id` = :id"
        );
        $findDeletedEvents->execute([
            'calendar' => $this->getPairingHash(),
            'synced' => static::getSyncTimestamp()
        ]);
        if (($deletedEvents = $findDeletedEvents->fetchAll()) !== false) {
            foreach ($deletedEvents as $eventCache) {
                try {
                    $api->delete(
                        "/calendar_events/{$eventCache['calendar_event[id]']}",
                        [
                            'cancel_reason' => getSyncTimestamp(),
                            /*
                             * TODO: this feels skeevy -- like the empty string
                             *     will break
                             */
                            'as_user_id' => ($this->getContext()->getContext() == 'user' ? $canvasObject['id'] : '')
                        ]
                    );
                    $deleteCachedEvent->execute($eventCache['id']);
                } catch (Pest_Unauthorized $e) {
                    if ($log) {
                        $log->log(
                            "calendar_event[{$eventCache['calendar_event[id]']}] no longer exists and will be purged from cache.",
                            PEAR_LOG_WARN
                        );
                    } else {
                        throw $e;
                    }
                } catch (Exception $e) {
                    if ($log) {
                        $log->log($e->getMessage(), PEAR_LOG_ERR);
                    } else {
                        throw $e;
                    }
                }
            }
        }

        if ($log) {
            $log->log(static::getSyncTimestamp() . ' sync finished', PEAR_LOG_INFO);
        }
    }
}
