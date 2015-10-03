<?php
	
require_once('common.inc.php');

$smarty->assign('name', $metadata['COURSE_NAVIGATION_LINK_TEXT']);
$smarty->assign('category', '');
$smarty->assign('formAction', $metadata['APP_URL'] . '/import.php');
$smarty->assign('formHidden', array(
	'canvas_url' => $_SESSION['canvasInstanceUrl']. '/courses/' . $_SESSION['toolProvider']->user->getResourceLink()->settings['custom_canvas_course_id'],
	'sync' => SCHEDULE_HOURLY
));
$smarty->display('course.tpl');

?>