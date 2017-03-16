<?php

define('IGNORE_LTI', true);

require_once('common.inc.php');

$toolbox->log("Checking {$argv[1]} schedule...");

/*
 * FIXME: should filter so that the syncs for the server we're running against
 * (INDEX_WEB_PATH) are called (or is that already happening?)
 */
$schedulesResponse = $toolbox->mysql_query("
	SELECT *
		FROM `schedules`
		WHERE
			`schedule` = '" . $toolbox->getMySQL()->real_escape_string($argv[1]) . "'
		ORDER BY
			`synced` ASC
");

while ($schedule = $schedulesResponse->fetch_assoc()) {
    $calendarResponse = $toolbox->mysql_query("
		SELECT *
			FROM `calendars`
			WHERE
				`id` = '{$schedule['calendar']}'
	");
    if ($calendar = $calendarResponse->fetch_assoc()) {
        echo shell_exec(
            'php ' . __DIR__ . '/import.php ' .
            $calendar['ics_url'] . ' ' .
            $calendar['canvas_url'] . ' ' .
            $schedule['id']
        );
    }
}
