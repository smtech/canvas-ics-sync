<?php

require_once('common.inc.php');

// FIXME: should filter so that the syncs for the server we're running against (INDEX_WEB_PATH) are called (or is that already happening?)
$schedulesResponse = $sql->query("
	SELECT *
		FROM `schedules`
		WHERE
			`schedule` = '" . $sql->real_escape_string($argv[INDEX_SCHEDULE]) . "'
		ORDER BY
			`synced` ASC
");

while($schedule = $schedulesResponse->fetch_assoc()) {
	$calendarResponse = $sql->query("
		SELECT *
			FROM `calendars`
			WHERE
				`id` = '{$schedule['calendar']}'
	");
	if ($calendar = $calendarResponse->fetch_assoc()) {
		shell_exec('curl -u ' . $secrets->mysql->user . ':' . $secrets->mysql->password . ' -k "https://skunkworks.stmarksschool.org/canvas/ics-sync/import.php?cal=' . urlencode($calendar['ics_url']) . '&canvas_url=' . urlencode($calendar['canvas_url']) . '&schedule=' . urlencode($schedule['id']) . '"');
	}
}

?>