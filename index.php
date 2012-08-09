<?php

require_once '../../config.php';
require_once 'lib.php';

$id = required_param('id', PARAM_INT); // Course Module ID

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('error:coursemisconfigured', 'webinar');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('mod/webinar:view', $context);

add_to_log($course->id, 'webinar', 'view all', "index.php?id=$course->id");

$strwebinars = get_string('modulenameplural', 'webinar');
$strwebinar = get_string('modulename', 'webinar');
$strwebinarname = get_string('webinarname', 'webinar');
$strweek = get_string('week');
$strtopic = get_string('topic');
$strcourse = get_string('course');
$strname = get_string('name');

$pagetitle = format_string($strwebinars);
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'title');
$navigation = build_navigation($navlinks);

$PAGE->set_title($strwebinars);
$PAGE->set_url("/mod/webinar/index.php?id=$course->id");
    $PAGE->set_heading($course->fullname);
    //$PAGE->navbar->add($strwebinars);
    echo $OUTPUT->header();

if (!$webinars = get_all_instances_in_course('webinar', $course)) {
    notice(get_string('nowebinars', 'webinar'), "../../course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();

if ($course->format == 'weeks' && has_capability('mod/webinar:viewattendees', $context)) {
    $table->head  = array ($strweek, $strwebinarname, get_string('sign-ups', 'webinar'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strwebinarname);
    $table->align = array ('center', 'left', 'center', 'center');
}
elseif ($course->format == 'topics' && has_capability('mod/webinar:viewattendees', $context)) {
    $table->head  = array ($strcourse, $strwebinarname, get_string('sign-ups', 'webinar'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'topics') {
    $table->head  = array ($strcourse, $strwebinarname);
    $table->align = array ('center', 'left', 'center', 'center');
}
else {
    $table->head  = array ($strwebinarname);
    $table->align = array ('left', 'left');
}

$currentsection = '';

foreach ($webinars as $webinar) {

    $submitted = get_string('no');

    if (!$webinar->visible) {
        //Show dimmed if the mod is hidden
        $link = "<a class=\"dimmed\" href=\"view.php?f=$webinar->id\">$webinar->name</a>";
    }
    else {
        //Show normal if the mod is visible
        $link = "<a href=\"view.php?f=$webinar->id\">$webinar->name</a>";
    }

    $printsection = '';
    if ($webinar->section !== $currentsection) {
        if ($webinar->section) {
            $printsection = $webinar->section;
        }
        $currentsection = $webinar->section;
    }

    $totalsignupcount = 0;
    if ($sessions = webinar_get_sessions($webinar->id)) {
        foreach($sessions as $session) {
            if (!webinar_has_session_started($session, $timenow)) {
                $signupcount = webinar_get_num_attendees($session->id);
                $totalsignupcount += $signupcount;
            }
        }
    }
        
    $courselink = '<a title="'.$course->shortname.'" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a>';
    if ($course->format == 'weeks' or $course->format == 'topics') {
        if (has_capability('mod/webinar:viewattendees', $context)) {
            $table->data[] = array ($courselink, $link, $totalsignupcount);
        }
        else {
            $table->data[] = array ($courselink, $link);
        }
    }
    else {
        $table->data[] = array ($link, $submitted);
    }
}

echo "<br />";

echo html_writer::table($table);
//print_table($table);

echo $OUTPUT->footer($course);
