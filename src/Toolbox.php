<?php

namespace smtech\CanvasICSSync;

use Battis\BootstrapSmarty\NotificationMessage;
use Log;

class Toolbox extends \smtech\StMarksReflexiveCanvasLTI\Toolbox
{
    /**
     * Write messages to the log file if a script is being run
     * non-interactively from the command line
     *
     * @param string $subject
     * @param string $body
     * @param string $flag (Optional, default `NotificationMessage::INFO`)
     * @param Log $log (Optional, defaults to app log)
     */
    public function smarty_addMessage($subject, $body, $flag = NotificationMessage::INFO, Log $log = null)
    {
        if (empty($log)) {
            $log = $this->getLog();
        }
        if (php_sapi_name() == 'cli') {
            $log->log("[$flag] $subject: $body");
        } else {
            parent::smarty_addMessage($subject, $body, $flag);
        }
    }
}
