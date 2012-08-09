<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Capability definitions for the webinar module.
 *
 * @package    mod
 * @subpackage webinar
 * Webinar module for Moodle - Copyright (C) 2011 Epic (http://www.epic.co.uk/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$capabilities = array(

    // Ability to see that the activity exists, and the basic information
    // about its sessions
    'mod/webinar:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to signup for a session
    'mod/webinar:signup' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to see activities for which no sessions have been added
    'mod/webinar:viewemptyactivities' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to see how many people have signed up for a session
    'mod/webinar:viewattendees' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to take attendance
    'mod/webinar:takeattendance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to add attendees to a session
    'mod/webinar:addattendees' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to remove attendees from a session
    'mod/webinar:removeattendees' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to add, edit, copy and delete a session
    'mod/webinar:editsessions' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // View session cancellations
    'mod/webinar:viewcancellations' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
    ),

    // Ability to overbook a session by signing up for it
    // Users with mod/webinar:addattendees can also overbook
    'mod/webinar:overbook' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
			'guest' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
    ),
	
	// JoeB addition for Moodle 2.3 - define the addinstance capability
	'mod/webinar:addinstance' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
		'clonepermissionsfrom' => 'moodle/course:manageactivities'
	),

);

?>
