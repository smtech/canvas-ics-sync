<?php

require_once('common.inc.php');

/* replace the contents of this file with your own app logic */

$api = new CanvasPest($_SESSION['apiUrl'], $_SESSION['apiToken']);
$profile = $api->get('/users/self/profile');

$smarty->assign('content', '
<h3>Choose Import/Export Direction</h3>
	<p>In which direction do you want to send your information>?</p>
	<ul>
		<li><a href="export.php">Export:</a> I would like to get an ICS feed of the calendar information (which I can subscribe to in Google, iCal, Outlook, etc.) for a specific course in Canvas.</li>
		<li><a href="import.php">Import:</a> I have an ICS feed (from Google, Smartsheet, iCloud, etc.) that I want to bring into a Canvas course, user or group.</li>
	</ul>'
);

$smarty->display();

?>