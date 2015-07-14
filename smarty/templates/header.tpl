<!DOCTYPE html>
<html>

<head>
	<title>Canvas &#x21C4; ICS Sync</title>
	<link rel="stylesheet" href="{$metadata['APP_URL']}/stylesheet.css" />
</head>

<body>

<header id="header">
	<div id="header-logo"></div>
	<ul id="navigation-menu">
		<li><a href="import.php">Import</a></li>
		<li><a href="export.php">Export</a></li>
	</ul>
</header>
{if count($messages) gt 0}<div id="messages">
	<ul>{foreach $messages as $message}
		<li class="{$message['class']|default:"message"}">
			<span class="title">{$message['title']}</span><br />
			<span class="content">{$message['content']}</span>
		</li>
	{/foreach}</ul>
</div>{/if}