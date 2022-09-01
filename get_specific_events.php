<?php
include_once 'config.php';

$URL = "https://api.planningcenteronline.com/";

$ch = curl_init(); // create cURL resource
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ;
curl_setopt($ch, CURLOPT_USERPWD, NACD_API_PW); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the transfer as a string
// curl_setopt($ch, CURLOPT_VERBOSE, true); // for debugging purposes


date_default_timezone_set('America/New_York');
$data = array();

try 
{
	$srv = "check_ins/v2/event_times";
	$param = "?order=-starts_at&include=event,event_period";

	$api = $URL . $srv . $param;
	curl_setopt($ch, CURLOPT_URL, $api); // set cURL url

	$curlResult = curl_exec($ch); // $curlResult contains the JSON-encoded string sent from API
	$curlResult = json_decode($curlResult); // Convert JSON-encoded string into PHP object

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


	$targetdate = mktime(0, 0, 0, date("m"), date("d")+2, date("Y"));
	$today = date("Y-m-d", $targetdate);
	$eventtimes = $curlResult->data;		// holding event times
	$includeds = $curlResult->included;		// holding extra information
	$events = array();
	$counter = 0;

	foreach ($eventtimes as $eventtime)
	{
		$starttime = $eventtime->attributes->shows_at;
		if (strpos($starttime, $today) !== false)
		{
			$event = array();
			$event['id'] = $eventtime->relationships->event->data->id;
			$event['timeid'] = $eventtime->id;
			$event['periodid'] = $eventtime->relationships->event_period->data->id;
			$event['time'] = date("m/d/Y h:i A", strtotime($eventtime->attributes->starts_at));

/*	
			for ($j=0; $j<count($included); $j++)
			{
				if (($included[$j]->type == "Event") && ($included[$j]->id == $event['id']))
				{
					$event['name'] = $included[$j]->attributes->name;
					$event['displayname'] = $event['name'] . ' - ' . $event['time'];
					break;
				}
			}
*/
			foreach ($includeds as $included)
			{
				if (($included->type == "Event") && ($included->id == $event['id']))
				{
					$event['name'] = $included->attributes->name;
					$event['displayname'] = $event['name'] . ' - ' . $event['time'];
					break;
				}
			}
			
			$events[$counter++] = $event;
		}
			
	}


	$data['data'] = $events;
	echo json_encode(json_encode($data));

	exit;


	
}
catch(Exception $e) 
{
	// Leave an error message in log file
	echo $e->getMessage() . "\n";
}

