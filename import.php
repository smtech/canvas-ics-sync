<?php

require_once('common.inc.php');

if (isset($argc)) {
	$_REQUEST['cal'] = urldecode($argv[1]);
	$_REQUEST['canvas_url'] = urldecode($argv[2]);
	$_REQUEST['schedule'] = urldecode($argv[3]);
}

/**
 * Check to see if a URL exists
 **/
function urlExists($url) {
	$handle = fopen($url, 'r');
	return $handle !== false;
}

/**
 * compute the calendar context for the canvas object based on its URL
 **/
function getCanvasContext($canvasUrl) {
	global $metadata;
	
	// TODO: accept calendar2?contexts links too (they would be an intuitively obvious link to use, after all)
	// FIXME: users aren't working
	// TODO: it would probably be better to look up users by email address than URL
	/* get the context (user, course or group) for the canvas URL */
	$canvasContext = array();
	if (preg_match('%(https?://)?(' . parse_url($metadata['CANVAS_INSTANCE_URL'], PHP_URL_HOST) . '/((about/(\d+))|(courses/(\d+)(/groups/(\d+))?)|(accounts/\d+/groups/(\d+))))%', $_REQUEST['canvas_url'], $matches)) {
		$canvasContext['canonical_url'] = "https://{$matches[2]}"; // https://stmarksschool.instructure.com/courses/953
		
		// course or account groups
		if (isset($matches[9]) || isset($matches[11])) {
			$canvasContext['context'] = 'group'; // used to for context_code in events 
			$canvasContext['id'] = ($matches[9] > $matches[11] ? $matches[9] : $matches[11]);
			$canvasContext['verification_url'] = "groups/{$canvasContext['id']}"; // used once to look up the object to be sure it really exists
			
		// courses
		} elseif (isset($matches[7])) {
			$canvasContext['context'] = 'course';
			$canvasContext['id'] = $matches[7];
			$canvasContext['verification_url'] = "courses/{$canvasContext['id']}";
		
		// users
		} elseif (isset($matches[5])) {
			$canvasContext['context'] = 'user';
			$canvasContext['id'] = $matches[5];
			$canvasContext['verification_url'] = "users/{$canvasContext['id']}/profile";
		
		// we're somewhere where we don't know where we are
		} else {
			return false;
		}
		return $canvasContext;
	}
	return false;
}

/**
 * Filter and clean event data before posting to Canvas
 *
 * This must happen AFTER the event hash has been calculated!
 **/
function filterEvent($event, $calendarCache) {
	
 	return (
	 	// include this event if filtering is off...
 		$calendarCache['enable_regexp_filter'] == false ||
 		(
			(
				( // if filtering is on, and there's an include pattern test that pattern...
					!empty($calendarCache['include_regexp']) &&
					preg_match("%{$calendarCache['include_regexp']}%", $event->getProperty('SUMMARY'))
				)
			) &&
			!( // if there is an exclude pattern, make sure that this event is NOT excluded
				!empty($calendarCache['exclude_regexp']) &&
				preg_match("%{$calendarCache['exclude_regexp']}%", $event->getProperty('SUMMARY'))
			)
		)
	);
}

// TODO: it would be nice to be able to cleanly remove a synched calendar
// TODO: it would be nice to be able unschedule a scheduled sync without removing the calendar
// TODO: how about something to extirpate non-synced data (could be done right now by brute force -- once overwrite is implemented -- by marking all of the cached events as invalid and then importing the calendar and overwriting, but that's a little icky)
// TODO: right now, if a user changes a synced event in Canvas, it will never get "corrected" back to the ICS feed... we could cache the Canvas events as well as the ICS feed and do a periodic (much less frequent, given the speed of looking everything up in the API) check and re-sync modified events too

/* do we have the vital information (an ICS feed and a URL to a canvas
   object)? */
if (isset($_REQUEST['cal']) && isset($_REQUEST['canvas_url'])) {

	// TODO: need to do OAuth here, so that users are forced to authenticate to verify that they have permission to update these calendars!
	
	if ($canvasContext = getCanvasContext($_REQUEST['canvas_url'])) {
		/* check ICS feed to be sure it exists */
		if(urlExists($_REQUEST['cal'])) {
			/* look up the canvas object -- mostly to make sure that it exists! */
			if ($canvasObject = $api->get($canvasContext['verification_url'])) {
			
				/* calculate the unique pairing ID of this ICS feed and canvas object */
				$pairingHash = getPairingHash($_REQUEST['cal'], $canvasContext['canonical_url']);
				$log = Log::singleton('file', "logs/$pairingHash.log");
				$log->log('Sync started [' . getSyncTimestamp() . ']', PEAR_LOG_INFO);
			
				/* tell users that it's started and to cool their jets */
				$smarty->assign('content', '
					<h3>Calendar Import Started</h3>
					<p>The calendar import that you requested has begun. You may leave this page at anytime. You can see the progress of the import by visiting <a target="_blank" href="https://' . parse_url($metadata['CANVAS_INSTANCE_URL'], PHP_URL_HOST) . "/calendar?include_contexts={$canvasContext['context']}_{$canvasObject['id']}\">this calendar</a> in Canvas.</p>"
				);
				$smarty->display('page.tpl');
				
				/* parse the ICS feed */
				$ics = new vcalendar(
					array(
						'unique_id' => $metadata['APP_ID'],
						'url' => $_REQUEST['cal']
					)
				);
				$ics->parse();
				
				/* log this pairing in the database cache, if it doesn't already exist */
				$calendarCacheResponse = $sql->query("
					SELECT *
						FROM `calendars`
						WHERE
							`id` = '$pairingHash'
				");
				$calendarCache = $calendarCacheResponse->fetch_assoc();
				
				/* if the calendar is already cached, just update the sync timestamp */
				if ($calendarCache) {
					$sql->query("
						UPDATE `calendars`
							SET
								`synced` = '" . getSyncTimestamp() . "'
							WHERE
								`id` = '$pairingHash'
					");
				} else {
					$sql->query("
						INSERT INTO `calendars`
							(
								`id`,
								`name`,
								`ics_url`,
								`canvas_url`,
								`synced`,
								`enable_regexp_filter`,
								`include_regexp`,
								`exclude_regexp`
							)
							VALUES (
								'$pairingHash',
								'" . $sql->real_escape_string($ics->getProperty('X-WR-CALNAME')) . "',
								'{$_REQUEST['cal']}',
								'{$canvasContext['canonical_url']}',
								'" . getSyncTimestamp() . "',
								'" . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER) . "',
								" . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER ? "'" . $sql->real_escape_string($_REQUEST['include_regexp']) . "'" : 'NULL') . ",
								" . ($_REQUEST['enable_regexp_filter'] == VALUE_ENABLE_REGEXP_FILTER ? "'" . $sql->real_escape_string($_REQUEST['exclude_regexp']) . "'" : 'NULL') . "
							)
					");
				}
				
				/* refresh calendar information from cache database */
				$calendarCacheResponse = $sql->query("
					SELECT *
						FROM `calendars`
						WHERE
							`id` = '$pairingHash'
				");
				$calendarCache = $calendarCacheResponse->fetch_assoc();
				
				/* walk through $master_array and update the Canvas calendar to match the
				   ICS feed, caching changes in the database */
				// TODO: would it be worth the performance improvement to just process things from today's date forward? (i.e. ignore old items, even if they've changed...)
				// FIXME: Arbitrarily selecting events in for a year on either side of today's date, probably a better system?
				foreach ($ics->selectComponents( date('Y')-1, date('m'), date('d'), date('Y')+1, date('m'), date('d'), false, false, true, true) as $year) {
					foreach ($year as $month => $days) {
						foreach ($days as $day => $events) {
							foreach ($events as $i => $event) {
			
								/* does this event already exist in Canvas? */
								$eventHash = getEventHash($event);
											
								/* if the event should be included... */
								if (filterEvent($event, $calendarCache)) {
									
									/* have we cached this event already? */
									$eventCacheResponse = $sql->query("
										SELECT *
											FROM `events`
											WHERE
												`calendar` = '{$calendarCache['id']}' AND
												`event_hash` = '$eventHash'
									");
									
					
									/* if we already have the event cached in its current form, just update
									   the timestamp */
									$eventCache = $eventCacheResponse->fetch_assoc();
									if ($eventCache) {
										$sql->query("
											UPDATE `events`
												SET
													`synced` = '" . getSyncTimestamp() . "'
												WHERE
													`id` = '{$eventCache['id']}'
										");
									
									/* otherwise, add this new event and cache it */
									} else {
										/* multi-day event instance start times need to be changed to _this_ date */
										$start = new DateTime(iCalUtilityFunctions::_date2strdate($event->getProperty('DTSTART')));
										$end = new DateTime(iCalUtilityFunctions::_date2strdate($event->getProperty('DTEND')));
										if ($event->getProperty('X-RECURRENCE')) {
											$start = new DateTime($event->getProperty('X-CURRENT-DTSTART')[1]);
											$end = new DateTime($event->getProperty('X-CURRENT-DTEND')[1]);
										}
										$start->setTimeZone(new DateTimeZone(LOCAL_TIMEZONE));
										$end->setTimeZone(new DateTimeZone(LOCAL_TIMEZONE));
										
										$calendarEvent = $api->post("/calendar_events",
											array(
												'calendar_event[context_code]' => "{$canvasContext['context']}_{$canvasObject['id']}",
												'calendar_event[title]' => preg_replace('%^([^\]]+)(\s*\[[^\]]+\]\s*)+$%', '\\1', strip_tags($event->getProperty('SUMMARY'))),
												'calendar_event[description]' => \Michelf\Markdown::defaultTransform(str_replace('\n', "\n\n", $event->getProperty('DESCRIPTION', 1))),
												'calendar_event[start_at]' => $start->format(CANVAS_TIMESTAMP_FORMAT),
												'calendar_event[end_at]' => $end->format(CANVAS_TIMESTAMP_FORMAT),
												'calendar_event[location_name]' => $event->getProperty('LOCATION')
											)
										);

										$sql->query("
											INSERT INTO `events`
												(
													`calendar`,
													`calendar_event[id]`,
													`event_hash`,
													`synced`
												)
												VALUES (
													'{$calendarCache['id']}',
													'{$calendarEvent['id']}',
													'$eventHash',
													'" . getSyncTimestamp() . "'
												)
										");
									}
								}
							}
						}
					}
				}
							
				/* clean out previously synced events that are no longer correct */
				$deletedEventsResponse = $sql->query("
					SELECT * FROM `events`
						WHERE
							`calendar` = '{$calendarCache['id']}' AND
							`synced` != '" . getSyncTimestamp() . "'
				");
				while ($deletedEventCache = $deletedEventsResponse->fetch_assoc()) {
					try {
						$deletedEvent = $api->delete("/calendar_events/{$deletedEventCache['calendar_event[id]']}",
							array(
								'cancel_reason' => getSyncTimestamp(),
								'as_user_id' => ($canvasContext['context'] == 'user' ? $canvasObject['id'] : '') // TODO: this feels skeevy -- like the empty string will break
							)
						);
					} catch (Pest_Unauthorized $e) {
						/* if the event has been deleted in Canvas, we'll get an error when
						   we try to delete it a second time. We still need to delete it from
						   our cache database, however */
						$log->log("Cache out-of-sync: calendar_event[{$deletedEventCache['calendar_event[id]']}] no longer exists and will be purged from cache.", PEAR_LOG_NOTICE);
					} catch (Pest_ClientError $e) {
						$smarty->addMessage(
							'API Client Error',
							'<pre>' . print_r(array(
								'Status' => $PEST->lastStatus(),
								'Error' => $PEST->lastBody(),
								'Verb' => $verb,
								'URL' => $url,
								'Data' => $data
							), false) . '</pre>',
							NotificationMessage::ERROR
						);
						$smarty->display('page.tpl');
						exit;
					}
					$sql->query("
						DELETE FROM `events`
							WHERE
								`id` = '{$deletedEventCache['id']}'
					");
				}
				
				/* if this was a scheduled import (i.e. a sync), update that schedule */
				if (isset($_REQUEST['schedule'])) {
					$sql->query("
						UPDATE `schedules`
							SET
								`synced` = '" . getSyncTimestamp() . "'
							WHERE
								`id` = '{$_REQUEST['schedule']}'
					");
				}
				
				/* are we setting up a regular synchronization? */
				if (isset($_REQUEST['sync']) && $_REQUEST['sync'] != SCHEDULE_ONCE) {
					$shellArguments[INDEX_COMMAND] = dirname(__FILE__) . '/sync.sh';
					$shellArguments[INDEX_SCHEDULE] = $_REQUEST['sync'];
					$shellArguments[INDEX_WEB_PATH] = 'http://localhost' . dirname($_SERVER['PHP_SELF']);
					$crontab = null;
					switch ($_REQUEST['sync']) {
						case SCHEDULE_WEEKLY: {
							$crontab = '0 0 * * 0';
							break;
						}
						case SCHEDULE_DAILY: {
							$crontab = '0 0 * * *';
							break;
						}
						case SCHEDULE_HOURLY: {
							$crontab = '0 * * * *';
							break;
						}
						case SCHEDULE_CUSTOM: {
							$shellArguments[INDEX_SCHEDULE] = md5($_REQUEST['crontab'] . getSyncTimestamp());
							$crontab = trim($_REQUEST['crontab']);
						}
					}
					
					/* schedule crontab trigger, if it doesn't already exist */
					$crontab .= ' ' . implode(' ', $shellArguments);
					
					/* thank you http://stackoverflow.com/a/4421284 ! */
					$crontabs = shell_exec('crontab -l');
					/* check to see if this sync is already scheduled */
					if (strpos($crontabs, $crontab) === false) {
						$filename = md5(getSyncTimestamp()) . '.txt';
						file_put_contents("/tmp/$filename", $crontabs . $crontab . PHP_EOL);
						shell_exec("crontab /tmp/$filename");
						$log->log("added new schedule '" . $shellArguments[INDEX_SCHEDULE] . "' to crontab", PEAR_LOG_INFO);
					}
					
					/* try to make sure that we have execute access to sync.sh */
					chmod('sync.sh', 0775);
	
					/* add to the cache database schedule, replacing any schedules for this
					   calendar that are already there */
					$schedulesResponse = $sql->query("
						SELECT *
							FROM `schedules`
							WHERE
								`calendar` = '{$calendarCache['id']}'
					");
					
					if ($schedule = $schedulesResponse->fetch_assoc()) {
		
						/* only need to worry if the cached schedule is different from the
						   new one we just set */
						if ($shellArguments[INDEX_SCHEDULE] != $schedule['schedule']) {
							/* was this the last schedule to require this trigger? */
							$schedulesResponse = $sql->query("
								SELECT *
									FROM `schedules`
									WHERE
										`calendar` != '{$calendarCache['id']}' AND
										`schedule` == '{$schedule['schedule']}'
							");
							/* we're the last one, delete it from crontab */
							if ($schedulesResponse->num_rows == 0) {
								$crontabs = preg_replace("%^.*{$schedule['schedule']}.*" . PHP_EOL . '%', '', shell_exec('crontab -l'));
								$filename = md5(getSyncTimestamp()) . '.txt';
								file_put_contents("/tmp/$filename", $crontabs);
								shell_exec("crontab /tmp/$filename");
								$log-log("removed unused schedule '{$schedule['schedule']}' from crontab", PEAR_LOG_INFO);
							}
						
							$sql->query("
								UPDATE `schedules`
									SET
										`schedule` = '" . $shellArguments[INDEX_SCHEDULE] . "',
										`synced` = '" . getSyncTimestamp() . "'
									WHERE
										`calendar` = '{$calendarCache['id']}'
							");
						}
					} else {
						$sql->query("
							INSERT INTO `schedules`
								(
									`calendar`,
									`schedule`,
									`synced`
								)
								VALUES (
									'{$calendarCache['id']}',
									'" . $shellArguments[INDEX_SCHEDULE] . "',
									'" . getSyncTimestamp() . "'
								)
						");
					}
				}
				
				/* if we're ovewriting data (for example, if this is a recurring sync, we
				   need to remove the events that were _not_ synced this in this round */
				if (isset($_REQUEST['overwrite']) && $_REQUEST['overwrite'] == VALUE_OVERWRITE_CANVAS_CALENDAR) {
					// TODO: actually deal with this
				}
				
				// TODO: deal with messaging based on context
			
				$log->log('Finished sync ['. getSyncTimestamp() . ']');
				exit;
			} else {
				$smarty->addMessage(
					'Canvas Object  Not Found',
					'The object whose URL you submitted could not be found.<pre>' . print_r(array(
						'Canvas URL' => $_REQUEST['canvas_url'],
						'Canvas Context' => $canvasContext,
						'Canvas Object' => $canvasObject
					), false) . '</pre>',
					NotificationMessage::ERROR
				);
			}
		} else {
			$smarty->addMessage(
				'ICS feed  Not Found',
				'The calendar whose URL you submitted could not be found.<pre>' . $_REQUEST['cal'] . '</pre>',
				NotificationMessage::ERROR
			);
		} 
	} else {
		$smarty->addMessage(
			'Invalid Canvas URL',
			'The Canvas URL you submitted could not be parsed.<pre>' . $_REQUEST['canvas_url'] . '</pre>',
			NotificationMessage::ERROR
		);
		$smarty->display('page.tpl');
		exit;
	}	
}

?>