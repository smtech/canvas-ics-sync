<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

use DateTime;
use vcalendar;
use Battis\DataUtilities;

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
    protected function getId($algorithm = 'md5')
    {
        return hash($algorithm, $this->getContext()->getCanonicalUrl() . $this->getFeedUrl());
    }

    public function getContextCode()
    {
        return $this->getContext()->getContext() . '_' . $this->getContext()->getId();
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

    public function sync(Log $log)
    {
        try {
            $api->get($this->getContext()->getVerificationUrl());
        } catch (Exception $e) {
            $this->logThrow(new Exception("Cannot sync calendars without a valid Canvas context"), $log);
        }

        if (!DataUtilities::URLexists($this>getFeedUrl())) {
            $this->logThrow(new Exception("Cannot sync calendars with a valid calendar feed"), $log);
        }

        $this->log(static::getTimestamp() . ' sync started', $log);

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
                    foreach ($events as $i => $_event) {
                        try {
                            $event = new Event($_event, $this);
                            if ($this->getFilter()->filter($event)) {
                                $event->save();
                            } else {
                                $event->delete();
                            }
                        } catch (Exception $e) {
                            $this->logThrow($e, $log);
                        }
                    }
                }
            }
        }

        try {
            Event::purgeUnmatched(static::getTimestamp(), $this);
        } catch (Exception $e) {
            $this->logThrow($e, $log);
        }

        $this->log(static::getTimestamp() . ' sync finished', $log);
    }

    private function log($message, Log $log, $flag = PEAR_LOG_INFO)
    {
        if ($log) {
            $log->log($message, $flag);
        }
    }

    private function logThrow(
        Exception $exception,
        Log $log,
        $flag = PEAR_LOG_ERR
    ) {
        if ($log) {
            $log->log($e->getMessage() . ': ' . $e->getTraceAsString(), $flag);
        } else {
            throw $e;
        }
    }
}
