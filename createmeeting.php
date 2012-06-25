<?php

/* Calls to Adobe Connect module and create a meeting / webinar session */

function create_meeting($webinar, $fromform, $date, $presenter_details) {

	//Step 1 - get session value
	$url = $webinar->sitexmlapiurl . "?action=common-info";
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	$session = $xml->common->cookie;

	foreach($xml->common->account->attributes() as $key => $val) {
		if($key == 'account-id') {
			$account_id = $val;
		}
	}

	//Step 2 - login
	$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);

	//Step 3 - get sco ID of 'my-meetings'
	$url = $webinar->sitexmlapiurl . "?action=sco-shortcuts&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);
	
	foreach ($xml->shortcuts->sco as $sco) {
		//if ($sco->attributes()->type == 'meetings') {
		if ($sco->attributes()->type == 'meetings') {
			foreach($sco->attributes() as $key => $val) {
				if($key == 'sco-id') {
					$scoid = $val;
				}
			}
		}
	}
	
	//Step 4 - create the meeting room
	//Format the Moodle dates to update Adobe Connect
	$datebegin = date('Y-m-d', $date->timestart);
	$timebegin = date('H:i', $date->timestart);
	$datetimebegin = $datebegin . "T" . $timebegin;
	
	$dateend = date('Y-m-d', $date->timefinish);
	$timeend = date('H:i', $date->timefinish);
	$datetimeend = $dateend . "T" . $timeend;
	
	$meetingname = str_replace(' ', '%20', $webinar->name . ' ' . $datebegin . ' ' . $timebegin);
	
	$url = $webinar->sitexmlapiurl . "?action=sco-update&type=meeting&name=" . $meetingname . 
				"&summary=xyz&folder-id=" . $scoid . "&date-begin=" . $datetimebegin . "&date-end=" . $datetimeend . "&url-path=&session=" . $session;

	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);
	
	$urlpath = $xml->sco->{'url-path'};

	foreach ($xml->sco as $sco) {
		foreach($sco->attributes() as $key => $val) {
			if($key == 'sco-id') {
				$scoid = $val;
			}
		}
	}

	//Step 5 - make the meeting public
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&acl-id=" . $scoid . "&principal-id=public-access&permission-id=view-hidden&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);

	//Step 6 - add a presenter - pass the presenter email address, check if it exists on Adobe Connect already - if not, add a new user to the system, otherwise get their permission ID
	$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $presenter_details->email . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);

	if ($xml->{'principal-list'}->principal) {
	
		//Presenter email address has been matched on Adobe Connect - get back their principal ID
		foreach($xml->{'principal-list'}->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}
	else {
		//Presenter email address is not registered yet with Adobe Connect - add them and get back the principal ID
		$url = $webinar->sitexmlapiurl . "?action=principal-update&first-name=" . $presenter_details->firstname . "&last-name=" . $presenter_details->lastname . "&login=" . $presenter_details->email . 
			"&password=test&type=user&send-email=false&has-children=0&email=" . $presenter_details->email . "&session=" . $session;
		$xmlstr = file_get_contents($url);
		$xml = new SimpleXMLElement($xmlstr);

		foreach($xml->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}
	
	//take the presenter user's principal ID and assign them as presenter of the session
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $scoid . "&permission-id=host&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);

	$webinardetails = new object();
    $webinardetails->scoid = $scoid;
    $webinardetails->urlpath = $urlpath;
	return $webinardetails;
}
