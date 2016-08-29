<?php

namespace smtech\CanvasICSSync\SyncIntoCanvas;

class Schedule
{
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
}
