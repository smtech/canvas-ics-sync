<?php
	
require_once __DIR__ . '/../vendor/autoload.php';

$ics = __DIR__ . '/' . $argv[1];
if (file_exists($ics)) {
	echo "$ics exists\n";

	$ical = new vcalendar(
		array(
			'unique_id' => __FILE__
		)
	);
	echo "loaded into " . (gettype($ical) == 'object' ? get_class($ical) : gettype($ical)) . "\n";

	$ical->parse(file_get_contents($ics));
	echo "parsed " . count($ical->components, true) . " components\n";

	$components = $ical->selectComponents( 2016, 3, 30, 2016, 4, 1, 'vevent', false, true, true);	
	if ($components) {
		echo "selected " . count($components) . " components\n";

		$output = __DIR__ . '/' . basename($ics, '.ics') . '.html';
		file_put_contents($output, "<html><body><dl>");
		echo "beginning output to $output\n";
		
		foreach ($components as $year => $months) {
			foreach ($months as $month => $days) {
				foreach ($days as $day => $events) {
					foreach ($events as $i => $component) {
						file_put_contents($output, "<dt>selection[$year][$month][$day][$i]</dt><dd><pre>" . print_r($component, true) . "</pre></dd>", FILE_APPEND);
						echo " .";
					}
				}
			}
		}
		file_put_contents($output, "</dl></body></html", FILE_APPEND);
		echo "\nfinished output to output\n";
		
		shell_exec("open $output");
	} else {
		echo "no components selected\n";
	}
} else {
	echo "$ics does not exist\n";
}
