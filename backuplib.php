<?php
  //------------------------------------------------------------------
  // This is the "graphical" structure of the Webinar module:
  //
  //          webinar                  webinar_sessions
  //         (CL, pk->id)-------------(CL, pk->id, fk->webinar)
  //                                          |  |  |  |
  //                                          |  |  |  |
  //            webinar_signups------------+  |  |  |
  //        (UL, pk->id, fk->sessionid)          |  |  |
  //                     |                       |  |  |
  //         webinar_signups_status           |  |  |
  //         (UL, pk->id, fk->signupid)          |  |  |
  //                                             |  |  |
  //                                             |  |  |
  //         webinar_session_roles------------+  |  |
  //        (UL, pk->id, fk->sessionid)             |  |
  //                                                |  |
  //                                                |  |
  //     webinar_session_field                   |  |
  //          (SL, pk->id)  |                       |  |
  //                        |                       |  |
  //             webinar_session_data------------+  |
  //    (CL, pk->id, fk->sessionid, fk->fieldid)       |
  //                                                   |
  //                                    webinar_sessions_dates
  //                                    (CL, pk->id, fk->session)
  //
  // Meaning: pk->primary key field of the table
  //          fk->foreign key to link with parent
  //          SL->system level info
  //          CL->course level info
  //          UL->user level info
  //
  //------------------------------------------------------------------

/**
 * API function called by the Moodle backup system to backup all of
 * the webinar activities
 */
function webinar_backup_mods($bf, $preferences)
{
    $status = true;

    $webinars = get_records('webinar', 'course', $preferences->backup_course, 'id');
    if ($webinars) {
        foreach ($webinars as $webinar) {
            if (backup_mod_selected($preferences, 'webinar', $webinar->id)) {
                $status &= webinar_backup_one_mod($bf, $preferences, $webinar);
            }
        }
    }

    //$status &= webinar_backup_session_field($bf, $preferences); // DISABLED

    return $status;
}

/**
 * Backup the webinar_session_field table (all custom session fields)
 *
 * NOTE: NOT CURRENTLY BACKED UP!
 */
function webinar_backup_session_field($bf, $preferences)
{
    $status = true;

    $sessionfields = get_records('webinar_session_field');
    if (!$sessionfields) {
        return $status;
    }

    $status = fwrite($bf, start_tag('SESSIONFIELDS', 3, true)) > 0;
    foreach ($sessionfields as $field) {
        $status &= fwrite($bf, start_tag('SESSIONFIELD', 4, true)) > 0;

        // webinar_session_field table
        $status &= fwrite($bf, full_tag('ID', 5, false, $field->id)) > 0;
        $status &= fwrite($bf, full_tag('NAME', 5, false, $field->name)) > 0;
        $status &= fwrite($bf, full_tag('SHORTNAME', 5, false, $field->shortname)) > 0;
        $status &= fwrite($bf, full_tag('TYPE', 5, false, $field->type)) > 0;
        $status &= fwrite($bf, full_tag('POSSIBLEVALUES', 5, false, $field->possiblevalues)) > 0;
        $status &= fwrite($bf, full_tag('REQUIRED', 5, false, $field->required)) > 0;
        $status &= fwrite($bf, full_tag('DEFAULTVALUE', 5, false, $field->defaultvalue)) > 0;
        $status &= fwrite($bf, full_tag('ISFILTER', 5, false, $field->isfilter)) > 0;
        $status &= fwrite($bf, full_tag('SHOWINSUMMARY', 5, false, $field->showinsummary)) > 0;

        $status &= fwrite($bf, end_tag('SESSIONFIELD', 4, true)) > 0;
    }
    $status = fwrite($bf, end_tag('SESSIONFIELDS', 3, true)) > 0;

    return $status;
}

/**
 * API function called by the Moodle backup system to backup a single
 * webinar activity
 */
function webinar_backup_one_mod($bf, $preferences, $webinar)
{
    if (is_numeric($webinar)) {
        $webinar = get_record('webinar', 'id', $webinar);
    }

    $status = fwrite($bf, start_tag('MOD', 3, true)) > 0;

    // webinar table
    $status &= fwrite($bf, full_tag('ID', 4, false, $webinar->id)) > 0;
    $status &= fwrite($bf, full_tag('MODTYPE', 4, false, 'webinar')) > 0;
    $status &= fwrite($bf, full_tag('NAME', 4, false, $webinar->name)) > 0;
    $status &= fwrite($bf, full_tag('THIRDPARTY', 4, false, $webinar->thirdparty)) > 0;
    $status &= fwrite($bf, full_tag('DISPLAY', 4, false, $webinar->display)) > 0;
    $status &= fwrite($bf, full_tag('CONFIRMATIONSUBJECT', 4, false, $webinar->confirmationsubject)) > 0;
    $status &= fwrite($bf, full_tag('CONFIRMATIONINSTRMNGR', 4, false, $webinar->confirmationinstrmngr)) > 0;
    $status &= fwrite($bf, full_tag('CONFIRMATIONMESSAGE', 4, false, $webinar->confirmationmessage)) > 0;
    $status &= fwrite($bf, full_tag('WAITLISTEDSUBJECT', 4, false, $webinar->waitlistedsubject)) > 0;
    $status &= fwrite($bf, full_tag('WAITLISTEDMESSAGE', 4, false, $webinar->waitlistedmessage)) > 0;
    $status &= fwrite($bf, full_tag('CANCELLATIONSUBJECT', 4, false, $webinar->cancellationsubject)) > 0;
    $status &= fwrite($bf, full_tag('CANCELLATIONINSTRMNGR', 4, false, $webinar->cancellationinstrmngr)) > 0;
    $status &= fwrite($bf, full_tag('CANCELLATIONMESSAGE', 4, false, $webinar->cancellationmessage)) > 0;
    $status &= fwrite($bf, full_tag('REMINDERSUBJECT', 4, false, $webinar->remindersubject)) > 0;
    $status &= fwrite($bf, full_tag('REMINDERINSTRMNGR', 4, false, $webinar->reminderinstrmngr)) > 0;
    $status &= fwrite($bf, full_tag('REMINDERMESSAGE', 4, false, $webinar->remindermessage)) > 0;
    $status &= fwrite($bf, full_tag('REMINDERPERIOD', 4, false, $webinar->reminderperiod)) > 0;
    $status &= fwrite($bf, full_tag('TIMECREATED', 4, false, $webinar->timecreated)) > 0;
    $status &= fwrite($bf, full_tag('TIMEMODIFIED', 4, false, $webinar->timemodified)) > 0;

    $status &= backup_webinar_sessions($bf, $webinar->id);

    if (backup_userdata_selected($preferences, 'webinar', $webinar->id)) {
        $status &= backup_webinar_submissions($bf, $webinar->id);
    }

    $status &= fwrite($bf, end_tag('MOD', 3 , true)) > 0;
    return $status;
}

/**
 * Backup the webinar_sessions table entries for a given webinar
 * activity
 */
function backup_webinar_sessions($bf, $webinarid)
{
    $status = true;

    $sessions = get_records('webinar_sessions', 'webinar', $webinarid, 'id');
    if (!$sessions) {
        return $status;
    }

    $status &= fwrite($bf, start_tag('SESSIONS', 4, true)) > 0;
    foreach ($sessions as $session) {
        $status &= fwrite($bf, start_tag('SESSION', 5, true)) > 0;

        // webinar_sessions table
        $status &= fwrite($bf, full_tag('ID', 6, false, $session->id)) > 0;
        $status &= fwrite($bf, full_tag('CAPACITY', 6, false, $session->capacity)) > 0;
        $status &= fwrite($bf, full_tag('PRESENTER', 6, false, $session->presenter)) > 0;
        $status &= fwrite($bf, full_tag('SCOID', 6, false, $session->scoid)) > 0;
        $status &= fwrite($bf, full_tag('URLPATH', 6, false, $session->urlpath)) > 0;
        $status &= fwrite($bf, full_tag('TIMECREATED', 6, false, $session->timecreated)) > 0;
        $status &= fwrite($bf, full_tag('TIMEMODIFIED', 6, false, $session->timemodified)) > 0;

        $status &= backup_webinar_sessions_dates($bf, $session->id);

        $status &= backup_webinar_session_data($bf, $session->id);

        $status &= fwrite($bf, end_tag('SESSION', 5, true)) > 0;
    }
    $status &= fwrite($bf, end_tag('SESSIONS', 4, true)) > 0;

    return $status;
}

/**
 * Backup the webinar_sessions_dates table entries for a given
 * webinar session
 */
function backup_webinar_sessions_dates($bf, $sessionid)
{
    $status = true;

    $dates = get_records('webinar_sessions_dates', 'sessionid', $sessionid, 'id');
    if (!$dates) {
        return $status;
    }

    $status &= fwrite($bf, start_tag('DATES', 6, true)) > 0;
    foreach ($dates as $date) {
        $status &= fwrite($bf, start_tag('DATE', 7, true)) > 0;

        // webinar_sessions_dates table
        $status &= fwrite($bf, full_tag('ID', 8, false, $date->id)) > 0;
        $status &= fwrite($bf, full_tag('TIMESTART', 8, false, $date->timestart)) > 0;
        $status &= fwrite($bf, full_tag('TIMEFINISH', 8, false, $date->timefinish)) > 0;

        $status &= fwrite($bf, end_tag('DATE', 7, true)) > 0;
    }
    $status &= fwrite($bf, end_tag('DATES', 6, true)) > 0;

    return $status;
}

/**
 * Backup the webinar_session_data table entries for a given
 * webinar session
 *
 * NOTE: we keep track of the field shortname so that we can lookup
 * the fieldid when we restore. Custom fields need to be manually
 * recreated on the destination site.
 */
function backup_webinar_session_data($bf, $sessionid)
{
    global $CFG;
    $status = true;

    $data = get_records_sql("SELECT d.id, f.shortname, d.sessionid, d.data
                               FROM {$CFG->prefix}webinar_session_field f
                               JOIN {$CFG->prefix}webinar_session_data d ON f.id = d.fieldid
                              WHERE d.sessionid = $sessionid
                           ORDER BY d.id");
    if (!$data) {
        return $status;
    }

    $status &= fwrite($bf, start_tag('DATA', 6, true)) > 0;
    foreach ($data as $datum) {
        $status &= fwrite($bf, start_tag('DATUM', 7, true)) > 0;

        // webinar_sessions_dates table
        $status &= fwrite($bf, full_tag('ID', 8, false, $datum->id)) > 0;
        $status &= fwrite($bf, full_tag('FIELDID', 8, false, $datum->fieldid)) > 0;
        $status &= fwrite($bf, full_tag('SESSIONID', 8, false, $datum->sessionid)) > 0;
        $status &= fwrite($bf, full_tag('DATA', 8, false, $datum->data)) > 0;

        $status &= fwrite($bf, end_tag('DATUM', 7, true)) > 0;
    }
    $status &= fwrite($bf, end_tag('DATA', 6, true)) > 0;

    return $status;
}

/**
 * Backup the webinar_signups table entries for a given
 * webinar activity
 */
function backup_webinar_submissions($bf, $webinarid)
{
    global $CFG;

    $status = true;

    $signups = get_records_sql(
        "
            SELECT
                ss.id AS statusid,
                s.id,
                s.sessionid,
                s.userid,
                s.mailedreminder,
                s.notificationtype,
                ss.statuscode,
                ss.superceded,
                ss.grade,
                ss.note,
                ss.advice,
                ss.createdby,
                ss.timecreated
            FROM
                {$CFG->prefix}webinar_signups_status ss
            INNER JOIN
                {$CFG->prefix}webinar_signups s
             ON ss.signupid = s.id
            INNER JOIN
                {$CFG->prefix}webinar_sessions sess
             ON s.sessionid = sess.id
            WHERE
                sess.webinar = {$webinarid}
        "
    );

    if (!$signups) {
        return $status;
    }

    $status &= fwrite($bf, start_tag('SIGNUPS', 4, true)) > 0;

    $signupid = null;
    foreach ($signups as $signup) {

        if ($signup->id != $signupid) {
            // If this isn't the first signup tag, close the previous one
            if ($signupid !== null) {
                $status &= fwrite($bf, end_tag('SIGNUPS_STATUS', 6, true)) > 0;
                $status &= fwrite($bf, end_tag('SIGNUP', 5, true)) > 0;
            }

            $status &= fwrite($bf, start_tag('SIGNUP', 5, true)) > 0;
            $status &= fwrite($bf, full_tag('ID', 6, false, $signup->id)) > 0;
            $status &= fwrite($bf, full_tag('SESSIONID', 6, false, $signup->sessionid)) > 0;
            $status &= fwrite($bf, full_tag('USERID', 6, false, $signup->userid)) > 0;
            $status &= fwrite($bf, full_tag('MAILEDREMINDER', 6, false, $signup->mailedreminder)) > 0;
            $status &= fwrite($bf, full_tag('NOTIFICATIONTYPE', 6, false, $signup->notificationtype)) > 0;
            $status &= fwrite($bf, start_tag('SIGNUPS_STATUS', 6, true)) > 0;

            $signupid = $signup->id;
        }

        $status &= fwrite($bf, start_tag('STATUS', 7, true)) > 0;
        $status &= fwrite($bf, full_tag('SIGNUPID', 8, false, $signup->id)) > 0;
        $status &= fwrite($bf, full_tag('STATUSCODE', 8, false, $signup->statuscode)) > 0;
        $status &= fwrite($bf, full_tag('SUPERCEDED', 8, false, $signup->superceded)) > 0;
        $status &= fwrite($bf, full_tag('GRADE', 8, false, $signup->grade)) > 0;
        $status &= fwrite($bf, full_tag('NOTE', 8, false, $signup->note)) > 0;
        $status &= fwrite($bf, full_tag('ADVICE', 8, false, $signup->advice)) > 0;
        $status &= fwrite($bf, full_tag('CREATEDBY', 8, false, $signup->createdby)) > 0;
        $status &= fwrite($bf, full_tag('TIMECREATED', 8, false, $signup->timecreated)) > 0;
        $status &= fwrite($bf, end_tag('STATUS', 7, true)) > 0;
    }

    $status &= fwrite($bf, end_tag('SIGNUPS_STATUS', 6, true)) > 0;
    $status &= fwrite($bf, end_tag('SIGNUP', 5, true)) > 0;
    $status &= fwrite($bf, end_tag('SIGNUPS', 4, true)) > 0;

    return $status;
}

/**
 * API function called by the Moodle backup system to describe the
 * contents of the given backup instances
 */
function webinar_check_backup_mods_instances($instance, $backup_unique_code)
{
    global $CFG;

    $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
    $info[$instance->id.'0'][1] = '';

    $info[$instance->id.'1'][0] = get_string('sessions', 'webinar');
    $info[$instance->id.'1'][1] = count_records('webinar_sessions', 'webinar', $instance->id);

    if (!empty($instance->userdata)) {
        $info[$instance->id.'2'][0] = get_string('signups', 'webinar');
        $info[$instance->id.'2'][1] = count_records_sql(
            "
                SELECT
                    COUNT(s.id)
                FROM
                    {$CFG->prefix}webinar_signups s
                INNER JOIN
                    {$CFG->prefix}webinar_sessions sess
                 ON sess.id = s.sessionid
                WHERE
                    sess.webinar = {$instance->id}
            "
        );
    }

    return $info;
}

/**
 * API function called by the Moodle backup system to describe the
 * contents of backup instances for the given course
 */
function webinar_check_backup_mods($course, $user_data=false, $backup_unique_code, $instances=null)
{
    global $CFG;

    if (!empty($instances) && is_array($instances) && count($instances)) {
        $info = array();
        foreach ($instances as $id => $instance) {
            $info += webinar_check_backup_mods_instances($instance, $backup_unique_code);
        }
        return $info;
    }

    $info[0][0] = get_string('modulenameplural', 'webinar');
    $info[0][1] = count_records('webinar', 'course', $course);

    $info[1][0] = get_string('sessions', 'webinar');
    $info[1][1] = count_records_sql("SELECT COUNT(*)
                                         FROM {$CFG->prefix}webinar f,
                                              {$CFG->prefix}webinar_sessions s
                                         WHERE f.id = s.webinar
                                           AND f.course = $course");

    if ($user_data) {
        $info[2][0] = get_string('signups', 'webinar');
        $info[2][1] = count_records_sql(
            "
                SELECT
                    COUNT(s.id)
                FROM
                    {$CFG->prefix}webinar_signups s
                INNER JOIN
                    {$CFG->prefix}webinar_sessions sess
                 ON sess.id = s.sessionid
                INNER JOIN
                    {$CFG->prefix}webinar f
                 ON sess.webinar = f.id
                WHERE
                    f.course = {$course}
            "
        );
    }

    return $info;
}
