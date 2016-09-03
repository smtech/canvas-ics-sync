<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

class Filter
{
    /**
     * Whether or not this filter is enabled
     * @var boolean
     */
    protected $enabled = false;

    /**
     * Regex to include (applied first)
     * @var string
     */
    protected $include;

    /**
     * Regex to exclude (applied second)
     * @var string
     */
    protected $exclude;

    /**
     * @param boolean $enabled
     * @param string|null $include
     * @param string|null $exclude
     */
    public function __construct($enabled, $include, $exclude)
    {
        $this->setEnabled($enabled);
        $this->setIncludeExpression($include);
        $this->setExcludeExpression($exclude);
    }

    public function setEnabled($enabled)
    {
        $this->enabled = (boolean) $enabled;
    }

    public function isEnabled()
    {
        return $enabled;
    }

    public function setIncludeExpression($regex)
    {
        $this->include = (string) $regex;
    }

    public function getIncludeExpression()
    {
        if (empty($this->include)) {
            return false;
        }
        return $this->include;
    }

    public function setExcludeExpression($regex)
    {
        $this->exclude = (string) $regex;
    }

    public function getExcludeExpression()
    {
        if (empty($this->exclude)) {
            return false;
        }
        return $this->exclude;
    }

    public function filter(Event $event)
    {
        return (
            // include this event if filtering is off...
            $this->isEnabled() == false ||
            (
                (
                    ( // if filtering is on, and there's an include pattern test that pattern...
                        $this->getIncludeExpression() &&
                       preg_match('%' . $this->getIncludeExpression() . '%', $event->getProperty('SUMMARY'))
                    )
                ) &&
               !( // if there is an exclude pattern, make sure that this event is NOT excluded
                    $this->getExcludeExpression() &&
                   preg_match('%' . $this->getExcludeExpression() . '%', $event->getProperty('SUMMARY'))
                )
            )
        );
    }
}
