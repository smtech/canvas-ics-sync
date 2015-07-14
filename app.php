<?php

require_once('common.inc.php');

$api = new CanvasPest($_SESSION['apiEndpoint'], $_SESSION['apiToken']);

/* replace the contents of this file with your own app logic */

?>
<html>
	<body>
		<h1>App</h1>
		
		<h2><?= $_REQUEST['lti-request'] ?> Request</h3>
		
		<?php if (isset($_REQUEST['reason'])): ?>
		<p><?= $_REQUEST['reason'] ?></p>
		<?php endif; ?>

		<h2>GET /users/self/profile</h3>		
		<pre><?= print_r($api->get('/users/self/profile')) ?></pre>
	</body>
</html>