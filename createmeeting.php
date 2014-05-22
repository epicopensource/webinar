<?php

/* Calls to Adobe Connect module and create a meeting / webinar session */

function create_meeting($webinar, $fromform, $date, $presenter_details) {

	//Step 1 - get session value
	/*

	$url = $webinar->sitexmlapiurl . "?action=common-info";
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	$session = $xml->common->cookie;

	foreach($xml->common->account->attributes() as $key => $val) {
		if($key == 'account-id') {
			$account_id = $val;
		}
	}
	*/

	//Step 2 - login
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
	
	/* JoeB changes 25/10/2012 - capture the number of hosts allowed for this Adobe Connect account */
	$url = $webinar->sitexmlapiurl . "?action=principal-list"; //&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);

	//print_r($xml);

	$p_array_count = 0;
	foreach($xml->{'principal-list'}->principal as $principal_array) {

		$principal = $principal_array; //$principal_array[$p_array_count];
		
		foreach($principal->name as $key => $val) {
			if ($val == 'Meeting Hosts') {
				foreach($principal->attributes() as $akey => $aval) {

					if($akey == 'principal-id') {
						$principal_id = $aval;
					}
				}
			}
		}
		$p_array_count++;
	}
	
	$url = $webinar->sitexmlapiurl . "?action=report-quotas"; //&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);
	
	$q_array_count = 0;
	$found_limit = false;
	foreach($xml->{'report-quotas'}->quota as $quota_array) {
	
		$quota = $quota_array; //$quota_array[$q_array_count];
	
		foreach($quota->attributes() as $key => $val) {
			if($key == 'acl-id') {
				
				if((int)$val == (int)$principal_id) {
					$found_limit = true;
				}
			}
			if($found_limit) {
				if($key == 'limit') {
					$hosts_limit = $val;
					break;
				}
			}
		}
		$q_array_count++;
	}
	/* end JoeB changes */
	
	$url = $webinar->sitexmlapiurl . "?action=sco-shortcuts"; //&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);
	
	foreach ($xml->shortcuts->sco as $sco) {
	
		if ((int)$hosts_limit == 1) {
			$meeting_search = 'my-meetings'; //'my-meetings' for single host licenses
		}
		else {
			$meeting_search = 'meetings'; //'meetings' for multihost accounts
		}
	
		if ($sco->attributes()->type == $meeting_search) {  
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
				"&summary=summary&folder-id=" . $scoid . "&date-begin=" . $datetimebegin . "&date-end=" . $datetimeend . "&url-path="; //&session=" . $session;

	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);
	
	$urlpath = $xml->sco->{'url-path'};

	foreach ($xml->sco as $sco) {
		foreach($sco->attributes() as $key => $val) {
			if($key == 'sco-id') {
				$scoid = $val;
			}
		}
	}

	//Step 5 - make the meeting public
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&acl-id=" . $scoid . "&principal-id=public-access&permission-id=view-hidden"; //&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);

	//Step 6 - add a presenter - pass the presenter email address, check if it exists on Adobe Connect already - if not, add a new user to the system, otherwise get their permission ID
	$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $presenter_details->email; // . "&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
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
		$url = $webinar->sitexmlapiurl . "?action=principal-update&first-name=" . str_replace(' ', '%20', $presenter_details->firstname) . "&last-name=" . str_replace(' ', '%20', $presenter_details->lastname) . "&login=" . $presenter_details->email . 
			"&password=" . $webinar->adminpassword . "&type=user&send-email=false&has-children=0&email=" . $presenter_details->email; // . "&session=" . $session;
		$xmlstr = file_get_contents($url, false, $context);
		$xml = new SimpleXMLElement($xmlstr);

		foreach($xml->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}
	
	//take the presenter user's principal ID and assign them as presenter of the session
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $scoid . "&permission-id=host"; //&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);

	$webinardetails = new object();
	
	//JoeB - changes for Moodle 2.3, explicitly cast the scoid and urlpath simplexml objects
    $webinardetails->scoid = (string)$scoid[0];
    $webinardetails->urlpath = (string)$urlpath[0];
	return $webinardetails;
}
