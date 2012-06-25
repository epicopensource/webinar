<?php

require_once "$CFG->dirroot/mod/webinar/lib.php";

$settings->add(new admin_setting_configtext('webinar/sitexmlapiurl', 
												get_string('setting:sitexmlapiurl_caption', 'webinar'), 
												get_string('setting:sitexmlapiurl', 'webinar'), 
												'', 
												PARAM_TEXT));													

$settings->add(new admin_setting_configtext('webinar/adminemail', 
												get_string('setting:adminemail_caption', 'webinar'), 
												get_string('setting:adminemail', 'webinar'), 
												'', 
												PARAM_TEXT));		

$settings->add(new admin_setting_configtext('webinar/adminpassword', 
												get_string('setting:adminpassword_caption', 'webinar'), 
												get_string('setting:adminpassword', 'webinar'), 
												'', 
												PARAM_TEXT));	



