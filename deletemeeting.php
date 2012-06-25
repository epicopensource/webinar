<?php

/* Calls to Adobe Connect module and deletes a given meeting / webinar session */

function delete_meeting($webinar, $session_info) {

	//Step 1 - get session value
	$url = $webinar->sitexmlapiurl . "?action=common-info";
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	$session = $xml->common->cookie;

	//Step 2 - login
	$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);

	//Step 3 - delete the meeting room
	$scoid = $session_info->scoid;
	
	$url = $webinar->sitexmlapiurl . "?action=sco-delete&sco-id=" . $scoid . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	//$xml = new SimpleXMLElement($xmlstr);

}