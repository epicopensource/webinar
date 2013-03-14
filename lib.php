<?php

require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/lib/adminlib.php';

/**
 * Definitions for setting notification types
 */
/**
 * Utility definitions
 */
define('WEBINAR_ICAL',			1);
define('WEBINAR_TEXT',			2);
define('WEBINAR_BOTH',          3);
define('WEBINAR_INVITE',		4);
define('WEBINAR_CANCEL',		8);

/**
 * Definitions for use in forms
 */
define('WEBINAR_INVITE_BOTH',		7);	    // Send a copy of both 4+1+2
define('WEBINAR_INVITE_TEXT',		6);	    // Send just a plain email 4+2
define('WEBINAR_INVITE_ICAL',		5);	    // Send just a combined text/ical message 4+1
define('WEBINAR_CANCEL_BOTH',		11);	// Send a copy of both 8+2+1
define('WEBINAR_CANCEL_TEXT',		10);	// Send just a plan email 8+2
define('WEBINAR_CANCEL_ICAL',		9);	    // Send just a combined text/ical message 8+1

// Name of the custom field where the manager's email address is stored
define('WEBINAR_MANAGERSEMAIL_FIELD', 'managersemail');

// Custom field related constants
define('WEBINAR_CUSTOMFIELD_DELIMITTER', ';');
define('WEBINAR_CUSTOMFIELD_TYPE_TEXT',        0);
define('WEBINAR_CUSTOMFIELD_TYPE_SELECT',      1);
define('WEBINAR_CUSTOMFIELD_TYPE_MULTISELECT', 2);

// Calendar-related constants
define('WEBINAR_CALENDAR_MAX_NAME_LENGTH', 15);

// Signup status codes (remember to update $WEBINAR_STATUS)
define('WEBINAR_STATUS_USER_CANCELLED',     10);
define('WEBINAR_STATUS_SESSION_CANCELLED',  20);
define('WEBINAR_STATUS_DECLINED',           30);
define('WEBINAR_STATUS_REQUESTED',          40);
define('WEBINAR_STATUS_APPROVED',           50);
define('WEBINAR_STATUS_WAITLISTED',         60);
define('WEBINAR_STATUS_BOOKED',             70);
define('WEBINAR_STATUS_NO_SHOW',            80);
define('WEBINAR_STATUS_PARTIALLY_ATTENDED', 90);
define('WEBINAR_STATUS_FULLY_ATTENDED',     100);

// This array must match the status codes above, and the values
// must equal the end of the constant name but in lower case
$WEBINAR_STATUS = array(
    WEBINAR_STATUS_USER_CANCELLED       => 'user_cancelled',
    WEBINAR_STATUS_SESSION_CANCELLED    => 'session_cancelled',
    WEBINAR_STATUS_DECLINED             => 'declined',
    WEBINAR_STATUS_REQUESTED            => 'requested',
    WEBINAR_STATUS_APPROVED             => 'approved',
    WEBINAR_STATUS_WAITLISTED           => 'waitlisted',
    WEBINAR_STATUS_BOOKED               => 'booked',
    WEBINAR_STATUS_NO_SHOW              => 'no_show',
    WEBINAR_STATUS_PARTIALLY_ATTENDED   => 'partially_attended',
    WEBINAR_STATUS_FULLY_ATTENDED       => 'fully_attended',
);

/**
 * Returns the human readable code for a webinar status
 *
 * @param int $statuscode One of the WEBINAR_STATUS* constants
 * @return string Human readable code
 */
function webinar_get_status($statuscode) {
    global $WEBINAR_STATUS;
    // Check code exists
    if (!isset($WEBINAR_STATUS[$statuscode])) {
        error('WEBINAR status code does not exist: '.$statuscode);
    }

    // Get code
    $string = $WEBINAR_STATUS[$statuscode];

    // Check to make sure the status array looks to be up-to-date
    if (constant('WEBINAR_STATUS_'.strtoupper($string)) != $statuscode) {
        error('WEBINAR status code array does not appear to be up-to-date: '.$statuscode);
    }

    return $string;
}

/**
 * Prints the cost amount along with the appropriate currency symbol.
 *
 * To set your currency symbol, set the appropriate 'locale' in
 * lang/en_utf8/langconfig.php (or the equivalent file for your
 * language).
 *
 * @param $amount      Numerical amount without currency symbol
 * @param $htmloutput  Whether the output is in HTML or not
 */
function webinar_format_cost($amount, $htmloutput=true) {
    setlocale(LC_MONETARY, get_string('locale'));
    $localeinfo = localeconv();

    $symbol = $localeinfo['currency_symbol'];
    if (empty($symbol)) {
        // Cannot get the locale information, default to en_US.UTF-8
        return '&pound;' . $amount;
    }

    // Character between the currency symbol and the amount
    $separator = '';
    if ($localeinfo['p_sep_by_space']) {
        $separator = $htmloutput ? '&nbsp;' : ' ';
    }

    // The symbol can come before or after the amount
    if ($localeinfo['p_cs_precedes']) {
        return $symbol . $separator . $amount;
    }
    else {
        return $amount . $separator . $symbol;
    }
}

/**
 * Returns the effective cost of a session depending on the presence
 * or absence of a discount code.
 *
 * @param class $sessiondata contains the discountcost and normalcost
 */
function webinar_cost($userid, $sessionid, $sessiondata, $htmloutput=true) {

    global $CFG, $DB;

    if ($DB->count_records_sql("SELECT COUNT(*)
                               FROM {$CFG->prefix}webinar_signups su,
                                    {$CFG->prefix}webinar_sessions se
                              WHERE su.sessionid=$sessionid
                                AND su.userid=$userid
                                AND su.sessionid = se.id") > 0) {
        return webinar_format_cost($sessiondata->discountcost, $htmloutput);
    } else {
        return webinar_format_cost($sessiondata->normalcost, $htmloutput);
    }
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param integer $duration duration in hours
 */
function webinar_format_duration($duration) {

    $components = explode(':', $duration);

    if ($components and count($components) > 1) {
        // e.g. "1:30" => "1 hour and 30 minutes"
        $hours = $components[0];
        $minutes = $components[1];
    }
    else {
        // e.g. "1.5" => "1 hour and 30 minutes"
        $minutes = round($duration - floor($duration) * 60);
        $hours = floor($duration);
    }

    $string = '';

    if (1 == $hours) {
        $string = get_string('onehour', 'webinar');
    } elseif ($hours > 1) {
        $string = get_string('xhours', 'webinar', $hours);
    }

    // Insert separator between hours and minutes
    if ($string != '') {
        $string .= ' ';
    }

    if (1 == $minutes) {
        $string .= get_string('oneminute', 'webinar');
    } elseif ($minutes > 0) {
        $string .= get_string('xminutes', 'webinar', $minutes);
    }

    return $string;
}

/**
 * Converts minutes to hours
 */
function webinar_minutes_to_hours($minutes) {

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    }
    else {
        return $minutes;
    }
}

/**
 * Converts hours to minutes
 */
function webinar_hours_to_minutes($hours)
{
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {
        // e.g. "1:45" => 105 minutes
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    }
    else {
        // e.g. "1.75" => 105 minutes
        return round($hours * 60.0);
    }
}

/**
 * Turn undefined manager messages into empty strings and deal with checkboxes
 */
function webinar_fix_settings($webinar) {

    if (empty($webinar->emailmanagerconfirmation)) {
        $webinar->confirmationinstrmngr = null;
    }
    if (empty($webinar->emailmanagerreminder)) {
        $webinar->reminderinstrmngr = null;
    }
    if (empty($webinar->emailmanagercancellation)) {
        $webinar->cancellationinstrmngr = null;
    }
    if (empty($webinar->thirdpartywaitlist)) {
        $webinar->thirdpartywaitlist = 0;
    }
    if (empty($webinar->showoncalendar)) {
        $webinar->showoncalendar = 0;
    }
    if (empty($webinar->approvalreqd)) {
        $webinar->approvalreqd = 0;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will create a new instance and
 * return the id number of the new instance.
 */
function webinar_add_instance($webinar) {
	global $DB;
	
    $webinar->timemodified = time();

    webinar_fix_settings($webinar);
    if ($webinar->id = $DB->insert_record('webinar', $webinar)) {
        webinar_grade_item_update($webinar);
    }
    return $webinar->id;
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will update an existing instance
 * with new data.
 */
function webinar_update_instance($webinar) {
	global $DB;
	
    $webinar->id = $webinar->instance;

    webinar_fix_settings($webinar);
    if ($return = $DB->update_record('webinar', $webinar)) {
        webinar_grade_item_update($webinar);
    }
    return $return;
}

/**
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 */
function webinar_delete_instance($id) {

    global $CFG, $DB;

    if (!$webinar = $DB->get_record('webinar', array('id' => $id))) {
        return false;
    }

    $result = true;
   // //begin_sql();

    if (!$DB->delete_records_select(
        'webinar_signups_status',
        "signupid IN
        (
            SELECT
                id
            FROM
                {$CFG->prefix}webinar_signups
            WHERE
                sessionid IN
                (
                    SELECT
                        id
                    FROM
                        {$CFG->prefix}webinar_sessions
                    WHERE
                        webinar = {$webinar->id}
                )
        )
        ")) {
        $result = false;
    }

    if (!$DB->delete_records_select('webinar_signups', "sessionid IN (SELECT id FROM {$CFG->prefix}webinar_sessions WHERE webinar = {$webinar->id})")) {
        $result = false;
    }

    if (!$DB->delete_records_select('webinar_sessions_dates', "sessionid in (SELECT id FROM {$CFG->prefix}webinar_sessions WHERE webinar = $webinar->id)")) {
        $result = false;
    }

    if (!$DB->delete_records('webinar_sessions', array('webinar' => $webinar->id))) {
        $result = false;
    }

    if (!$DB->delete_records('webinar', array('id' => $webinar->id))) {
        $result = false;
    }

    if (!$DB->delete_records('event', array('modulename' => 'webinar', 'instance' => $webinar->id))) {
        $result = false;
    }

    if (!webinar_grade_item_delete($webinar)) {
        $result = false;
    }

    if ($result) {
        //commit_sql();
    } else {
        //rollback_sql();
    }

    return $result;
}

/**
 * Prepare the user data to go into the database.
 */
function webinar_cleanup_session_data($session) {

    // Only numbers allowed here
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $MAX_CAPACITY = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    }
    elseif ($session->capacity > $MAX_CAPACITY) {
        $session->capacity = $MAX_CAPACITY;
    }

    // Get the decimal point separator
    //setlocale(LC_MONETARY, get_string('locale'));
    $localeinfo = localeconv();
    $symbol = $localeinfo['decimal_point'];
    if (empty($symbol)) {
        // Cannot get the locale information, default to en_US.UTF-8
        $symbol = '.';
    }

    return $session;
}

/**
 * Create a new entry in the webinar_sessions table
 */
function webinar_add_session($session, $sessiondates)
{
    global $USER, $DB;

    $session->timecreated = time();
    $session = webinar_cleanup_session_data($session);

    $eventname = $DB->get_field('webinar', 'name', array('id' => $session->webinar));

    if ($session->id = $DB->insert_record('webinar_sessions', $session)) {
        if (empty($sessiondates)) {
            // Insert a dummy date record
            $date = new object();
            $date->sessionid = $session->id;
            $date->timestart = 0;
            $date->timefinish = 0;
            if (!$DB->insert_record('webinar_sessions_dates', $date)) {
                //rollback_sql();
                return false;
            }
        }
        else {
            foreach ($sessiondates as $date) {
                $date->sessionid = $session->id;
                if (!$DB->insert_record('webinar_sessions_dates', $date)) {
                    //rollback_sql();
                    return false;
                }
            }
        }

        // Put the sessions in this user's calendar
        // (i.e. we're assuming it's the teacher)
        $session->sessiondates = $sessiondates;
        webinar_add_session_to_user_calendar($session, $eventname, $USER->id, 'session');

        return $session->id;
    } else {
        //rollback_sql();
        return false;
    }
}

/**
 * Modify an entry in the webinar_sessions table
 */
function webinar_update_session($session, $sessiondates) {
	global $DB;
	
    $session->timemodified = time();
    $session = webinar_cleanup_session_data($session);

    if (!$DB->update_record('webinar_sessions', $session)) {
        //rollback_sql();
        return false;
    }

    if (!$DB->delete_records('webinar_sessions_dates', array('sessionid' => $session->id))) {
        //rollback_sql();
        return false;
    }

    if (empty($sessiondates)) {
        // Insert a dummy date record
        $date = new object();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        if (!$DB->insert_record('webinar_sessions_dates', $date)) {
            //rollback_sql();
            return false;
        }
    }
    else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            if (!$DB->insert_record('webinar_sessions_dates', $date)) {
                //rollback_sql();
                return false;
            }
        }
    }

    // Update Calendar entries for students and teachers
    $session->sessiondates = $sessiondates;
    if (!webinar_update_calendar_events($session, 'booking')) {
        //rollback_sql();
        return false;
    }
    if (!webinar_update_calendar_events($session, 'session')) {
        //rollback_sql();
        return false;
    }

    return webinar_update_attendees($session);
}

/**
 * Update attendee list status' on booking size change
 */
function webinar_update_attendees($session) {
    global $USER, $DB;

    // Get webinar
    if (!$webinar = $DB->get_record('webinar', array('id' => $session->webinar))) {
        error('Could not load webinar record');
    }

    // Get course
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        error('Could not load course record');
    }

    // Update user status'
    $users = webinar_get_attendees($session->id);

    if ($users) {

            // Convert earliest signed up users to booked, and make the rest waitlisted
            $capacity = $session->capacity;

            // Count number of booked users
            $booked = 0;
            foreach ($users as $user) {
                if ($user->statuscode == WEBINAR_STATUS_BOOKED) {
                    $booked++;
                }
            }

            // If booked less than capacity, book some new users
            if ($booked < $capacity) {
                foreach ($users as $user) {
                    if ($booked >= $capacity) {
                        break;
                    }

                    if ($user->statuscode == WEBINAR_STATUS_WAITLISTED) {

                        if (!webinar_user_signup($session, $webinar, $course, '', 0, WEBINAR_STATUS_BOOKED, $user->id)) {
                            //rollback_sql();
                            return false;
                        }
                        $booked++;
                    }
                }
            }
        
    }

    return $session->id;
}

/**
 * Return an array of all webinar activities in the current course
 */
function webinar_get_webinar_menu() {

    global $CFG;
	if ($webinars = $DB->get_records_sql("SELECT f.id, c.shortname, f.name
                                            FROM {$CFG->prefix}course c, {$CFG->prefix}webinar f
                                            WHERE c.id = f.course
                                            ORDER BY c.shortname, f.name")) {
        $i=1;
        foreach ($webinars as $webinar) {
            $f = $webinar->id;
            $webinarmenu[$f] = $webinar->shortname.' --- '.$webinar->name;
            $i++;
        }

        return $webinarmenu;

    } else {

        return '';

    }
}

/**
 * Delete entry from the webinar_sessions table along with all
 * related details in other tables
 *
 * @param object $session Record from webinar_sessions
 */
function webinar_delete_session($session)
{
    global $CFG, $DB;

    $webinar = $DB->get_record('webinar', array('id' => $session->webinar));

    // Cancel user signups (and notify users)
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {$CFG->prefix}webinar_signups s
            LEFT JOIN
                {$CFG->prefix}webinar_signups_status ss
             ON ss.signupid = s.id
            WHERE
                s.sessionid = $session->id
            AND ss.superceded = 0
            AND ss.statuscode >= ".WEBINAR_STATUS_REQUESTED."
        "
    );

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (webinar_user_cancel($session, $user->userid, true)) {
                webinar_send_cancellation_notice($webinar, $session, $user->userid);
            }
            else {
                return false; // Cannot rollback since we notified users already
            }
        }
    }

    //begin_sql();

    // Remove entries from the teacher calendars
    if (!$DB->delete_records_select('event', "modulename = 'webinar' AND eventtype = 'webinarsession' AND instance = $webinar->id")) {
        //rollback_sql();
        return false;
    }

    // Remove entry from site-wide calendar
    webinar_remove_session_from_site_calendar($session);

    // Remove entry from site-wide calendar
    webinar_remove_session_from_site_calendar($session);

    // Delete session details
    if (!$DB->delete_records('webinar_sessions', array('id' => $session->id))) {
        //rollback_sql();
        return false;
    }
    if (!$DB->delete_records('webinar_sessions_dates', array('sessionid' => $session->id))) {
        //rollback_sql();
        return false;
    }

    if (!$DB->delete_records_select(
        'webinar_signups_status',
        "signupid IN
        (
            SELECT
                id
            FROM
                {$CFG->prefix}webinar_signups
            WHERE
                sessionid = {$session->id}
        )
        ")) {
        $result = false;
    }

    if (!$DB->delete_records('webinar_signups', array('sessionid' => $session->id))) {
        //rollback_sql();
        return false;
    }

    //commit_sql();
    return true;
}

/**
 * Subsitute the placeholders in email templates for the actual data
 */
function webinar_email_substitutions($msg, $webinarname, $reminderperiod, $user, $session, $sessionid)
{
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }


        // Scheduled session
        $sessiondate = userdate($session->sessiondates->timestart, get_string('strftimedate'));
        $starttime = userdate($session->sessiondates[0]->timestart, get_string('strftimetime'));
        $finishtime = userdate($session->sessiondates[0]->timefinish, get_string('strftimetime'));
		
		$alldates = '';
        foreach ($session->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $alldates .= userdate($date->timestart, get_string('strftimedate')).', ';
            $alldates .= userdate($date->timestart, get_string('strftimetime')).
                ' to '.userdate($date->timefinish, get_string('strftimetime'));
        }


    $msg = str_replace(get_string('placeholder:webinarname', 'webinar'), $webinarname,$msg);
    $msg = str_replace(get_string('placeholder:firstname', 'webinar'), $user->firstname,$msg);
    $msg = str_replace(get_string('placeholder:lastname', 'webinar'), $user->lastname,$msg);
    $msg = str_replace(get_string('placeholder:cost', 'webinar'), webinar_cost($user->id, $sessionid, $session, false),$msg);
    $msg = str_replace(get_string('placeholder:alldates', 'webinar'), $alldates,$msg);
    /*$msg = str_replace(get_string('placeholder:sessiondate', 'webinar'), $sessiondate,$msg);
    $msg = str_replace(get_string('placeholder:starttime', 'webinar'), $starttime,$msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'webinar'), $finishtime,$msg);*/
    $msg = str_replace(get_string('placeholder:duration', 'webinar'), webinar_format_duration($session->duration),$msg);
    if (empty($session->details)) {
        $msg = str_replace(get_string('placeholder:details', 'webinar'), '',$msg);
    }
    else {
        $msg = str_replace(get_string('placeholder:details', 'webinar'), html_to_text($session->details),$msg);
    }
    $msg = str_replace(get_string('placeholder:reminderperiod', 'webinar'), $reminderperiod, $msg);

    // Replace more meta data
    $msg = str_replace(get_string('placeholder:attendeeslink', 'webinar'), $CFG->wwwroot.'/mod/webinar/attendees.php?s='.$session->id, $msg);

    // Custom session fields (they look like "session:shortname" in the templates)
    $customfields = webinar_get_session_customfields();
    $customdata = $DB->get_records('webinar_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $placeholder = "[session:{$field->shortname}]";
        $data = '';
        if (!empty($customdata[$field->id])) {
            $data = $customdata[$field->id]->data;
        }

        $msg = str_replace($placeholder, $data, $msg);
    }
	
	$msg = str_replace('&pound;','£',$msg);
	
    return $msg;
}

/**
 * Function to be run periodically according to the moodle cron
 */
function webinar_cron()
{
    return true;
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param class $session record from the webinar_sessions table
 * @param integer $timenow current time
 */
function webinar_has_session_started($session, $timenow) {

    foreach ($session->sessiondates as $date) {
        if ($date->timestart < $timenow) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param class $session record from the webinar_sessions table
 * @param integer $timenow current time
 */
function webinar_is_session_in_progress($session, $timenow) {

    foreach ($session->sessiondates as $date) {
        if ($date->timefinish > $timenow && $date->timestart < $timenow) {
            return true;
        }
    }
    return false;
}

/**
 * Get all of the dates for a given session
 */
function webinar_get_session_dates($sessionid) {
	global $DB;
	
    $ret = array();

    if ($dates = $DB->get_records('webinar_sessions_dates', array('sessionid' => $sessionid), 'timestart')) {
        $i = 0;
        foreach ($dates as $date) {
            $ret[$i++] = $date;
        }
    }

    return $ret;
}

/**
 * Get a record from the webinar_sessions table
 *
 * @param integer $sessionid ID of the session
 */
function webinar_get_session($sessionid) {
	global $DB;
	
    $session = $DB->get_record('webinar_sessions', array('id' => $sessionid));

    if ($session) {
        $session->sessiondates = webinar_get_session_dates($sessionid);
    }

    return $session;
}

/**
 * Get all records from webinar_sessions for a given webinar activity and location
 *
 * @param integer $webinarid ID of the activity
 * @param string $location location filter (optional)
 */
function webinar_get_sessions($webinarid, $location='')
{
    global $CFG, $DB;

    $fromclause = "FROM {$CFG->prefix}webinar_sessions s";
    $locationwhere = '';
    if (!empty($location)) {
        $fromclause = "FROM {$CFG->prefix}webinar_session_data d
                       JOIN {$CFG->prefix}webinar_sessions s ON s.id = d.sessionid";
        $locationwhere = " AND d.data = '$location'";
    }

    $sessions = $DB->get_records_sql("SELECT s.*
                                   $fromclause
                        LEFT OUTER JOIN (SELECT sessionid, min(timestart) AS mintimestart
                                           FROM {$CFG->prefix}webinar_sessions_dates GROUP BY sessionid) m ON m.sessionid = s.id
                                  WHERE s.webinar = $webinarid
                                        $locationwhere
                               ORDER BY m.mintimestart");

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            //$sessions[$key]->duration = webinar_minutes_to_hours($sessions[$key]->duration);
            $sessions[$key]->sessiondates = webinar_get_session_dates($value->id);
        }
    }
    return $sessions;
}

/**
 * Get a grade for the given user from the gradebook.
 *
 * @param integer $userid        ID of the user
 * @param integer $courseid      ID of the course
 * @param integer $webinarid  ID of the Webinar activity
 *
 * @returns object String grade and the time that it was graded
 */
function webinar_get_grade($userid, $courseid, $webinarid) {

    $ret = new object;
    $ret->grade = 0;
    $ret->dategraded = 0;

    $grading_info = grade_get_grades($courseid, 'mod', 'webinar', $webinarid, $userid);
    if (!empty($grading_info->items)) {
        $ret->grade = $grading_info->items[0]->grades[$userid]->str_grade;
        $ret->dategraded = $grading_info->items[0]->grades[$userid]->dategraded;
    }

    return $ret;
}

/**
 * Get list of users attending a given session
 */
function webinar_get_attendees($sessionid)
{
    global $CFG, $DB;
    $records = $DB->get_records_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            f.id AS webinarid,
            f.course,
            ss.grade,
            ss.statuscode,
            sign.timecreated
        FROM
            {$CFG->prefix}webinar f
        JOIN
            {$CFG->prefix}webinar_sessions s
         ON s.webinar = f.id
        JOIN
            {$CFG->prefix}webinar_signups su
         ON s.id = su.sessionid
        JOIN
            {$CFG->prefix}webinar_signups_status ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {$CFG->prefix}webinar_signups_status ss
            INNER JOIN
                {$CFG->prefix}webinar_signups s
             ON s.id = ss.signupid
            AND s.sessionid = $sessionid
            WHERE
                ss.statuscode IN (".WEBINAR_STATUS_BOOKED.",".WEBINAR_STATUS_WAITLISTED.")
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {$CFG->prefix}user u
         ON u.id = su.userid
        WHERE
            s.id = $sessionid
        AND ss.superceded != 1
        AND ss.statuscode >= ".WEBINAR_STATUS_APPROVED."
        ORDER BY
            sign.timecreated ASC,
            ss.timecreated ASC
    ");

    return $records;
}

/**
 * Get a single attendee of a session
 */
function webinar_get_attendee($sessionid, $userid)
{
    global $CFG, $DB;
    $record = $DB->get_record_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            s.discountcost,
            f.id AS webinarid,
            f.course,
            ss.grade,
            ss.statuscode
        FROM
            {$CFG->prefix}webinar f
        JOIN
            {$CFG->prefix}webinar_sessions s
         ON s.webinar = f.id
        JOIN
            {$CFG->prefix}webinar_signups su
         ON s.id = su.sessionid
        JOIN
            {$CFG->prefix}webinar_signups_status ss
         ON su.id = ss.signupid
        JOIN
            {$CFG->prefix}user u
         ON u.id = su.userid
        WHERE
            s.id = $sessionid
        AND ss.superceded != 1
        AND u.id = $userid
    ");

    if (!$record) {
        return false;
    }

    return $record;
}
/**
 * Return all user fields to include in exports
 */
function webinar_get_userfields()
{
    global $CFG;

    static $userfields = null;
    if (null == $userfields) {
        $userfields = array();

        if (function_exists('grade_export_user_fields')) {
            $fieldnames = grade_export_user_fields();
            foreach ($fieldnames as $key => $obj) {
                $userfields[$obj->shortname] = $obj->fullname;
            }
        }
        else {
            // Set default fields if the grade export patch is not
            // detected (see MDL-17346)
            $fieldnames = array('firstname', 'lastname', 'email', 'city',
                                'idnumber', 'institution', 'department', 'address');
            foreach ($fieldnames as $shortname) {
                $userfields[$shortname] = get_string($shortname);
            }
            $userfields['managersemail'] = get_string('manageremail', 'webinar');
        }
    }

    return $userfields;
}

/**
 * Download the list of users attending at least one of the sessions
 * for a given webinar activity
 */
function webinar_download_attendance($webinarname, $webinarid, $location, $format) {
    global $CFG;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate'));
    $downloadfilename = clean_filename($webinarname.'_'.userdate($timenow, $timeformat));

    $dateformat = 0;
    if ('ods' === $format) {
        // OpenDocument format (ISO/IEC 26300)
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    }
    else {
        // Excel format
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
        $dateformat =& $workbook->add_format();
        $dateformat->set_num_format('d mmm yy'); // TODO: use format specified in language pack
    }

    $workbook->send($downloadfilename);
    $worksheet =& $workbook->add_worksheet('attendance');

    webinar_write_worksheet_header($worksheet);
    webinar_write_activity_attendance($worksheet, 1, $webinarid, $location, '', '', $dateformat);

    $workbook->close();
    exit;
}

/**
 * Add the appropriate column headers to the given worksheet
 *
 * @param object $worksheet  The worksheet to modify (passed by reference)
 * @returns integer The index of the next column
 */
function webinar_write_worksheet_header(&$worksheet)
{
    $pos=0;
    $customfields = webinar_get_session_customfields();
    foreach ($customfields as $field) {
        if (!empty($field->showinsummary)) {
            $worksheet->write_string(0, $pos++, $field->name);
        }
    }
    $worksheet->write_string(0, $pos++, get_string('date', 'webinar'));
    $worksheet->write_string(0, $pos++, get_string('timestart', 'webinar'));
    $worksheet->write_string(0, $pos++, get_string('timefinish', 'webinar'));
    $worksheet->write_string(0, $pos++, get_string('duration', 'webinar'));
    $worksheet->write_string(0, $pos++, get_string('status', 'webinar'));

    $trainerroles = webinar_get_trainer_roles();
    foreach ($trainerroles as $role) {
        $worksheet->write_string(0, $pos++, get_string('role').': '.$role->name);
    }

    $userfields = webinar_get_userfields();
    foreach ($userfields as $shortname => $fullname) {
        $worksheet->write_string(0, $pos++, $fullname);
    }

    $worksheet->write_string(0, $pos++, get_string('attendance', 'webinar'));
    return $pos;
}

/**
 * Write in the worksheet the given webinar attendance information
 * filtered by location.
 *
 * This function includes lots of custom SQL because it's otherwise
 * way too slow.
 *
 * @param object  $worksheet    Currently open worksheet
 * @param integer $startingrow  Index of the starting row (usually 1)
 * @param integer $webinarid ID of the webinar activity
 * @param string  $location     Location to filter by
 * @param string  $coursename   Name of the course (optional)
 * @param string  $activityname Name of the webinar activity (optional)
 * @param object  $dateformat   Use to write out dates in the spreadsheet
 * @returns integer Index of the last row written
 */
function webinar_write_activity_attendance(&$worksheet, $startingrow, $webinarid, $location,
                                              $coursename, $activityname, $dateformat)
{
    global $CFG, $DB;

    $trainerroles = webinar_get_trainer_roles();
    $userfields = webinar_get_userfields();
    $customsessionfields = webinar_get_session_customfields();
    $timenow = time();
    $i = $startingrow;

    $locationcondition = '';
    if (!empty($location)) {
        $locationcondition = "AND s.location='$location'";
    }

    // Fast version of "webinar_get_attendees()" for all sessions
    $sessionsignups = array();
    $signups = $DB->get_records_sql("
        SELECT
            su.id AS submissionid,
            s.id AS sessionid,
            u.*,
            f.course AS courseid,
            ss.grade,
            sign.timecreated
        FROM
            {$CFG->prefix}webinar f
        JOIN
            {$CFG->prefix}webinar_sessions s
         ON s.webinar = f.id
        JOIN
            {$CFG->prefix}webinar_signups su
         ON s.id = su.sessionid
        JOIN
            {$CFG->prefix}webinar_signups_status ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {$CFG->prefix}webinar_signups_status ss
            INNER JOIN
                {$CFG->prefix}webinar_signups s
             ON s.id = ss.signupid
            INNER JOIN
                {$CFG->prefix}webinar_sessions se
             ON s.sessionid = se.id
            AND se.webinar = $webinarid
            WHERE
                ss.statuscode IN (".WEBINAR_STATUS_BOOKED.",".WEBINAR_STATUS_WAITLISTED.")
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {$CFG->prefix}user u
         ON u.id = su.userid
        WHERE
            f.id = $webinarid
        AND ss.superceded != 1
        AND ss.statuscode >= ".WEBINAR_STATUS_APPROVED."
        ORDER BY
            s.id, u.firstname, u.lastname
    ");

    if ($signups) {
        // Get all grades at once
        $userids = array();
        foreach ($signups as $signup) {
            if ($signup->id > 0) {
                $userids[] = $signup->id;
            }
        }
        $grading_info = grade_get_grades(reset($signups)->courseid, 'mod', 'webinar',
                                         $webinarid, $userids);

        foreach ($signups as $signup) {
            $userid = $signup->id;

            if ($customuserfields = webinar_get_user_customfields($userid, $userfields)) {
                foreach ($customuserfields as $fieldname => $value) {
                    if (!isset($signup->$fieldname)) {
                        $signup->$fieldname = $value;
                    }
                }
            }

            // Set grade
            if (!empty($grading_info->items) and !empty($grading_info->items[0]->grades[$userid])) {
                $signup->grade = $grading_info->items[0]->grades[$userid]->str_grade;
            }

            $sessionsignups[$signup->sessionid][$signup->id] = $signup;
        }
    }

    // Fast version of "webinar_get_sessions($webinarid, $location)"
    $sql = "SELECT s.id, s.capacity, d.timestart, d.timefinish
              FROM {$CFG->prefix}webinar_sessions s
              JOIN {$CFG->prefix}webinar_sessions_dates d ON s.id = d.sessionid
             WHERE s.webinar=$webinarid AND d.sessionid = s.id
                   $locationcondition
          ORDER BY d.timestart";

    if ($sessions = $DB->get_records_sql($sql)) {
        $i = $i - 1; // will be incremented BEFORE each row is written

        foreach ($sessions as $session) {
            $customdata = $DB->get_records('webinar_session_data', 'sessionid', $session->id, '', 'fieldid, data');

            $sessiondate = false;
            $starttime   = get_string('wait-listed', 'webinar');
            $finishtime  = get_string('wait-listed', 'webinar');
            $status      = get_string('wait-listed', 'webinar');

            $sessiontrainers = webinar_get_trainers($session->id);

            
                // Display only the first date
                if (method_exists($worksheet, 'write_date')) {
                    // Needs the patch in MDL-20781
                    $sessiondate = (int)$session->timestart;
                }
                else {
                    $sessiondate = userdate($session->timestart, get_string('strftimedate'));
                }
                $starttime   = userdate($session->timestart, get_string('strftimetime'));
                $finishtime  = userdate($session->timefinish, get_string('strftimetime'));

                if ($session->timestart < $timenow) {
                    $status = get_string('sessionover', 'webinar');
                }
                else {
                    $signupcount = 0;
                    if (!empty($sessionsignups[$session->id])) {
                        $signupcount = count($sessionsignups[$session->id]);
                    }

                    if ($signupcount >= $session->capacity) {
                        $status = get_string('bookingfull', 'webinar');
                    } else {
                        $status = get_string('bookingopen', 'webinar');
                    }
                }
            

            if (!empty($sessionsignups[$session->id])) {
                foreach ($sessionsignups[$session->id] as $attendee) {
                    $i++; $j=0;

                    // Custom session fields
                    foreach ($customsessionfields as $field) {
                        if (empty($field->showinsummary)) {
                            continue; // skip
                        }

                        $data = '-';
                        if (!empty($customdata[$field->id])) {
                            $data = $customdata[$field->id]->data;
                        }
                        $worksheet->write_string($i, $j++, $data);
                    }

                    if (empty($sessiondate)) {
                        $worksheet->write_string($i, $j++, $status); // session date
                    }
                    else {
                        if (method_exists($worksheet, 'write_date')) {
                            $worksheet->write_date($i, $j++, $sessiondate, $dateformat);
                        }
                        else {
                            $worksheet->write_string($i, $j++, $sessiondate);
                        }
                    }
                    $worksheet->write_string($i,$j++,$starttime);
                    $worksheet->write_string($i,$j++,$finishtime);
                    $worksheet->write_number($i,$j++,(int)$session->duration);
                    $worksheet->write_string($i,$j++,$status);

                    foreach (array_keys($trainerroles) as $roleid) {
                        if (!empty($sessiontrainers[$roleid])) {
                            $trainers = array();
                            foreach ($sessiontrainers[$roleid] as $trainer) {
                                $trainers[] = fullname($trainer);
                            }

                            $trainers = implode(', ', $trainers);
                        }
                        else {
                            $trainers = '-';
                        }

                        $worksheet->write_string($i, $j++, $trainers);
                    }

                    foreach ($userfields as $shortname => $fullname) {
                        $value = '-';
                        if (!empty($attendee->$shortname)) {
                            $value = $attendee->$shortname;
                        }

                        if ('firstaccess' == $shortname or 'lastaccess' == $shortname or
                            'lastlogin' == $shortname or 'currentlogin' == $shortname) {

                            if (method_exists($worksheet, 'write_date')) {
                                $worksheet->write_date($i, $j++, (int)$value, $dateformat);
                            }
                            else {
                                $worksheet->write_string($i, $j++, userdate($value, get_string('strftimedate')));
                            }
                        }
                        else {
                            $worksheet->write_string($i,$j++,$value);
                        }
                    }
                    $worksheet->write_string($i,$j++,$attendee->grade);

                    if (method_exists($worksheet,'write_date')) {
                        $worksheet->write_date($i, $j++, (int)$attendee->timecreated, $dateformat);
                    } else {
                        $signupdate = userdate($attendee->timecreated, get_string('strftimedatetime'));
                        if (empty($signupdate)){
                            $signupdate = '-';
                        }
                        $worksheet->write_string($i,$j++, $signupdate);
                    }

                    if (!empty($coursename)) {
                        $worksheet->write_string($i, $j++, $coursename);
                    }
                    if (!empty($activityname)) {
                        $worksheet->write_string($i, $j++, $activityname);
                    }
                }
            }
            else {
                // no one is sign-up, so let's just print the basic info
                $i++; $j=0;

                // Custom session fields
                foreach ($customsessionfields as $field) {
                    if (empty($field->showinsummary)) {
                        continue; // skip
                    }

                    $data = '-';
                    if (!empty($customdata[$field->id])) {
                        $data = $customdata[$field->id]->data;
                    }
                    $worksheet->write_string($i, $j++, $data);
                }

                if (empty($sessiondate)) {
                    $worksheet->write_string($i, $j++, $status); // session date
                }
                else {
                    if (method_exists($worksheet, 'write_date')) {
                        $worksheet->write_date($i, $j++, $sessiondate, $dateformat);
                    }
                    else {
                        $worksheet->write_string($i, $j++, $sessiondate);
                    }
                }
                $worksheet->write_string($i,$j++,$starttime);
                $worksheet->write_string($i,$j++,$finishtime);
                $worksheet->write_number($i,$j++,(int)$session->duration);
                $worksheet->write_string($i,$j++,$status);
                foreach ($userfields as $unused) {
                    $worksheet->write_string($i,$j++,'-');
                }
                $worksheet->write_string($i,$j++,'-');

                if (!empty($coursename)) {
                    $worksheet->write_string($i, $j++, $coursename);
                }
                if (!empty($activityname)) {
                    $worksheet->write_string($i, $j++, $activityname);
                }
            }
        }
    }

    return $i;
}

/**
 * Return an object with all values for a user's custom fields.
 *
 * This is about 15 times faster than the custom field API.
 *
 * @param array $fieldstoinclude Limit the fields returned/cached to these ones (optional)
 */
function webinar_get_user_customfields($userid, $fieldstoinclude=false)
{
    global $CFG, $DB;

    // Cache all lookup
    static $customfields = null;
    if (null == $customfields) {
        $customfields = array();
    }

    if (!empty($customfields[$userid])) {
        return $customfields[$userid];
    }

    $ret = new object();

    $sql = "SELECT if.shortname, id.data
              FROM {$CFG->prefix}user_info_field if
              JOIN {$CFG->prefix}user_info_data id ON id.fieldid = if.id
             WHERE id.userid = $userid";
    if ($customfields = $DB->get_records_sql($sql)) {
        foreach ($customfields as $field) {
            $fieldname = $field->shortname;
            if (false === $fieldstoinclude or !empty($fieldstoinclude[$fieldname])) {
                $ret->$fieldname = $field->data;
            }
        }
    }

    $customfields[$userid] = $ret;
    return $ret;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 */
function webinar_get_unmailed_reminders()
{
    global $CFG, $DB;

    $submissions = $DB->get_records_sql("
        SELECT
            su.*,
            f.course,
            f.id as webinarid,
            f.name as webinarname,
            f.reminderperiod,
            se.details
        FROM
            {$CFG->prefix}webinar_signups su
        INNER JOIN
            {$CFG->prefix}webinar_signups_status sus
         ON su.id = sus.signupid
        AND sus.superceded = 0
        AND sus.statuscode = ".WEBINAR_STATUS_BOOKED."
        JOIN
            {$CFG->prefix}webinar_sessions se
         ON su.sessionid = se.id
        JOIN
            {$CFG->prefix}webinar f
         ON se.webinar = f.id
        WHERE
            su.mailedreminder = 0
    ");

    if ($submissions) {
        foreach ($submissions as $key => $value) {
            $submissions[$key]->sessiondates = webinar_get_session_dates($value->sessionid);
        }
    }

    return $submissions;
}

/**
 * Add a record to the webinar submissions table and sends out an
 * email confirmation
 *
 * @param class $session record from the webinar_sessions table
 * @param class $webinar record from the webinar table
 * @param class $course record from the course table
 * @param string $discountcode code entered by the user
 * @param integer $notificationtype type of notifications to send to user
 * @see {{WEBINAR_INVITE}}
 * @oaran integer $statuscode Status code to set
 * @param integer $userid user to signup
 * @param bool $notifyuser whether or not to send an email confirmation
 * @param bool $displayerrors whether or not to return an error page on errors
 */
function webinar_user_signup($session, $webinar, $course, $discountcode,
                                $notificationtype, $statuscode, $userid = false,
                                $notifyuser = true) {

    global $CFG, $DB;

    // Get user id
    if (!$userid) {
        global $USER, $DB;
        $userid = $USER->id;
    }

    $return = false;
    $timenow = time();

    // Check to see if a signup already exists
    if ($existingsignup = $DB->get_record('webinar_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {
        // Otherwise, prepare a signup object
        $usersignup = new stdclass;
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->mailedreminder = 0;
    //$usersignup->notificationtype = $notificationtype;
	$usersignup->notificationtype = 0;

    $usersignup->discountcode = trim(strtoupper($discountcode));
    if (empty($usersignup->discountcode)) {
        $usersignup->discountcode = null;
    }

    //begin_sql();

    // Update/insert the signup record
    if (!empty($usersignup->id)) {
        $success = $DB->update_record('webinar_signups', $usersignup);
    } else {
        $usersignup->id = $DB->insert_record('webinar_signups', $usersignup);
        $success = (bool)$usersignup->id;
    }

    if (!$success) {
        //rollback_sql();
        error('Could not update webinar signup record in database');
        return false;
    }

    // Work out which status to use

    // If approval not required
    //if (!$webinar->approvalreqd) {
        $new_status = $statuscode;
    
	
	/*
	} else {
        // If approval required

        // Get current status (if any)
        $current_status = $DB->get_field('webinar_signups_status', 'statuscode', array('signupid' => $usersignup->id, 'superceded' => 0));

        // If approved, then no problem
        if ($current_status == WEBINAR_STATUS_APPROVED) {
            $new_status = $statuscode;
        } else {
        // Otherwise, send manager request
            $new_status = WEBINAR_STATUS_REQUESTED;
        }
    }
	*/

    // Update status
    if (!webinar_update_signup_status($usersignup->id, $new_status, $userid)) {
        //rollback_sql();
        error('Webinar failed to update the user\'s status');
        return false;
    }

    // Add to calendar
    if (in_array($new_status, array(WEBINAR_STATUS_BOOKED, WEBINAR_STATUS_WAITLISTED))) {
        webinar_add_session_to_user_calendar($session, addslashes($webinar->name), $userid, 'booking');
    }

    // If session has already started, do not send a notification
    if (webinar_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification
    if ($notifyuser) {
        // If booked/waitlisted
        switch ($new_status) {
            case WEBINAR_STATUS_BOOKED:
                $error = webinar_send_confirmation_notice($webinar, $session, $userid, $notificationtype, false);
                break;

            case WEBINAR_STATUS_WAITLISTED:
                $error = webinar_send_confirmation_notice($webinar, $session, $userid, $notificationtype, true);
                break;

            case WEBINAR_STATUS_REQUESTED:
                $error = webinar_send_request_notice($webinar, $session, $userid);
                break;
        }

        if (!empty($error)) {
            //rollback_sql();
            error($error);
            return false;
        }

        if (!$DB->update_record('webinar_signups', $usersignup)) {
            //rollback_sql();
            error('Webinar failed to update the user\'s signup');
            return false;
        }
    }

    //commit_sql();
    return true;
}

/**
 * Send booking request notice to user and their manager
 *
 * @param   object  $webinar webinar instance
 * @param   object  $session    Session instance
 * @param   int     $userid     ID of user requesting booking
 * @return  string  Error string, empty on success
 */
function webinar_send_request_notice($webinar, $session, $userid) {
	global $DB;
	
    if (!$manageremail = webinar_get_manageremail($userid)) {
        return get_string('error:nomanagersemailset', 'webinar');
    }

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return get_string('error:invaliduserid', 'webinar');
    }

    $fromaddress = get_config(NULL, 'webinar_fromaddress');
    if (!$fromaddress) {
        $fromaddress = '';
    }

    $postsubject = webinar_email_substitutions(
            $webinar->requestsubject,
            $webinar->name,
            $webinar->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttext = webinar_email_substitutions(
            $webinar->requestmessage,
            $webinar->name,
            $webinar->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttextmgrheading = webinar_email_substitutions(
            $webinar->requestinstrmngr,
            $webinar->name,
            $webinar->reminderperiod,
            $user,
            $session,
            $session->id
    );

    // Send to user
    if (!email_to_user($user, $fromaddress, $postsubject, $posttext)) {
        return get_string('error:cannotsendrequestuser', 'webinar');
    }

    // Send to manager
    $user->email = $manageremail;

    if (!email_to_user($user, $fromaddress, $postsubject, $posttextmgrheading.$posttext)) {
        return get_string('error:cannotsendrequestmanager', 'webinar');
    }

    return '';
}


/**
 * Update the signup status of a particular signup
 *
 * @param integer $signupid ID of the signup to be updated
 * @param integer $statuscode Status code to be updated to
 * @param integer $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 *
 * @returns integer ID of newly created signup status, or false
 *
 */
function webinar_update_signup_status($signupid, $statuscode, $createdby, $note='', $grade=NULL) {
	global $DB;
	
    $timenow = time();

    $signupstatus = new stdclass;
    $signupstatus->signupid = $signupid;
    $signupstatus->statuscode = $statuscode;
    $signupstatus->createdby = $createdby;
    $signupstatus->timecreated = $timenow;
    $signupstatus->note = $note;
    $signupstatus->grade = $grade;
    $signupstatus->superceded = 0;
    $signupstatus->mailed = 0;

    //begin_sql();
    if ($statusid = $DB->insert_record('webinar_signups_status', $signupstatus)) {
        // mark any previous signup_statuses as superceded
        $where = "signupid = $signupid AND ( superceded = 0 OR superceded IS NULL ) AND id != $statusid";
        if($DB->set_field_select('webinar_signups_status', 'superceded', 1, $where)) {
            //commit_sql();
            return $statusid;
        } else {
            //rollback_sql();
            return false;
        }
    } else {
        //rollback_sql();
        return false;
    }
}

/**
 * Cancel a user who signed up earlier
 *
 * @param class $session       Record from the webinar_sessions table
 * @param integer $userid      ID of the user to remove from the session
 * @param bool $forcecancel    Forces cancellation of sessions that have already occurred
 * @param string $errorstr     Passed by reference. For setting error string in calling function
 * @param string $cancelreason Optional justification for cancelling the signup
 */
function webinar_user_cancel($session, $userid=false, $forcecancel=false, &$errorstr=null, $cancelreason='')
{
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    // if $forcecancel is set, cancel session even if already occurred
    // used by webinar_delete_session()
    if (!$forcecancel) {
        $timenow = time();
        // don't allow user to cancel a session that has already occurred
        if (webinar_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'webinar');
            return false;
        }
    }

    if (webinar_user_cancel_submission($session->id, $userid, $cancelreason)) {
        webinar_remove_bookings_from_user_calendar($session, $userid);

        webinar_update_attendees($session);

        return true;
    }

    $errorstr = get_string('error:cancelbooking', 'webinar');
    return false;
}

/**
 * Common code for sending confirmation and cancellation notices
 *
 * @param string $postsubject Subject of the email
 * @param string $posttext Plain text contents of the email
 * @param string $posttextmgrheading Header to prepend to $posttext in manager email
 * @param string $notificationtype The type of notification to send
 * @see {{WEBINAR_INVITE}}
 * @param class $webinar record from the webinar table
 * @param class $session record from the webinar_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function webinar_send_notice($postsubject, $posttext, $posttextmgrheading,
                                $notificationtype, $webinar, $session, $userid) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return get_string('error:invaliduserid', 'webinar');
    }

    if (empty($postsubject) || empty($posttext)) {
        return '';
    }

    // If no notice type is defined (TEXT or ICAL)
    if (!($notificationtype & WEBINAR_BOTH)) {
        // If none, make sure they at least get a text email
        $notificationtype |= WEBINAR_TEXT;
    }

    // If we are cancelling, check if ical cancellations are disabled
    if (($notificationtype & WEBINAR_CANCEL) &&
        get_config(NULL, 'webinar_disableicalcancel')) {
        $notificationtype |= WEBINAR_TEXT; // add a text notification
        $notificationtype &= ~WEBINAR_ICAL; // remove the iCalendar notification
    }

    // If we are sending an ical attachment, set file name
    if ($notificationtype & WEBINAR_ICAL) {
        if ($notificationtype & WEBINAR_INVITE) {
            $attachmentfilename = 'invite.ics';
        }
	    elseif ($notificationtype & WEBINAR_CANCEL) {
	        $attachmentfilename = 'cancel.ics';
	    }
    }

    // Do iCal attachement stuff
    $icalattachments = array();
    if ($notificationtype & WEBINAR_ICAL) {
        if (get_config(NULL, 'webinar_oneemailperday')) {
            // Keep track of all sessiondates
            $sessiondates = $session->sessiondates;

            foreach ($sessiondates as $sessiondate) {
                $session->sessiondates = array($sessiondate); // one day at a time

                $filename = webinar_get_ical_attachment($notificationtype, $webinar, $session, $user);
                $subject = webinar_email_substitutions($postsubject, $webinar->name, $webinar->reminderperiod,
                                                          $user, $session, $session->id);
                $body = webinar_email_substitutions($posttext, $webinar->name, $webinar->reminderperiod,
                                                       $user, $session, $session->id);
                $htmlbody = ''; // TODO
                $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                           'body' => $body, 'htmlbody' => $htmlbody);
            }

            // Restore session dates
            $session->sessiondates = $sessiondates;
        }
        else {
            $filename = webinar_get_ical_attachment($notificationtype, $webinar, $session, $user);
            $subject = webinar_email_substitutions($postsubject, $webinar->name, $webinar->reminderperiod,
                                                      $user, $session, $session->id);
            $body = webinar_email_substitutions($posttext, $webinar->name, $webinar->reminderperiod,
                                                   $user, $session, $session->id);
            $htmlbody = ''; // FIXME
            $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                       'body' => $body, 'htmlbody' => $htmlbody);
        }
    }

    // Fill-in the email placeholders
    $postsubject = webinar_email_substitutions($postsubject, $webinar->name, $webinar->reminderperiod,
                                                  $user, $session, $session->id);
    $posttext = webinar_email_substitutions($posttext, $webinar->name, $webinar->reminderperiod,
                                               $user, $session, $session->id);

    $posttextmgrheading = webinar_email_substitutions($posttextmgrheading, $webinar->name, $webinar->reminderperiod,
                                                         $user, $session, $session->id);

    $posthtml = ''; // FIXME
    $fromaddress = get_config(NULL, 'webinar_fromaddress');
    if (!$fromaddress) {
        $fromaddress = '';
    }

    $usercheck = $DB->get_record('user', array('id' => $userid));

	// Send email with iCal attachment
	if ($notificationtype & WEBINAR_ICAL) {
        foreach ($icalattachments as $attachment) {
            if (!email_to_user($user, $fromaddress, $attachment['subject'], $attachment['body'],
                    $attachment['htmlbody'], $attachment['filename'], $attachmentfilename)) {

                return get_string('error:cannotsendconfirmationuser', 'webinar');
            }
            unlink($CFG->dataroot . '/' . $attachment['filename']);
        }
	}

    // Send plain text email
	if ($notificationtype & WEBINAR_TEXT) {
	    if (!email_to_user($user, $fromaddress, $postsubject, $posttext, $posthtml)) {
            return get_string('error:cannotsendconfirmationuser', 'webinar');
	    }
	}

    // Manager notification
    $manageremail = webinar_get_manageremail($userid);
    if (!empty($posttextmgrheading) and !empty($manageremail)) {
	    $managertext = $posttextmgrheading.$posttext;
        $manager = $user;
        $manager->email = $manageremail;

        // Leave out the ical attachments in the managers notification
        if (!email_to_user($manager, $fromaddress, $postsubject, $managertext, $posthtml)) {
            return get_string('error:cannotsendconfirmationmanager', 'webinar');
        }
	}

    // Third-party notification
    if (!empty($webinar->thirdparty) &&
        (!empty($webinar->thirdpartywaitlist))) {

        $thirdparty = $user;
        $recipients = explode(',', $webinar->thirdparty);
        foreach ($recipients as $recipient) {
            $thirdparty->email = trim($recipient);

            // Leave out the ical attachments in the 3rd parties notification
            if (!email_to_user($thirdparty, $fromaddress, $postsubject, $posttext, $posthtml)) {
                return get_string('error:cannotsendconfirmationthirdparty', 'webinar');
            }
        }
    }
}

/**
 * Send a confirmation email to the user and manager
 *
 * @param class $webinar record from the webinar table
 * @param class $session record from the webinar_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{WEBINAR_INVITE}}
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @returns string Error message (or empty string if successful)
 */
function webinar_send_confirmation_notice($webinar, $session, $userid, $notificationtype, $iswaitlisted) {

    $posttextmgrheading = ''; //$webinar->confirmationinstrmngr;

    if (!$iswaitlisted) {
        $postsubject = ''; //$webinar->confirmationsubject;
        $posttext = ''; //$webinar->confirmationmessage;
    } else {
        $postsubject = ''; //$webinar->waitlistedsubject;
        $posttext = ''; //$webinar->waitlistedmessage;

        // Don't send an iCal attachement when we don't know the date!
        $notificationtype |= WEBINAR_TEXT; // add a text notification
        $notificationtype &= ~WEBINAR_ICAL; // remove the iCalendar notification
    }

    // Set invite bit
    $notificationtype |= WEBINAR_INVITE;

    return webinar_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $webinar, $session, $userid);
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class $webinar record from the webinar table
 * @param class $session record from the webinar_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function webinar_send_cancellation_notice($webinar, $session, $userid) {
	global $DB;
	
    $postsubject = ''; //$webinar->cancellationsubject;
    $posttext = ''; //$webinar->cancellationmessage;
    $posttextmgrheading = ''; //$webinar->cancellationinstrmngr;

    // Lookup what type of notification to send
    $notificationtype = $DB->get_field('webinar_signups', 'notificationtype', array('sessionid' => $session->id, 'userid' => $userid));

    // Set cancellation bit
    $notificationtype |= WEBINAR_CANCEL;

    return webinar_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $webinar, $session, $userid);
}

/**
 * Returns true if the user has registered for a session in the given
 * webinar activity
 *
 * @global class $USER used to get the current userid
 * @returns integer The session id that we signed up for, false otherwise
 */
function webinar_check_signup($webinarid) {

    global $USER;

    if ($submissions = webinar_get_user_submissions($webinarid, $USER->id)) {
        return reset($submissions)->sessionid;
    } else {
        return false;
    }
}

/**
 * Return the email address of the user's manager if it is
 * defined. Otherwise return an empty string.
 *
 * @param integer $userid User ID of the staff member
 */
function webinar_get_manageremail($userid) {
	global $DB;
	
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => WEBINAR_MANAGERSEMAIL_FIELD));
    if ($fieldid) {
        return $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $fieldid));
    }
    else {
        return ''; // No custom field => no manager's email
    }
}

/**
 * Human-readable version of the format of the manager's email address
 */
function webinar_get_manageremailformat() {

    $addressformat = get_config(NULL, 'webinar_manageraddressformat');

    if (!empty($addressformat)) {
        $readableformat = get_config(NULL, 'webinar_manageraddressformatreadable');
        return get_string('manageremailformat', 'webinar', $readableformat);
    }

    return '';
}

/**
 * Returns true if the given email address follows the format
 * prescribed by the site administrator
 *
 * @param string $manageremail email address as entered by the user
 */
function webinar_check_manageremail($manageremail) {

    $addressformat = get_config(NULL, 'webinar_manageraddressformat');

    if (empty($addressformat) || strpos($manageremail, $addressformat)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Set the user's manager email address using a custom field, creating
 * the custom field if it did not exist already.
 *
 * @global class $USER used to get the current userid
 */
function webinar_set_manageremail($manageremail) {

    global $USER, $DB;

    //begin_sql();

    if (!$fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => WEBINAR_MANAGERSEMAIL_FIELD))) {
        // Create the custom field

        $categoryname = clean_param(get_string('modulename', 'webinar'), PARAM_TEXT);
        if (!$categoryid = $DB->get_field('user_info_category', 'id', array('name' => $categoryname))) {
            $category = new object();
            $category->name = $categoryname;
            $category->sortorder = 1;

            if (!$categoryid = $DB->insert_record('user_info_category', $category)) {
                //rollback_sql();
                error_log('WEBINAR: could not create new custom field category');
                return false;
            }
        }

        $record = new stdclass();
        $record->datatype = 'text';
        $record->categoryid = $categoryid;
        $record->shortname = WEBINAR_MANAGERSEMAIL_FIELD;
        $record->name = clean_param(get_string('manageremail', 'webinar'), PARAM_TEXT);

        if (!$fieldid = $DB->insert_record('user_info_field', $record)) {
            //rollback_sql();
            error_log('WEBINAR: could not create new custom field');
            return false;
        }
    }

    $data = new stdclass();
    $data->userid = $USER->id;
    $data->fieldid = $fieldid;
    $data->data = $manageremail;

    if ($dataid = $DB->get_field('user_info_data', 'id', array('userid' => $USER->id, 'fieldid' => $fieldid))) {
        $data->id = $dataid;
        if (!$DB->update_record('user_info_data', $data)) {
            error_log('WEBINAR: could not update existing custom field data');
            //rollback_sql();
            return false;
        }
    }
    else {
        if (!insert_record('user_info_data', $data)) {
            //rollback_sql();
            error_log('WEBINAR: could not insert new custom field data');
            return false;
        }
    }

    //commit_sql();
    return true;
}

/**
 * Mark the fact that the user attended the webinar session by
 * giving that user a grade of 100
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and every submission ID to mark as attended
 *                    under the 'submissionid_XXXX' keys where XXXX is
 *                    the ID of the signup
 */
function webinar_take_attendance($data) {

    global $USER;

    $sessionid = $data->s;

    // Load session
    if(!$session = webinar_get_session($sessionid)) {
        error_log('WEBINAR: Could not load webinar session');
        return false;
    }

    // Check webinar has finished
    if (!webinar_has_session_started($session, time())) {
        error_log('WEBINAR: Can not take attendance for a session that has not yet started');
        return false;
    }

    // Record the selected attendees from the user interface - the other attendees will need their grades set
    // to zero, to indicate non attendance, but only the ticked attendees come through from the web interface.
    // Hence the need for a diff
    $selectedsubmissionids = array();

    // FIXME: This is not very efficient, we should do the grade
    // query outside of the loop to get all submissions for a
    // given webinar ID, then call
    // webinar_grade_item_update with an array of grade
    // objects.
    foreach ($data as $key => $value) {

        $submissionidcheck = substr($key, 0, 13);
        if ($submissionidcheck == 'submissionid_') {
            $submissionid = substr($key, 13);
            $selectedsubmissionids[$submissionid]=$submissionid;

            // Update status
            switch ($value) {

                case WEBINAR_STATUS_NO_SHOW:
                    $grade = 0;
                    break;

                case WEBINAR_STATUS_PARTIALLY_ATTENDED:
                    $grade = 50;
                    break;

                case WEBINAR_STATUS_FULLY_ATTENDED:
                    $grade = 100;
                    break;

                default:
                    // This use has not had attendance set
                    // Jump to the next item in the foreach loop
                    continue 2;
            }

            webinar_update_signup_status($submissionid, $value, $USER->id, '', $grade);

            if (!webinar_take_individual_attendance($submissionid, $grade)) {
                error_log("WEBINAR: could not mark '$submissionid' as ".$value);
                return false;
            }
        }
    }

    return true;
}

/**
 * Mark users' booking requests as declined or approved
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and an array of request approval/denies
 */
function webinar_approve_requests($data) {
    global $USER, $DB;

    // Check request data
    if (empty($data->requests) || !is_array($data->requests)) {
        error_log('WEBINAR: No request data supplied');
        return false;
    }

    $sessionid = $data->s;

    // Load session
    if (!$session = webinar_get_session($sessionid)) {
        error_log('WEBINAR: Could not load webinar session');
        return false;
    }

    // Load webinar
    if (!$webinar = $DB->get_record('webinar', array('id' => $session->webinar))) {
        error_log('WEBINAR: Could not load webinar instance');
        return false;
    }

    // Load course
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        error_log('WEBINAR: Could nto load course');
        return false;
    }

    // Loop through requests
    foreach ($data->requests as $key => $value) {

        // Check key/value
        if (!is_numeric($key) || !is_numeric($value)) {
            continue;
        }

        // Load user submission
        if (!$attendee = webinar_get_attendee($sessionid, $key)) {
            error_log('WEBINAR: User '.$key.' not an attendee of this session');
            continue;;
        }

        // Update status
        switch ($value) {

            // Decline
            case 1:
                webinar_update_signup_status(
                        $attendee->submissionid,
                        WEBINAR_STATUS_DECLINED,
                        $USER->id
                );

                // Send a cancellation notice to the user
                webinar_send_cancellation_notice($webinar, $session, $attendee->id);

                break;

            // Approve
            case 2:
                webinar_update_signup_status(
                        $attendee->submissionid,
                        WEBINAR_STATUS_APPROVED,
                        $USER->id
                );

                // Check if there is capacity
                if (webinar_session_has_capacity($session)) {
                    $status = WEBINAR_STATUS_BOOKED;
                } else {
                    $status = WEBINAR_STATUS_WAITLISTED;
                }

                // Signup user
                if (!webinar_user_signup(
                        $session,
                        $webinar,
                        $course,
                        $attendee->discountcode,
                        $attendee->notificationtype,
                        $status,
                        $attendee->id
                    )) {
                    continue;
                }

                break;

            case 0:
            default:
                // Change nothing
                continue;
        }
    }

    return true;
}

/*
 * Set the grading for an individual submission, to either 0 or 100 to indicate attendance
 * @param $submissionid The id of the submission in the database
 * @param $grading Grade to set
 */
function webinar_take_individual_attendance($submissionid, $grading) {
    global $USER, $CFG, $DB;

    $timenow = time();

    $record = $DB->get_record_sql("SELECT f.*, s.userid
                                FROM {$CFG->prefix}webinar_signups s
                                JOIN {$CFG->prefix}webinar_sessions fs ON s.sessionid = fs.id
                                JOIN {$CFG->prefix}webinar f ON f.id = fs.webinar
                                JOIN {$CFG->prefix}course_modules cm ON cm.instance = f.id
                                JOIN {$CFG->prefix}modules m ON m.id = cm.module
                               WHERE s.id = $submissionid AND m.name='webinar'");

    $grade = new stdclass();
    $grade->userid = $record->userid;
    $grade->rawgrade = $grading;
    $grade->rawgrademin = 0;
    $grade->rawgrademax = 100;
    $grade->timecreated = $timenow;
    $grade->timemodified = $timenow;
    $grade->usermodified = $USER->id;

    return webinar_grade_item_update($record, $grade);
}

/**
 * Used by course/lib.php to display a few sessions besides the
 * webinar activity on the course page
 *
 * @global class $USER used to get the current userid
 * @global class $CFG used to get the path to the module
 */
function webinar_print_coursemodule_info($coursemodule)
{
    global $CFG, $USER, $DB;

    $contextmodule = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
    if (!has_capability('mod/webinar:view', $contextmodule)) {
        return ''; // not allowed to view this activity
    }
    $contextcourse = get_context_instance(CONTEXT_COURSE, $coursemodule->course);
    // can view attendees
    $viewattendees = has_capability('mod/webinar:viewattendees', $contextcourse);

    $table = '';
    $timenow = time();
    $webinarpath = "$CFG->wwwroot/mod/webinar";

    $webinarid = $coursemodule->instance;
    $webinar = $DB->get_record('webinar', array('id' => $webinarid));
    if (!$webinar) {
        error_log("webinar: ask to print coursemodule info for a non-existent activity ($webinarid)");
        return '';
    }

    $htmlactivitynameonly = '<img src="icon.gif" class="activityicon" alt="'.$webinar->name.'" /> '
            .$webinar->name;
    $strviewallsessions = get_string('viewallsessions', 'webinar');
    $htmlviewallsessions = '<a class="f2fsessionlinks" href="'.$webinarpath.'/view.php?f='.$webinarid.'" title="'.$strviewallsessions.'">'
        .$strviewallsessions.'</a>';

    if ($submissions = webinar_get_user_submissions($webinarid, $USER->id)) {
        // User has signedup for the instance
        $submission = array_shift($submissions);

        if ($session = webinar_get_session($submission->sessionid)) {
            $sessiondate = '';
            $sessiontime = '';


                foreach ($session->sessiondates as $date) {
                    if (!empty($sessiondate)) {
                        $sessiondate .= '<br />';
                    }
                    $sessiondate .= userdate($date->timestart, get_string('strftimedate'));
                    if (!empty($sessiontime)) {
                        $sessiontime .= '<br />';
                    }
                    $sessiontime .= userdate($date->timestart, get_string('strftimetime')).
                        ' - '.userdate($date->timefinish, get_string('strftimetime'));
                }
 

            // don't include the link to cancel a session if it has already occurred
            $cancellink = '';
            if (!webinar_has_session_started($session, $timenow)) {
                $strcancelbooking = get_string('cancelbooking', 'webinar');
                $cancellink = "<tr><td><a class=\"f2fsessionlinks\" href=\"$webinarpath/cancelsignup.php?s={$session->id}\" title=\"$strcancelbooking\">$strcancelbooking</a></td></tr>";
            }

            $strmoreinfo = get_string('moreinfo', 'webinar');
            $strseeattendees = get_string('seeattendees', 'webinar');

            $location = '&nbsp;';
            $venue = '&nbsp;';
            $customfielddata = webinar_get_customfielddata($session->id);
            if (!empty($customfielddata['location'])) {
                $location = $customfielddata['location']->data;
            }
            if (!empty($customfielddata['venue'])) {
                $venue = $customfielddata['venue']->data;
            }

            // don't include the link to view attendees if user is lacking capability
            $attendeeslink = '';
            if ($viewattendees) {
                $attendeeslink = "<tr><td><a class=\"f2fsessionlinks\" href=\"$webinarpath/attendees.php?s=$session->id\" title=\"$strseeattendees\">$strseeattendees</a></td></tr>";
            }

            $table = '<table border="0" cellpadding="1" cellspacing="0" width="90%" summary="" style="display:inline-table">'
                .'<tr>'
                .'<td class="f2fsessionnotice" colspan="4">'.$htmlactivitynameonly.'</td>'
                .'</tr>'
                .'<tr>'
                .'<td class="f2fsessionnotice" colspan="4">'.get_string('bookingstatus', 'webinar').':</td>'
                .'<td><span class="f2fsessionnotice" >'.get_string('options', 'webinar').':</span></td>'
                .'</tr>'
                .'<tr>'
                .'<td>'.$location.'</td>'
                .'<td>'.$venue.'</td>'
                .'<td>'.$sessiondate.'</td>'
                .'<td>'.$sessiontime.'</td>'
                ."<td><table border=\"0\" summary=\"\"><tr><td><a class=\"f2fsessionlinks\" href=\"$webinarpath/signup.php?s=$session->id\" title=\"$strmoreinfo\">$strmoreinfo</a></td>"
                .'</tr>'
                .$attendeeslink
                .$cancellink
                .'<tr>'
                ."<td>$htmlviewallsessions</td>"
                .'</tr>'
                .'</table></td></tr>'
                .'</table>';
        }
    }
    elseif ($webinar->display > 0 && $sessions = webinar_get_sessions($webinarid) ) {

        $table = '<table border="0" cellpadding="1" cellspacing="0" width="100%" summary="" style="display:inline-table">'
            .'   <tr>'
            .'       <td class="f2fsessionnotice" colspan="2">'.$htmlactivitynameonly.'</td>'
            .'   </tr>'
            .'   <tr>'
            .'       <td class="f2fsessionnotice" colspan="2">'.get_string('signupforsession', 'webinar').':</td>'
            .'   </tr>';

        $i=0;
        foreach($sessions as $session) {
            if ((webinar_has_session_started($session, $timenow))) {
                continue;
            }

            if (!webinar_session_has_capacity($session, $contextmodule)) {
                continue;
            }

            $multiday = '';
            $sessiondate = '';
            $sessiontime = '';


                if (empty($session->sessiondates)) {
                    $sessiondate = get_string('unknowndate', 'webinar');
                    $sessiontime = get_string('unknowntime', 'webinar');
                }
                else {
                    $sessiondate = userdate($session->sessiondates[0]->timestart, get_string('strftimedate'));
                    $sessiontime = userdate($session->sessiondates[0]->timestart, get_string('strftimetime')).
                        ' - '.userdate($session->sessiondates[0]->timefinish, get_string('strftimetime'));
                    if (count($session->sessiondates) > 1) {
                        $multiday = ' ('.get_string('multiday', 'webinar').')';
                    }
                }
 

            if ($i == 0) {
                $table .= '   <tr>';
                $i++;
            }
            else if ($i++ % 2 == 0) {
                if ($i > $webinar->display) {
                    break;
                }
                $table .= '   </tr>';
                $table .= '   <tr>';
            }

            $locationstring = '';
            $customfielddata = webinar_get_customfielddata($session->id);
            if (!empty($customfielddata['location']) && trim($customfielddata['location']->data) != '') {
                $locationstring = $customfielddata['location']->data . ', ';
            }

            $table .= "      <td><a href=\"$webinarpath/signup.php?s=$session->id\">{$locationstring}$sessiondate<br />{$sessiontime}$multiday</a></td>";
        }
        if ($i++ % 2 == 0) {
            $table .= '<td>&nbsp;</td>';
        }

        $table .= '   </tr>'
            .'   <tr>'
            ."     <td colspan=\"2\">$htmlviewallsessions</td>"
            .'   </tr>'
            .'</table>';
    }
    elseif (has_capability('mod/webinar:viewemptyactivities', $contextmodule)) {
        return '<span class="f2fsessionnotice" style="line-height:1.5">'.$htmlactivitynameonly.'<br />'.$htmlviewallsessions.'</span>';
    }
    else {
        // Nothing to display to this user
    }

    return $table;
}

/**
 * Returns the ICAL data for a webinar meeting.
 *
 * @param integer $method The method, @see {{WEBINAR_INVITE}}
 * @return string Filename of the attachment in the temp directory
 */
function webinar_get_ical_attachment($method, $webinar, $session, $user)
{
    global $CFG;

    // First, generate all the VEVENT blocks
    $VEVENTS = '';
    foreach ($session->sessiondates as $date) {
        // Date that this representation of the calendar information was created -
        // we use the time the session was created
        // http://www.kanzaki.com/docs/ical/dtstamp.html
        $DTSTAMP = webinar_ical_generate_timestamp($session->timecreated);

        // UIDs should be globally unique
        $urlbits = parse_url($CFG->wwwroot);
        $UID =
            $DTSTAMP .
            '-' . substr(md5($CFG->siteidentifier . $session->id . $date->id), -8) .   // Unique identifier, salted with site identifier
            '@' . $urlbits['host'];                                                    // Hostname for this moodle installation

        $DTSTART = webinar_ical_generate_timestamp($date->timestart);
        $DTEND   = webinar_ical_generate_timestamp($date->timefinish);

        // FIXME: currently we are not sending updates if the times of the
        // sesion are changed. This is not ideal!
        $SEQUENCE = ($method & WEBINAR_CANCEL) ? 1 : 0;

        $SUMMARY     = webinar_ical_escape($webinar->name);
        $DESCRIPTION = webinar_ical_escape($session->details, true);

        // Get the location data from custom fields if they exist
        $customfielddata = webinar_get_customfielddata($session->id);
        $locationstring = '';
        if (!empty($customfielddata['room'])) {
            $locationstring .= $customfielddata['room']->data;
        }
        if (!empty($customfielddata['venue'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['venue']->data;
        }
        if (!empty($customfielddata['location'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['location']->data;
        }

        // NOTE: Newlines are meant to be encoded with the literal sequence
        // '\n'. But evolution presents a single line text field for location,
        // and shows the newlines as [0x0A] junk. So we switch it for commas
        // here. Remember commas need to be escaped too.
        $LOCATION    = str_replace('\n', '\, ', webinar_ical_escape($locationstring));

        $ORGANISEREMAIL = get_config(NULL, 'webinar_fromaddress');

        $ROLE = 'REQ-PARTICIPANT';
        $CANCELSTATUS = '';
        if ($method & WEBINAR_CANCEL) {
            $ROLE = 'NON-PARTICIPANT';
            $CANCELSTATUS = "\nSTATUS:CANCELLED";
        }

        $icalmethod = ($method & WEBINAR_INVITE) ? 'REQUEST' : 'CANCEL';

        // FIXME: if the user has input their name in another language, we need
        // to set the LANGUAGE property parameter here
        $USERNAME = fullname($user);
        $MAILTO   = $user->email;

        // The extra newline at the bottom is so multiple events start on their
        // own lines. The very last one is trimmed outside the loop
        $VEVENTS .= <<<EOF
BEGIN:VEVENT
UID:{$UID}
DTSTAMP:{$DTSTAMP}
DTSTART:{$DTSTART}
DTEND:{$DTEND}
SEQUENCE:{$SEQUENCE}
SUMMARY:{$SUMMARY}
LOCATION:{$LOCATION}
DESCRIPTION:{$DESCRIPTION}
CLASS:PRIVATE
TRANSP:OPAQUE{$CANCELSTATUS}
ORGANIZER;CN={$ORGANISEREMAIL}:MAILTO:{$ORGANISEREMAIL}
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$ROLE};PARTSTAT=NEEDS-ACTION;
 RSVP=FALSE;CN={$USERNAME};LANGUAGE=en:MAILTO:{$MAILTO}
END:VEVENT

EOF;
    }

    $VEVENTS = trim($VEVENTS);

    // TODO: remove the hard-coded timezone!
    $template = <<<EOF
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Moodle//NONSGML Facetoface//EN
VERSION:2.0
METHOD:{$icalmethod}
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Tzfile/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZNAME:NZST
DTSTART:19700405T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=4
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:NZDT
DTSTART:19700928T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE
{$VEVENTS}
END:VCALENDAR
EOF;

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    file_put_contents($tempfilepathname, $template);
    return $tempfilename;
}

function webinar_ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}

/**
 * Escapes data of the text datatype in ICAL documents.
 *
 * See RFC2445 or http://www.kanzaki.com/docs/ical/text.html or a more readable definition
 */
function webinar_ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ','),
        array('\\\\', '\n', '\;', '\,'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one
    // whitespace after the newline that does the wrapping
    $text = wordwrap($text, 75, "\n ", true);

    return $text;
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $webinar null means all webinar activities
 * @param int $userid specific user only, 0 mean all (not used here)
 */
function webinar_update_grades($webinar=null, $userid=0) {

    if ($webinar != null) {
            webinar_grade_item_update($webinar);
    }
    else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {$CFG->prefix}webinar f
                  JOIN {$CFG->prefix}course_modules cm ON cm.instance = f.id
                  JOIN {$CFG->prefix}modules m ON m.id = cm.module
                 WHERE m.name='webinar'";
        if ($rs = $DB->get_records(et_sql($sql))) {
            while ($webinar = rs_fetch_next_record($rs)) {
                webinar_grade_item_update($webinar);
            }
            rs_close($rs);
        }
    }
}

/**
 * Create grade item for given Webinar session
 *
 * @param int webinar  Webinar activity (not the session) to grade
 * @param mixed grades    grades objects or 'reset' (means reset grades in gradebook)
 * @return int 0 if ok, error code otherwise
 */
function webinar_grade_item_update($webinar, $grades=NULL) {
    global $CFG, $DB;

    if (!isset($webinar->cmidnumber)) {

        $sql = "SELECT cm.idnumber as cmidnumber
                  FROM {$CFG->prefix}course_modules cm
                  JOIN {$CFG->prefix}modules m ON m.id = cm.module
                 WHERE m.name='webinar' AND cm.instance = $webinar->id";
        $webinar->cmidnumber = $DB->get_field_sql($sql);
    }

    $params = array('itemname'=>$webinar->name,
                    'idnumber'=>$webinar->cmidnumber);

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademin']  = 0;
    $params['gradepass'] = 100;
    $params['grademax']  = 100;

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    $retcode = grade_update('mod/webinar', $webinar->course, 'mod', 'webinar',
                            $webinar->id, 0, $grades, $params);
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Delete grade item for given webinar
 *
 * @param object $webinar object
 * @return object webinar
 */
function webinar_grade_item_delete($webinar) {
    $retcode = grade_update('mod/webinar', $webinar->course, 'mod', 'webinar',
                            $webinar->id, 0, NULL, array('deleted'=>1));
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Return number of attendees signed up to a webinar session
 *
 * @param integer $session_id
 * @return integer
 */
function webinar_get_num_attendees($session_id) {
    global $CFG, $DB;
    // for the session, pick signups that haven't been superceded, or cancelled
    return (int) $DB->count_records_sql("select count(ss.id) from {$CFG->prefix}webinar_signups su
        JOIN {$CFG->prefix}webinar_signups_status ss ON su.id = ss.signupid
        WHERE sessionid=$session_id AND ss.superceded=0 AND ss.statuscode >= ".WEBINAR_STATUS_APPROVED);
}

/**
 * Return all of a users' submissions to a webinar
 *
 * @param integer $webinarid
 * @param integer $userid
 * @param boolean $includecancellations
 * @return array submissions | false No submissions
 */
function webinar_get_user_submissions($webinarid, $userid, $includecancellations=false) {
    global $CFG, $DB;

    $whereclause = "s.webinar=$webinarid AND su.userid=$userid AND ss.superceded != 1";

    // If not show cancelled, only show requested and up status'
    if (!$includecancellations) {
        $whereclause .= ' AND ss.statuscode >= '.WEBINAR_STATUS_REQUESTED.' AND ss.statuscode < '.WEBINAR_STATUS_NO_SHOW;
    }

    //TODO fix mailedconfirmation, timegraded, timecancelled, etc
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.webinar,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            ss.statuscode
        FROM
            {$CFG->prefix}webinar_sessions s
        JOIN
            {$CFG->prefix}webinar_signups su
         ON su.sessionid = s.id
        JOIN
            {$CFG->prefix}webinar_signups_status ss
         ON su.id = ss.signupid
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ");
}

/**
* Return all of a users' submissions to a webinar SESSION
 * @param integer $webinarid
 * @param integer $userid
 * @param integer $sessionid
 * @param boolean $includecancellations
 * @return array submissions | false No submissions
*/
function webinar_session_get_user_submissions($webinarid, $userid, $sessionid, $includecancellations=false) {
    global $CFG, $DB;

    $whereclause = "su.id = ss.signupid AND su.sessionid = s.id AND s.id=$sessionid AND s.webinar=$webinarid AND su.userid=$userid AND ss.superceded != 1";

    // If not show cancelled, only show requested and up status'
    if (!$includecancellations) {
        $whereclause .= ' AND ss.statuscode >= '.WEBINAR_STATUS_REQUESTED.' AND ss.statuscode < '.WEBINAR_STATUS_NO_SHOW;
    }

    //TODO fix mailedconfirmation, timegraded, timecancelled, etc
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.webinar,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            ss.statuscode
        FROM
            {$CFG->prefix}webinar_sessions s, {$CFG->prefix}webinar_signups su, {$CFG->prefix}webinar_signups_status ss 
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ");
}



/**
 * Cancel users' submission to a webinar session
 *
 * @param integer $sessionid   ID of the webinar_sessions record
 * @param integer $userid      ID of the user record
 * @param string $cancelreason Short justification for cancelling the signup
 * @return boolean success
 */
function webinar_user_cancel_submission($sessionid, $userid, $cancelreason='')
{
	global $DB;
	
    $signup = $DB->get_record('webinar_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    if (!$signup) {
        return true; // not signed up, nothing to do
    }

    return webinar_update_signup_status($signup->id, WEBINAR_STATUS_USER_CANCELLED, $userid, $cancelreason);
}

/**
 * A list of actions in the logs that indicate view activity for participants
 */
function webinar_get_view_actions() {
    return array('view', 'view all');
}

/**
 * A list of actions in the logs that indicate post activity for participants
 */
function webinar_get_post_actions() {
    return array('cancel booking', 'signup');
}

/**
 * Return a small object with summary information about what a user
 * has done with a given particular instance of this module (for user
 * activity reports.)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 */
function webinar_user_outline($course, $user, $mod, $webinar) {

    $result = new stdClass;

    $grade = webinar_get_grade($user->id, $course->id, $webinar->id);
    if ($grade->grade > 0) {
        $result = new stdClass;
        $result->info = get_string('grade') . ': ' . $grade->grade;
        $result->time = $grade->dategraded;
    }
    elseif ($submissions = webinar_get_user_submissions($webinar->id, $user->id)) {
        $result->info = get_string('usersignedup', 'webinar');
        $result->time = reset($submissions)->timecreated;
    }
    else {
        $result->info = get_string('usernotsignedup', 'webinar');
    }

    return $result;
}

/**
 * Print a detailed representation of what a user has done with a
 * given particular instance of this module (for user activity
 * reports).
 */
function webinar_user_complete($course, $user, $mod, $webinar) {

    $grade = webinar_get_grade($user->id, $course->id, $webinar->id);

    if ($submissions = webinar_get_user_submissions($webinar->id, $user->id, true)) {
        print get_string('grade').': '.$grade->grade . '<br />';
        if ($grade->dategraded > 0) {
            $timegraded = trim(userdate($grade->dategraded, get_string('strftimedatetime')));
            print '('.format_string($timegraded).')<br />';
        }
        print '<br />';

        foreach ($submissions as $submission) {
            $timesignedup = trim(userdate($submission->timecreated, get_string('strftimedatetime')));
            print get_string('usersignedupon', 'webinar', format_string($timesignedup)) . '<br />';

            if ($submission->timecancelled > 0) {
                $timecancelled = userdate($submission->timecancelled, get_string('strftimedatetime'));
                print get_string('usercancelledon', 'webinar', format_string($timecancelled)) . '<br />';
            }
        }
    }
    else {
        print get_string('usernotsignedup', 'webinar');
    }

    return true;
}

/**
 * Add a link to the session to this user's Moodle calendar.
 *
 * @param class   $session     Record from the webinar_sessions table
 * @param class   $eventname   Name to display for this event
 * @param integer $userid      ID of the user
 * @param string  $eventtype   Type of the event (booking or session)
 */
function webinar_add_session_to_user_calendar($session, $eventname, $userid, $eventtype)
{
    global $CFG, $DB;



    $detailsurl = $CFG->wwwroot . '/mod/webinar/';
    $detailsurl .= ('session' == $eventtype) ? 'attendees' : 'signup';
    $detailsurl .= ".php?s=$session->id";

    $result = true;
	
    foreach ($session->sessiondates as $date) {
        $newevent = new object();
        $newevent->name = $eventname;
        $newevent->description = get_string("calendareventdescription$eventtype", 'webinar', $detailsurl);
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = 0; // Not a course event
        $newevent->groupid = 0;
        $newevent->userid = $userid;
        $newevent->instance = $session->webinar;
        $newevent->modulename = 'webinar';
        $newevent->eventtype = "webinar$eventtype";
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}

/**
 * Add a link to the session to the site Calendar
 *
 * @param class   $session     Record from the webinar_sessions table
 * @param class   $webinar  Record from the webinar table
 */
function webinar_add_session_to_site_calendar($session, $webinar)
{
    global $CFG, $DB;

    if (empty($webinar->showoncalendar)) {
        return true; // not meant for the calendar
    }

    $shortname = $webinar->shortname;
    if (empty($shortname)) {
        $shortname = substr($webinar->name, 0, WEBINAR_CALENDAR_MAX_NAME_LENGTH);
    }

    $description = '';
    if (!empty($webinar->description)) {
        $description .= '<p>'.clean_param($webinar->description, FORMAT_HTML).'</p>';
    }
    $description .= webinar_print_session($session, false, true, true);
    $signupurl = "$CFG->wwwroot/mod/webinar/signup.php?s=$session->id";
    $description .= '<a href="' . $signupurl . '">' . get_string('signupforthissession', 'webinar') . '</a>';

    $result = true;
    foreach ($session->sessiondates as $date) {
        $newevent = new object();
        $newevent->name = addslashes($shortname);
        $newevent->description = addslashes($description);
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = SITEID; // site-wide event
        $newevent->groupid = 0;
        $newevent->userid = 0; // not a user event
        $newevent->uuid = "$session->id";
        $newevent->instance = $session->webinar;
        $newevent->modulename = 'webinar';
        $newevent->eventtype = "webinarsession";
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}

/**
 * Remove all entries in the student's calendar which relate to this session.
 *
 * @param class $session    Record from the webinar_sessions table
 * @param integer $userid   ID of the user
 */
function webinar_remove_bookings_from_user_calendar($session, $userid)
{
	global $DB;
	
    return $DB->delete_records_select('event', "modulename = 'webinar' AND
                                           eventtype = 'webinarbooking' AND
                                           instance = $session->webinar AND
                                           userid = $userid AND
                                           courseid = 0");
}

/**
 * Remove all entries in the site calendar which relate to this session.
 *
 * @param class $session       Record from the webinar_sessions table
 */
function webinar_remove_session_from_site_calendar($session)
{
	global $DB;
	
	$delarray = array(
						'modulename' => 'webinar',
						'eventtype' => 'webinarsession',
						'instance' => $session->webinar,
						'courseid' => SITEID,
						'uuid' => $session->id,
						'userid' => '0'
						
	);
	
	return $DB->delete_records('event', $delarray);
	
	
	
    /*return $DB->delete_records_select('event', "modulename = 'webinar' AND
                                           eventtype = 'webinarsession' AND
                                           instance = $session->webinar AND
                                           courseid = ". SITEID . " AND
                                           uuid = '$session->id' AND
                                           userid = 0");*/
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param class  $session    Record from the webinar_sessions table
 * @param string $eventtype  Type of the event (booking or session)
 */
function webinar_update_calendar_events($session, $eventtype)
{
    global $CFG, $DB;

    $whereclause = "modulename = 'webinar' AND eventtype = 'webinar$eventtype' AND instance = $session->webinar";

    if ('session' == $eventtype) {
        $whereclause .= " AND description LIKE '%attendees.php?s=$session->id%'";
    }
	
	$wherearray = array(
						'modulename' => 'webinar',
						'eventtype' => 'webinar' . $eventtype,
						'instance' => $session->webinar,
	);
	
    // Find all users with this session in their calendar
	//$users_sql = "SELECT DISTINCT userid FROM ".$CFG->prefix."event WHERE $whereclause";
    //$users = $DB->get_records_sql($users_sql);
	
	$users = $DB->get_records('event', $wherearray, '', 'userid', '', 1);

    $result = true;
    if ($users and count($users) > 0) {
        // Delete the existing events
        $result = $result && $DB->delete_records('event', $wherearray);

        // Add this session to these users' calendar
        $eventname = $DB->get_field('webinar', 'name', array('id' => $session->webinar));
        foreach($users as $user) {
            $result = $result && webinar_add_session_to_user_calendar($session, $eventname, $user->userid, $eventtype);
        }
    }

    return $result;
}

/**
 * Confirm that a user can be added to a session.
 *
 * @param class  $session Record from the webinar_sessions table
 * @param object $context (optional) A context object (record from context table)
 * @return bool True if user can be added to session
 **/
function webinar_session_has_capacity($session, $context = false) {

    if (empty($session)) {
        return false;
    }

    // If allowoverbook enabled
    //if ($session->allowoverbook) {
    //    return true;
    //}

    $signupcount = webinar_get_num_attendees($session->id);
    if ($signupcount >= $session->capacity) {
        // if session is full, check if overbooking is allowed for this user
        if (!$context || !has_capability('mod/webinar:overbook', $context)) {
            return false;
        }
    }

    return true;
}

/**
 * Print the details of a session
 *
 * @param object $session         Record from webinar_sessions
 * @param boolean $showcapacity   Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return         Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup     Hide any messages relating to signing up
 */
function webinar_print_session($session, $showcapacity, $calendaroutput=false, $return=false, $hidesignup=false)
{
    global $CFG, $DB;

	//print_r($session);
	
    //$table = new object();
	$table = new html_table();
    $table->summary = get_string('sessionsdetailstablesummary', 'webinar');
    $table->class = 'f2fsession';
    $table->width = '50%';
    $table->align = array('right', 'left');
    if ($calendaroutput) {
        $table->tablealign = 'left';
    }

    $customfields = webinar_get_session_customfields();
    $customdata = $DB->get_records('webinar_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $data = '';
        if (!empty($customdata[$field->id])) {
            if (WEBINAR_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $values = explode(WEBINAR_CUSTOMFIELD_DELIMITTER, $customdata[$field->id]->data);
                $data = implode(', ', $values);
            }
            else {
                $data = $customdata[$field->id]->data;
            }
        }
        $table->data[] = array(str_replace(' ', '&nbsp;', format_string($field->name)), format_string($data));
    }

	$presenter_name = $DB->get_records_sql("SELECT
                        u.firstname,
                        u.lastname
                    FROM 
						{$CFG->prefix}user u 
                    WHERE 
						u.id = $session->presenter 
					LIMIT 1");

				
	if($presenter_name) {
		foreach($presenter_name as $pname) {
			$presenter = $pname->firstname . " " . $pname->lastname;
		}
	}
	
	
	$table->data[] = array(get_string('presenter', 'webinar'), $presenter);
	
    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'webinar'));
    
    $html = '';
        foreach($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= '<br/>';
            }
            $timestart = userdate($date->timestart, get_string('strftimedatetime'));
            $timefinish = userdate($date->timefinish, get_string('strftimedatetime'));
            $html .= "$timestart &ndash; $timefinish";
        }
    $table->data[] = array($strdatetime, $html);

    $signupcount = webinar_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        //if ($session->allowoverbook) {
        //    $table->data[] = array(get_string('capacity', 'webinar'), $session->capacity . ' ('.strtolower(get_string('allowoverbook', 'webinar')).')');
        //} else {
            $table->data[] = array(get_string('capacity', 'webinar'), $session->capacity);
        //}
    }
    elseif (!$calendaroutput) {
        $table->data[] = array(get_string('seatsavailable', 'webinar'), max(0, $placesleft));
    }

	/*
    // Display requires approval notification
    $webinar = $DB->get_record('webinar', array('id' => $session->webinar));

    if ($webinar->approvalreqd) {
        $table->data[] = array('', get_string('sessionrequiresmanagerapproval', 'webinar'));
    }

    // Display waitlist notification
    if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
        $table->data[] = array('', get_string('userwillbewaitlisted', 'webinar'));
    }

    if (!empty($session->duration)) {
        $table->data[] = array(get_string('duration', 'webinar'), webinar_format_duration($session->duration));
    }
    if (!empty($session->normalcost)) {
        $table->data[] = array(get_string('normalcost', 'webinar'), webinar_format_cost($session->normalcost));
    }
    if (!empty($session->discountcost)) {
        $table->data[] = array(get_string('discountcost', 'webinar'), webinar_format_cost($session->discountcost));
    }
    if (!empty($session->details)) {
        $details = clean_text($session->details, FORMAT_HTML);
        $table->data[] = array(get_string('details', 'webinar'), $details);
    }

    // Display trainers
    $trainerroles = webinar_get_trainer_roles();

    if ($trainerroles) {
        // Get trainers
        $trainers = webinar_get_trainers($session->id);

        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->name;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainer_names = array();
            foreach ($trainers[$role] as $trainer) {
                $trainer_names[] = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$trainer->id.'">'.fullname($trainer).'</a>';
            }

            $table->data[] = array($rolename, implode(', ', $trainer_names));
        }
    }
	*/

    //return print_table($table, $return);
	return html_writer::table($table, $return);
}

/**
 * Update the value of a customfield for the given session/notice.
 *
 * @param integer $fieldid    ID of a record from the webinar_session_field table
 * @param string  $data       Value for that custom field
 * @param integer $otherid    ID of a record from the webinar_(sessions|notice) table
 * @param string  $table      'session' or 'notice' (part of the table name)
 * @returns true if it succeeded, false otherwise
 */
function webinar_save_customfield_value($fieldid, $data, $otherid, $table)
{
	global $DB;
	
    $dbdata = null;
    if (is_array($data)) {
        $dbdata = trim(implode(WEBINAR_CUSTOMFIELD_DELIMITTER, $data), ';');
    }
    else {
        $dbdata = trim($data);
    }

    $newrecord = new object();
    $newrecord->data = $dbdata;

    $fieldname = "{$table}id";
    if ($record = $DB->get_record("webinar_{$table}_data", array('fieldid' => $fieldid, $fieldname => $otherid))) {
        if (empty($dbdata)) {
            // Clear out the existing value
            return $DB->delete_records("webinar_{$table}_data", array('id' => $record->id));
        }

        $newrecord->id = $record->id;
        return $DB->update_record("webinar_{$table}_data", $newrecord);
    }
    else {
        if (empty($dbdata)) {
            return true; // no need to store empty values
        }

        $newrecord->fieldid = $fieldid;
        $newrecord->$fieldname = $otherid;
        return $DB->insert_record("webinar_{$table}_data", $newrecord);
    }
}

/**
 * Return the value of a customfield for the given session/notice.
 *
 * @param object  $field    A record from the webinar_session_field table
 * @param integer $otherid  ID of a record from the webinar_(sessions|notice) table
 * @param string  $table    'session' or 'notice' (part of the table name)
 * @returns string The data contained in this custom field (empty string if it doesn't exist)
 */
function webinar_get_customfield_value($field, $otherid, $table)
{
	global $DB;
	
    if ($record = $DB->get_record("webinar_{$table}_data", array('fieldid' => $field->id, "{$table}id" => $otherid))) {
        if (!empty($record->data)) {
            if (WEBINAR_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                return explode(WEBINAR_CUSTOMFIELD_DELIMITTER, $record->data);
            }
            return $record->data;
        }
    }
    return '';
}

/**
 * Return the values stored for all custom fields in the given session.
 *
 * @param integer $sessionid  ID of webinar_sessions record
 * @returns array Indexed by field shortnames
 */
function webinar_get_customfielddata($sessionid)
{
    global $CFG, $DB;

    $sql = "SELECT f.shortname, d.data
              FROM {$CFG->prefix}webinar_session_field f
              JOIN {$CFG->prefix}webinar_session_data d ON f.id = d.fieldid
             WHERE d.sessionid = $sessionid";
    if ($records = $DB->get_records_sql($sql)) {
        return $records;
    }
    return array();
}

/**
 * Return a cached copy of all records in webinar_session_field
 */
function webinar_get_session_customfields()
{
	global $DB;
	
    static $customfields = null;
    if (null == $customfields) {
        if (!$customfields = $DB->get_records('webinar_session_field')) {
            $customfields = array();
        }
    }
    return $customfields;
}

/**
 * Display the list of custom fields in the site-wide settings page
 */
function webinar_list_of_customfields()
{
    global $CFG, $USER, $DB;

    if ($fields = $DB->get_records('webinar_session_field', NULL, 'name', 'id, name')) {
        //$table = new stdClass;
		$table = new html_table();
        $table->width = '50%';
        $table->tablealign = 'left';
        $table->data = array();
        $table->size = array('100%');
        foreach ($fields as $field) {
            $fieldname = format_string($field->name);
            $editlink = '<a href="'.$CFG->wwwroot.'/mod/webinar/customfield.php?id='.$field->id.'">'.
                '<img class="iconsmall" src="'.$CFG->wwwroot.'/pix/t/edit.gif" alt="'.get_string('edit').'" /></a>';
            $deletelink = '<a href="'.$CFG->wwwroot.'/mod/webinar/customfield.php?id='.$field->id.'&amp;d=1&amp;sesskey='.$USER->sesskey.'">'.
                '<img class="iconsmall" src="'.$CFG->wwwroot.'/pix/t/delete.gif" alt="'.get_string('delete').'" /></a>';
            $table->data[] = array($fieldname, $editlink, $deletelink);
        }
        //return print_table($table, true);
		return html_writer::table($table, true);
    }

    return get_string('nocustomfields', 'webinar');
}

function webinar_update_trainers($sessionid, $form) {

    // If we recieved bad data
    if (!is_array($form)) {
        return false;
    }

    // Load current trainers
    $old_trainers = webinar_get_trainers($sessionid);

    //begin_sql();

    // Loop through form data and add any new trainers
    foreach ($form as $roleid => $trainers) {

        // Loop through trainers in this role
        foreach ($trainers as $trainer) {

            if (!$trainer) {
                continue;
            }

            // If the trainer doesn't exist already, create it
            if (!isset($old_trainers[$roleid][$trainer])) {

                $newtrainer = new object();
                $newtrainer->userid = $trainer;
                $newtrainer->roleid = $roleid;
                $newtrainer->sessionid = $sessionid;

                if (!$DB->insert_record('webinar_session_roles', $newtrainer)) {
                    error('Could not save new webinar session trainer');
                    //rollback_sql();
                    return false;
                }
            }

            unset($old_trainers[$roleid][$trainer]);
        }
    }

    // Loop through what is left of old trainers, and remove
    // (as they have been deselected)
    if ($old_trainers) {
        foreach ($old_trainers as $roleid => $trainers) {
            // If no trainers left
            if (empty($trainers)) {
                continue;
            }

            // Delete any remaining trainers
            foreach ($trainers as $trainer) {
                if (!$DB->delete_records('webinar_session_roles', 'sessionid', $sessionid, 'roleid', $roleid, 'userid', $trainer->id)) {
                    error('Could not delete a webinar session trainer');
                    //rollback_sql();
                    return false;
                }
            }
        }
    }

    //commit_sql();

    return true;
}


/**
 * Return array of trainer roles configured for webinar
 *
 * @return  array
 */
function webinar_get_trainer_roles() {
    global $CFG;

    // Check that roles have been selected
    if (empty($CFG->webinar_sessionroles)) {
        return false;
    }

    // Parse roles
    $cleanroles = clean_param($CFG->webinar_sessionroles, PARAM_SEQUENCE);

    // Load role names
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {$CFG->prefix}role r
        WHERE
            r.id IN ({$cleanroles})
        AND r.id <> 0
    ");

    // Return roles and names
    if (!$rolenames) {
        return array();
    }

    return $rolenames;
}


/**
 * Get all trainers associated with a session, optionally
 * restricted to a certain roleid
 *
 * If a roleid is not specified, will return a multi-dimensional
 * array keyed by roleids, with an array of the chosen roles
 * for each role
 *
 * @param   integer     $sessionid
 * @param   integer     $roleid (optional)
 * @return  array
 */
function webinar_get_trainers($sessionid, $roleid = null) {
    global $CFG, $DB;

    $rs = $DB->get_recordset_sql("
        SELECT
            u.id,
            u.firstname,
            u.lastname,
            r.roleid
        FROM
            {$CFG->prefix}webinar_session_roles r
        LEFT JOIN
            {$CFG->prefix}user u
         ON u.id = r.userid
        WHERE
            r.sessionid = {$sessionid}
        ".
        ($roleid ? "AND r.roleid = {$roleid}" : '')
    );

    if (!$rs) {
        return false;
    }

    $return = array();
    while ($record = rs_fetch_next_record($rs)) {
        // Create new array for this role
        if (!isset($return[$record->roleid])) {
            $return[$record->roleid] = array();
        }

        $return[$record->roleid][$record->id] = $record;
    }

    rs_close($rs);

    // If we are only after one roleid
    if ($roleid) {
        if (empty($return[$roleid])) {
            return false;
        }

        return $return[$roleid];
    }

    // If we are after all roles
    if (empty($return)) {
        return false;
    }

    return $return;
}

/**
 * Determines whether an activity requires the user to have a manager (either for
 * manager approval or to send notices to the manager)
 *
 * @param  object $webinar A database fieldset object for the webinar activity
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function webinar_manager_needed($webinar){
    return $webinar->approvalreqd
        || $webinar->confirmationinstrmngr
        || $webinar->reminderinstrmngr
        || $webinar->cancellationinstrmngr;
}

/**
 * Display the list of site notices in the site-wide settings page
 */
function webinar_list_of_sitenotices()
{
    global $CFG, $USER, $DB;

    if ($notices = $DB->get_records('webinar_notice', NULL, 'name', 'id, name')) {
        $table = new html_table();
        $table->width = '50%';
        $table->tablealign = 'left';
        $table->data = array();
        $table->size = array('100%');
        foreach ($notices as $notice) {
            $noticename = format_string($notice->name);
            $editlink = '<a href="'.$CFG->wwwroot.'/mod/webinar/sitenotice.php?id='.$notice->id.'">'.
                '<img class="iconsmall" src="'.$CFG->wwwroot.'/pix/t/t/edit.gif" alt="'.get_string('edit').'" /></a>';
            $deletelink = '<a href="'.$CFG->wwwroot.'/mod/webinar/sitenotice.php?id='.$notice->id.'&amp;d=1&amp;sesskey='.$USER->sesskey.'">'.
                '<img class="iconsmall" src="'.$CFG->wwwroot.'/pix/t/t/delete.gif" alt="'.get_string('delete').'" /></a>';
            $table->data[] = array($noticename, $editlink, $deletelink);
        }
        return html_writer::table($table, true);
    }

    return get_string('nositenotices', 'webinar');
}

/**
 * Add formslib fields for all custom fields defined site-wide.
 * (used by the session add/edit page and the site notices)
 */
function webinar_add_customfields_to_form(&$mform, $customfields, $alloptional=false)
{
    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";

        $options = array();
        if (!$field->required) {
            $options[''] = get_string('none');
        }
        foreach (explode(WEBINAR_CUSTOMFIELD_DELIMITTER, $field->possiblevalues) as $value) {
            $v = trim($value);
            if (!empty($v)) {
                $options[$v] = $v;
            }
        }

        switch ($field->type) {
        case WEBINAR_CUSTOMFIELD_TYPE_TEXT:
            $mform->addElement('text', $fieldname, $field->name);
            break;
        case WEBINAR_CUSTOMFIELD_TYPE_SELECT:
            $mform->addElement('select', $fieldname, $field->name, $options);
            break;
        case WEBINAR_CUSTOMFIELD_TYPE_MULTISELECT:
            $select = &$mform->addElement('select', $fieldname, $field->name, $options);
            $select->setMultiple(true);
            break;
        default:
            error_log("webinar: invalid field type for custom field ID $field->id");
            continue;
        }

        $mform->setType($fieldname, PARAM_TEXT);
        $mform->setDefault($fieldname, $field->defaultvalue);
        if ($field->required and !$alloptional) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }
}

function webinar_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:          return true;
        default: return null;
    }
}