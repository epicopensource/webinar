<?php

// This file keeps track of upgrades to
// the webinar module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_webinar_upgrade($oldversion=0) {

    global $CFG, $USER, $db;

    $result = true;

	/*
    if ($result && $oldversion < 2010100400) {
        // Remove unused mailed field
        $table = new XMLDBTable('webinar_signups_status');
        $field = new XMLDBField('mailed');
        $result = $result && drop_field($table, $field, false, true);
    }
	*/

    return $result;
}
