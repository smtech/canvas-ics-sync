<?php

require_once('vendor/autoload.php');

define('SECRETS_FILE', __DIR__ . '/secrets.xml');
define('SCHEMA_FILE', __DIR__ . '/admin/schema-app.sql');
define('MYSQL_PREFIX', '');

session_start();

/**
 * Initialize a SimpleXMLElement from the SECRETS_FILE
 *
 * @return SimpleXMLElement
 *
 * @throws CanvasAPIviaLTI_Exception MISSING_SECRETS_FILE if the SECRETS_FILE cannot be found
 * @throws CanvasAPIviaLTI_Exception INVALID_SECRETS_FILE if the SECRETS_FILE exists, but cannot be parsed
 **/
function initSecrets() {
	if (file_exists(SECRETS_FILE)) {
		// http://stackoverflow.com/a/24760909 (oy!)
		if (($secrets = simplexml_load_string(file_get_contents(SECRETS_FILE))) !== false) {
			return $secrets;
		} else {
			throw new CanvasAPIviaLTI_Exception(
				SECRETS_FILE . ' could not be loaded. ',
				CanvasAPIviaLTI_Exception::INVALID_SECRETS_FILE
			);
		}
	} else {
		throw new CanvasAPIviaLTI_Exception(
			SECRETS_FILE . " could not be found.",
			CanvasAPIviaLTI_Exception::MISSING_SECRETS_FILE
		);
	}
}

/**
 * Initialize a mysqli connector using the credentials stored in SECRETS_FILE
 *
 * @uses initSecrets() If $secrets is not already initialized
 *
 * @return mysqli A valid mysqli connector to the database backing the CanvasAPIviaLTI instance
 *
 * @throws CanvasAPIviaLTI_Exception MYSQL_CONNECTION if a mysqli connection cannot be established
 **/
function initMySql() {
	global $secrets;	
	if (!($secrets instanceof SimpleXMLElement)) {
		$secrets = initSecrets();
	}
	
	/* turn off warnings, since we're going to test the connection ourselves */
	set_error_handler(function() {});
	$sql = new mysqli(
		(string) $secrets->mysql->host,
		(string) $secrets->mysql->username,
		(string) $secrets->mysql->password,
		(string) $secrets->mysql->database
	);
	restore_error_handler();
	
	if ($sql->connect_error) {
		throw new CanvasAPIviaLTI_Exception(
			$sql->connect_error,
			CanvasAPIviaLTI_Exception::MYSQL_CONNECTION
		);
	}
	return $sql;
}

function initAppMetadata() {
	global $secrets;
	global $sql;
	
	$metadata = new AppMetadata($sql, (string) $secrets->app->id);
	
	return $metadata;
}

/*****************************************************************************
 *                                                                           *
 * The script begins here                                                    *
 *                                                                           *
 *****************************************************************************/
 
/* assume everything's going to be fine... */
$ready = true;

/* fire up the templating engine */
$smarty = new CustomSmarty();

try {

	/* initialize global variables */
	$secrets = initSecrets();
	$sql = initMySql();
	$metadata = initAppMetadata();
	$smarty->assign('metadata', $metadata);

	/* set up a Tool Provider (TP) object to process the LTI request */
	$toolProvider = new CanvasAPIviaLTI(LTI_Data_Connector::getDataConnector($sql));
	$toolProvider->setParameterConstraint('oauth_consumer_key', TRUE, 50);
	$toolProvider->setParameterConstraint('resource_link_id', TRUE, 50, array('basic-lti-launch-request'));
	$toolProvider->setParameterConstraint('user_id', TRUE, 50, array('basic-lti-launch-request'));
	$toolProvider->setParameterConstraint('roles', TRUE, NULL, array('basic-lti-launch-request'));

} catch (CanvasAPIviaLTI_Exception $e) {
	$ready = false;
}

require_once('common-app.inc.php');

?>