<?php

require_once '../../config.php';
require_once 'lib.php';

$PAGE->set_url($CFG->wwwroot.$SCRIPT);

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // webinar ID
$location = optional_param('location', '', PARAM_TEXT); // location
$download = optional_param('download', '', PARAM_ALPHA); // download attendance

if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'webinar');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'webinar');
    }
    if (!$webinar = $DB->get_record('webinar', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'webinar');
    }
}
elseif ($f) {
    if (!$webinar = $DB->get_record('webinar', array('id' => $f))) {
        print_error('error:incorrectwebinarid', 'webinar');
    }
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        print_error('error:coursemisconfigured', 'webinar');
    }
    if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'webinar');
    }
}
else {
    print_error('error:mustspecifycoursemodulewebinar', 'webinar');
}

//$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$context = context_module::instance($cm->id);

if (!empty($download)) {
    require_capability('mod/webinar:viewattendees', $context);
    webinar_download_attendance($webinar->name, $webinar->id, $location, $download);
    exit();
}

require_course_login($course);
require_capability('mod/webinar:view', $context);

add_to_log($course->id, 'webinar', 'view', "view.php?id=$cm->id", $webinar->id, $cm->id);

$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->navbar->add($webinar->name);
$output = $PAGE->get_renderer('mod_webinar');

echo $OUTPUT->header();


if (empty($cm->visible) and !has_capability('mod/webinar:viewemptyactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}

$OUTPUT->box_start();

echo $output->view_page($course, $webinar, $cm, $context, $location);

$OUTPUT->box_end();

echo $OUTPUT->footer();
