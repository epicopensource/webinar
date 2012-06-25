<?php

/* Calls to Adobe Connect module and signs a user up to a meeting / webinar session */

function cancelsignup_meeting($webinar, $session_info, $user) {

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

	//Step 3 - get list of users, and get the principal id which matches your user?
	$url = $webinar->sitexmlapiurl . "?action=principal-list&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	
	foreach($xml->{'principal-list'}->principal as $principal) {
		if($principal->email == $user->email) {
			foreach($principal->attributes() as $key => $val) {
				if($key == 'principal-id') {
					$principal_id = $val;
				}
			}
		}
	}

	//Step 4 - remove user as a meeting participant using the principalID obtained above
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $session_info->scoid . "&permission-id=remove&session=" . $session;
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	//print_r($xml);

}
