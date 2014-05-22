<?php

/* Calls to Adobe Connect module and deletes a given meeting / webinar session */

function delete_meeting($webinar, $session_info) {

	/*
	//Step 1 - get session value
	$url = $webinar->sitexmlapiurl . "?action=common-info";
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	$session = $xml->common->cookie;

	//Step 2 - login
	$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	*/

	$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword;

	$ch=curl_init($url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	$response = curl_exec($ch);
	curl_close($ch);

	$breeze_session_first_strip = strstr($response, 'BREEZESESSION');
	$breeze_session_second_strip = strstr($breeze_session_first_strip, ';', true);
	$breeze_session = str_replace('BREEZESESSION=', '', $breeze_session_second_strip);

	// Create a stream for HTTP headers, including the BREEZESESSION cookie
	$opts = array(
			  'http'=>array(
			    'method'=>"GET",
			    'header'=>"Cookie: " . $breeze_session_second_strip . "\r\n"
			  )
	);

	$context = stream_context_create($opts);

	//Step 3 - delete the meeting room
	$scoid = $session_info->scoid;
	
	$url = $webinar->sitexmlapiurl . "?action=sco-delete&sco-id=" . $scoid; // . "&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	//$xml = new SimpleXMLElement($xmlstr);

}