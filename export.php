<?php

require_once 'common.inc.php';

if (isset($_REQUEST['course_url'])) {
    $courseId = preg_replace('|.*/courses/(\d+)/?.*|', '$1', parse_url($_REQUEST['course_url'], PHP_URL_PATH));
    $course = $api->get("/courses/$courseId");
    if ($course) {
        $webcalFeed = str_replace('https://', 'webcal://', $course['calendar']['ics']);
        $smarty->assign([
            'content' => '<h3>Course Calendar ICS Feed</h3>
                <p>You can subscribe to the calendar for <a href="https://' .
                $_SESSION[CANVAS_INSTANCE_URL] . '/courses/' . $courseId .
                '">' . $course['name'] . '</a> at <a href="' .
                $webcalFeed . '">' . $webcalFeed .
                '</a> in any calendar application that supports external ICS feeds.</p>'
            ]);
    } else {
        $messages[] = array(
            'class' => 'error',
            'title' => 'Canvas API Error',
            'content' => 'The course you requested could not be accessed.<pre>' . print_r($json, false) . '</pre>'
        );
    }
} else {
    $smarty->assign('content', '
    <form method="post" action="' . $_SERVER['PHP_SELF'] . '">
        <label for="course_url">Course URL <span class="comment">The URL to the course whose calendar you would like to export as an ICS feed</span></label>
        <input id="course_url" name="course_url" type="text" />
        <input type="submit" value="Generate ICS Feed" />
    </form>
    ');
}

$smarty->display('page.tpl');
