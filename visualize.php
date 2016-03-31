<?php

require_once __DIR__ . '/vendor/autoload.php';

use smtech\StMarksSmarty\StMarksSmarty;
use Battis\ConfigXML;
use Battis\HierarchicalSimpleCache;

$config = new ConfigXML(__DIR__ . '/secrets.xml');

$sql = $config->newInstanceOf(mysqli::class, 'mysql');

$cache = new HierarchicalSimpleCache($sql, basename(__FILE__, '.php'));

$smarty = StMarksSmarty::getSmarty();
$smarty->addTemplateDir(__DIR__ . '/templates');

$ics = $cache->getCache($_REQUEST['url']);
if (empty($ics)) {
	$ics = new vcalendar(
		array(
			'unique_id' => basename(__FILE__, '.php'),
			'url' => $_REQUEST['url']
		)
	);
	$ics->parse();
	$cache->setCache($_REQUEST['url'], $ics);
}

$smarty->assign('ics', $ics);
$smarty->assign('veventProperties', array('unique' => array('CLASS','CREATED','SUMMARY','DESCRIPTION','DTSTART','X-CURRENT-DTSTART','DTEND','X-CURRENT-DTEND','DURATION','GEO','LAST-MOD','LOCATION','ORGANIZER','PRIORITY','DTSTAMP','SEQ','STATUS','TRANSP','UID','URL','RECURID'),'multiple'=>array('ATTACH','ATTENDEE','CATEGORIES','COMMENT','CONTACT','EXDATE','EXRULE','RSTATUS','RELATED','RESOURCES','RDATE','RRULE','X-PROP')));
$smarty->display('visualize.tpl');