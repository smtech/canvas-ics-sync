<?php

/* some sample app metadata information -- review config.xml for a panoply of options */
$metadata['APP_DESCRIPTION'] = 'Import ICS feeds into Canvas, access individual Canvas ICS feeds';
$metadata['APP_DOMAIN'] = '';
$metadata['APP_ICON_URL'] = '@APP_URL/lti/icon.png';
$metadata['APP_LAUNCH_URL'] = '@APP_URL/lti/launch.php';
$metadata['APP_PRIVACY_LEVEL'] = 'public'; # /public|name_only|anonymous/
$metadata['APP_CONFIG_URL'] = '@APP_URL/lti/config.xml';
$metadata['ACCOUNT_NAVIGATION'] = 'TRUE'; # /TRUE|FALSE/
$metadata['ACCOUNT_NAVIGATION_ENABLED'] = 'true'; # /true|false/
$metadata['ACCOUNT_NAVIGATION_LAUNCH_URL'] = '@APP_LAUNCH_URL';
$metadata['ACCOUNT_NAVIGATION_LINK_TEXT'] = 'Calendar Sync';
$metadata['COURSE_NAVIGATION'] = 'TRUE'; # /TRUE|FALSE/
$metadata['COURSE_NAVIGATION_DEFAULT'] = 'enabled'; # /enabled|disabled/
$metadata['COURSE_NAVIGATION_ENABLED'] = 'true'; # /true|false/
$metadata['COURSE_NAVIGATION_LAUNCH_URL'] = '@APP_LAUNCH_URL';
$metadata['COURSE_NAVIGATION_LINK_TEXT'] = '@ACCOUNT_NAVIGATION_LINK_TEXT';
$metadata['COURSE_NAVIGATION_VISIBILITY'] = 'admins'; # /public|members|admins/
$metadata['CUSTOM_FIELDS'] = 'FALSE'; # /TRUE|FALSE/
$metadata['EDITOR_BUTTON'] = 'FALSE'; # /TRUE|FALSE/
$metadata['HOMEWORK_SUBMISSION'] = 'FALSE'; # /TRUE|FALSE/
$metadata['RESOURCE_SELECTION'] = 'FALSE'; # /TRUE|FALSE/
$metadata['USER_NAVIGATION'] = 'TRUE'; # /TRUE|FALSE/
$metadata['USER_NAVIGATION_ENABLED'] = 'true'; # /true|false/
$metadata['USER_NAVIGATION_LAUNCH_URL'] = '@APP_LAUNCH_URL';
$metadata['USER_NAVIGATION_LINK_TEXT'] = '@ACCOUNT_NAVIGATION_LINK_TEXT';

$smarty->addMessage(
	'App metadata updated',
	'Application metadata has been updated to create config.xml',
	NotificationMessage::GOOD
);

?>