<?php

require_once('common.inc.php');

if (isset($_REQUEST['course_url'])) {
	$eventsApi = new CanvasPest($_SESSION['apiUrl'], $_SESSION['apiToken']);
	
	// TODO work nicely with the cache (purge uncached events, or only cached events, etc.)
	
	$events = $eventsApi->get('calendar_events',
		array(
			'type' => 'event',
			'all_events' => true,
			'context_codes[]' => preg_replace('|.*/courses/(\d+)/?.*|', "course_$1", $_REQUEST['course_url'])
		)
	);
	do {
		foreach($events as $event) {
			$api->delete("calendar_events/{$event['id']}",
				array(
					'cancel_reason' => $metadata['APP_NAME'] . " course_url={$_REQUEST['course_url']}"
				)
			);
		}
	} while($events = $eventsApi->nextPage());
	
	$smarty->assign('content', 'Calendar purged.');
} else {
	$smarty->assign('content', '<form action="' . $_SERVER['PHP_SELF'] . '" method="post"><label for="course_url">Course URL <input id="course_url" name="course_url" type="text" /><input type="submit" value="Purge All Calendar Events" /></form>');
}

$smarty->display();

?>