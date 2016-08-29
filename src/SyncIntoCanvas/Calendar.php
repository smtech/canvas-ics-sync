<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

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
        global $metadata;
        return md5($this->getContext() . $this->getFeedUrl());
    }

    public function save()
    {
        $sql = static::getMySql();

        $params = [
            'id' => $sql->escape_string($this->getPairingHash()),
            'name' => $sql->escape_string($this->getName()),
            'canvas_url' => $sql->escape_string($this->getContext()->getCanonicalUrl()),
            'ics_url' => $sql->escape_string($this->getFeedUrl()),
            'enable_regex_filter' => $this->getFilter()->isEnabled(),
            'include_regexp' => $sql->escape_string($this->getFilter()->getIncludeExpression()),
            'exclude_regexp' => $sql->escape_string($this->getFilter()->getExcludeExpression())
        ];
        foreach ($params as $field => $value) {
            if (empty($value)) {
                $params[$field] = 'NULL';
            } else {
                $params[$field] = "'$value'";
            }
        }
        $response = static::getMySql()->query("
            SELECT * FROM `calendars` WHERE `id` = '$_id'
        ");
        $previous = $response->fetch_assoc();
        if ($previous) {
            $query = "UPDATE `calendars` SET\n";
            foreach ($params as $field => $value) {
                if ($key != 'id') {
                    $query .= "`$field` = $value\n";
                }
            }
            $query .= "WHERE `id` = '{$params['id']}'";
        } else {
            $query = "INSERT INTO `calendars` (\n`";
            $query .= implode('`, `', array_keys($params));
            $query .= "`) VALUES (";
            $query .= implode(', ', $params);
            $query .= ')';
        }
        if (static::getMySql($query)->query() === false) {
            throw new Exception(
                "Failed to store calendar ID {$this->id} to database"
            );
        }
    }

    /**
     * Load a Calendar from the MySQL database
     *
     * @param int $id
     * @return Calendar
     */
    public static function load($id)
    {
        $sql = static::getMySql();
        $query = "SELECT * FROM `calendars` WHERE `id` = '" . $sql->escape_string($id) . "'";
        $response = $sql->query($query);
        if ($response) {
            $calendar = $response->fetch_assoc();
            return new Calendar($calendar['canvas_url'], $calendar['ics_url'], $calendar['enable_regex_filter'], $calendar['include_regexp'], $calendar['exclude_regexp']);
        }
        return null;
    }
}
