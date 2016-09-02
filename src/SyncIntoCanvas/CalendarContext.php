<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

class CalendarContext
{
    /**
     * The canonical URL for this context in Canvas
     * @var string
     */
    protected $canonicalUrl;

    /**
     * The context for this calendar in Canvas (user, group, course)
     * @var CanvasContext
     */
    protected $context;

    /**
     * Unique ID for this Canvas context
     * @var int
     */
    protected $id;

    /**
     * URL to verify this context against API
     * @var string
     */
    protected $verificationUrl;

    /**
     * Compute the calendar context for the canvas object based on its URL
     *
     * @param string $canvasUrl URL to the context for a calendar on this
     *     Canvas instance
     * @throws Exception If `$canvasInstance` is not a URL on this Canvas
     *     instance
     * @throws Exception if `$canvasInstance` is not a URL to a recognizable
     *     calendar context
     */
    public function __construct($canvasUrl)
    {
        /*
         * TODO: accept calendar2?contexts links too (they would be an intuitively obvious link to use, after all)
         */
        /*
         * FIXME: users aren't working
         */
        /*
         * TODO: it would probably be better to look up users by email address than URL
         */
        /* get the context (user, course or group) for the canvas URL */
        if (preg_match('%(https?://)?(' . parse_url($_SESSION[Constants::CANVAS_INSTANCE_URL], PHP_URL_HOST) . '/((about/(\d+))|(courses/(\d+)(/groups/(\d+))?)|(accounts/\d+/groups/(\d+))))%', $canvasUrl, $match)) {
            $this->canonicalUrl = "https://{$match[2]}"; // https://stmarksschool.instructure.com/courses/953

            // course or account groups
            if (isset($match[9]) || isset($match[11])) {
                $this->context = CanvasContext::GROUP(); // used for context_code in events
                $this->id = ($match[9] > $match[11] ? $match[9] : $match[11]);
                $this->verificationUrl = "groups/{$this->id}"; // used once to look up the object to be sure it really exists

            // courses
            } elseif (isset($match[7])) {
                $this->context = CanvasContext::COURSE();
                $this->id = $match[7];
                $this->verificationUrl = "courses/{$this->id}";

            // users
            } elseif (isset($match[5])) {
                $this->context = CanvasContext::USER();
                $this->id = $match[5];
                $this->verificationUrl = "users/{$this->id}/profile";

            // we're somewhere where we don't know where we are
            } else {
                throw new Exception(
                    "'$canvasUrl' is not a recognizable calendar context"
                );
            }
        }
        throw new Exception(
            "'$canvasUrl' is not recognized as a URL to a calendar context on this Canvas instance"
        );
    }

    public function getCanonicalUrl()
    {
        return $this->canonicalUrl;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getVerificationUrl()
    {
        return $this->verificationUrl;
    }

    public function __toString()
    {
        return $this->getContext();
    }
}
