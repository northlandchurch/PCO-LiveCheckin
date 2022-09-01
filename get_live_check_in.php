<?php
include_once 'config.php';

if (array_key_exists('step', $_GET))
{
	$step = $_GET['step'];
	$eventtimeid = $_GET['eventtimeid'];
	$eventid = $_GET['eventid'];
	$eventperiodid = $_GET['eventperiodid'];
}
else
{
	$step = 'search';
	// CM Sunday Morning 05/22/2018
	$eventtimeid = 5819222;		
	$eventid = 149098;		
	$eventperiodid = 4398376;		
/*
$eventid = 159558;		// CM Daytime Childcare
*/
}
	

$URL = "https://api.planningcenteronline.com/";

$ch = curl_init(); // create cURL resource
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ;
curl_setopt($ch, CURLOPT_USERPWD, NACD_API_PW); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the transfer as a string
// curl_setopt($ch, CURLOPT_VERBOSE, true); // for debugging purposes


$data = array();

if ($step == 'init')
{
	$data['data'] = array();
	echo json_encode(json_encode($data));
	exit;
}

$checkins = array();


try 
{
	/////////////////////////////////////////////////////////////////////////////////
	// 	Retrieve locations for this event and Build
	/////////////////////////////////////////////////////////////////////////////////
	$srv = "check_ins/v2/events/" . $eventid . "/locations";
	$param = "?order=kind,name&include=parent";

	$api = $URL . $srv . $param;

	curl_setopt($ch, CURLOPT_URL, $api); // set cURL url
	$curlResult = curl_exec($ch); // $curlResult contains the JSON-encoded string sent from API

	////////////////////////////////////////////////////////////////////////
	// Verify status code from PCO API
	////////////////////////////////////////////////////////////////////////
	$received_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
	switch($received_status_code) {
	case 200:
		// expected, continue
		break;
	default:
		// unexpected, throw error
		throw new Exception("Unexpected Status Code in Request to PCO API: " . $received_status_code, 1);
	}

	
//	var_dump($curlResult);
	$counter = 0;
	$next = true;

	// Retrieve all locations for the event
	do {
		$curlResult = json_decode($curlResult); // Convert JSON-encoded string into PHP object
		$links = $curlResult->links;
		$locations = $curlResult->data;
		$includeds = $curlResult->included;

		foreach ($locations as $location)
		{
			$checkin = array();
		
			// Location ID
			$checkin['id'] = $location->id;
			if ($location->attributes->opened)
			{
				$checkin['open'] = 'Open';
			}
			else
			{
				$checkin['open'] = 'Closed';
			}
			
			// Location type
			$checkin['kind'] = $location->attributes->kind;
			switch ($checkin['kind']) {
				case "Folder":
					$checkin['area'] = $location->attributes->name;
					$checkin['roster'] = '';
					break;
				case "Location":
					if ($location->relationships->parent->data == null)
					{
						$checkin['area'] = "None";
					}
					else
					{
						$parentId = $location->relationships->parent->data->id;
						foreach ($includeds as $included)
						{
							if ($included->id == $parentId)
							{
								$checkin['area'] = $included->attributes->name;
								break;
							}
						}
					}
					$checkin['roster'] = $location->attributes->name;
					break;
				default:
					break;
			}
					
			$checkin['pcount'] = 0;
			$checkin['plist'] = '';
//			$checkin['scount'] = 0;
//			$checkin['slist'] = '';
			
			$checkins[$counter++] = $checkin;
		}
		

		if (!array_key_exists('next', $links))
		{
			$next = false;
		}
		else
		{
			$api = $links->next;

			curl_setopt($ch, CURLOPT_URL, $api); // set cURL url
			$curlResult = curl_exec($ch); // $curlResult contains the JSON-encoded string sent from API

			////////////////////////////////////////////////////////////////////////
			// Verify status code from PCO API
			////////////////////////////////////////////////////////////////////////
			$received_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
			switch($received_status_code) {
			case 200:
				// expected, continue
				break;
			default:
				// unexpected, throw error
				throw new Exception("Unexpected Status Code in Request to PCO API: " . $received_status_code, 1);
			}
		}
		
	} while($next);


	

	/////////////////////////////////////////////////////////////////////////////////
	// 	Retrieve Check-in data and Build data by location
	/////////////////////////////////////////////////////////////////////////////////
	$srv = "check_ins/v2/event_times/" . $eventtimeid . "/check_ins";
	$param = "?include=location,person";

	$api = $URL . $srv . $param;

	curl_setopt($ch, CURLOPT_URL, $api); // set cURL url
	$curlResult = curl_exec($ch); // $curlResult contains the JSON-encoded string sent from API

	////////////////////////////////////////////////////////////////////////
	// Verify status code from PCO API
	////////////////////////////////////////////////////////////////////////
	$received_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
	switch($received_status_code) {
	case 200:
		// expected, continue
		break;
	default:
		// unexpected, throw error
		throw new Exception("Unexpected Status Code in Request to PCO API: " . $received_status_code, 1);
	}

	$next = true;
	// Retrieve all locations for the event
	do {
		$curlResult = json_decode($curlResult); // Convert JSON-encoded string into PHP object
		$links = $curlResult->links;
		$people = $curlResult->data;
		$includeds = $curlResult->included;

		foreach ($people as $person)
		{
			if ($person->attributes->checked_out_at != null)
			{
				continue;
			}
			
			$id = $person->id;
			$name = $person->attributes->first_name . " " . $person->attributes->last_name;
			$kind = $person->attributes->kind;
			$locId = $person->relationships->location->data->id;

			for ($j=0; $j<count($checkins); $j++)
			{
				if ($checkins[$j]['id'] == $locId)
				{
					switch ($kind) {
						case "Regular":
						case "Guest":
						case "Volunteer":
							if ($checkins[$j]['pcount'] == 0)
							{
								$checkins[$j]['plist'] = $name ;
							}
							else
							{
								$checkins[$j]['plist'] = $checkins[$j]['plist'] . ", " . $name ;
							}
							$checkins[$j]['pcount'] = $checkins[$j]['pcount'] + 1;
							break;
/*
						case "Volunteer":
							if ($checkins[$j]['scount'] == 0)
							{
								$checkins[$j]['slist'] = $name ;
							}
							else
							{
								$checkins[$j]['slist'] = $checkins[$j]['slist'] . ", " . $name ;
							}
							$checkins[$j]['scount'] = $checkins[$j]['scount'] + 1;
							break;
*/
						default:
							break;
					}
					
					break;
				}
			}

		}
		
		if (!array_key_exists('next', $links))
		{
			$next = false;
		}
		else
		{
			$api = $links->next;

			curl_setopt($ch, CURLOPT_URL, $api); // set cURL url
			$curlResult = curl_exec($ch); // $curlResult contains the JSON-encoded string sent from API

			////////////////////////////////////////////////////////////////////////
			// Verify status code from PCO API
			////////////////////////////////////////////////////////////////////////
			$received_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
			switch($received_status_code) {
			case 200:
				// expected, continue
				break;
			default:
				// unexpected, throw error
				throw new Exception("Unexpected Status Code in Request to PCO API: " . $received_status_code, 1);
			}
		}
		
	} while($next);



	$data['data'] = $checkins;
	echo json_encode(json_encode($data));


	exit;

	
	
}
catch(Exception $e) 
{
	// Leave an error message in log file
	echo $e->getMessage() . "\n";
}

