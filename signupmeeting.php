<?php

/* Calls to Adobe Connect module and signs a user up to a meeting / webinar session */

function signup_meeting($webinar, $session_info, $user) {

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

	//Step 3 - as admin user, check if user email exists on Adobe Connect already - if not, add a new user to the system, otherwise get their permission ID
	
	$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $user->email . "&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);

	if ($xml->{'principal-list'}->principal) {
	
		//User email address has been matched on Adobe Connect - get back their principal ID
		foreach($xml->{'principal-list'}->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}
	else {
		//User email address is not registered yet with Adobe Connect - add them and get back the principal ID
		$url = $webinar->sitexmlapiurl . "?action=principal-update&first-name=" . str_replace(' ', '%20', $user->firstname) . "&last-name=" . str_replace(' ', '%20', $user->lastname) . "&login=" . $user->email . 
			"&password=" . $webinar->adminpassword . "&type=user&send-email=false&has-children=0&email=" . $user->email . "&session=" . $session;
		$xmlstr = file_get_contents($url);
		$xml = new SimpleXMLElement($xmlstr);

		foreach($xml->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}

	//Step 4 - add user as a meeting participant using the principalID obtained above
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $session_info->scoid . "&permission-id=view&session=" . $session;

	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);

}
