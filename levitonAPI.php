#!/usr/bin/php
<?php
$LEVITON_ROOT = 'https://my.leviton.com/api';
$USERNAME = getenv("leviton_username");
$PASSWORD = getenv("leviton_password");
$ONPERCENT = getenv("leviton_percent");

$error = false;
if($USERNAME === false){
	echo "Environmental Variable 'leviton_username' not set.\n";
	$error = true;
}
if($PASSWORD === false){
	echo "Environmental Variable 'leviton_password' not set.\n";
	$error = true;
}
if($ONPERCENT === false){
	echo "Environmental Variable 'leviton_percent' not set.\n";
	$error = true;
}
if($error){
	exit;
}

// Optional Environmental Variables
$TWILIGHT = getenv("sunwait_twilight");
if($TWILIGHT === false || $TWILIGHT != "daylight" && $TWILIGHT != "civil" && $TWILIGHT != "nautical" && $TWILIGHT != "astronomical" && strpos($TWILIGHT, "angle") === false ){
	echo "Defaulting to 'daylight' twilight.\n";
	$TWILIGHT = "daylight";
}

$OFFSET = getenv("sunwait_offset");
if($OFFSET === false){
	$OFFSET = "";
} else {
	$OFFSET = "offset ".$OFFSET." ";
}


// Verify that there is not another instance running that could cause a conflict or waisted resources
if(trim(shell_exec("ps -aux | grep '".$argv[0]." start' | grep -v grep | wc -l")) > 1){
        echo "There is another instance already running, exiting.\n";
        exit;
}

if(!isset($argv[1])){
        echo "Argument Required.\n";
        exit;
}

function lev_request($api, $body, $method, $sessionId = null){
        global $LEVITON_ROOT;
        $uri = $LEVITON_ROOT.$api;

        if($method == "POST"){
                $body = json_encode($body);
                $post = 1;
        } else if($method == "GET"){
                //GET
                $post = 0;
        } else {
                //PUT
                $body = json_encode($body);
                $post = 0;
        }


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if($post == 1){
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else if($method == "GET") {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
                'Authorization: ' . $sessionId)
        );
        $output["result"] = json_decode(curl_exec($ch), true);
        $output["responseInfo"] = curl_getinfo($ch);

        return $output;
}

function lev_login($email, $password){
        $body = array('email' => $email, 'password' => $password, 'clientId' => 'levdb-echo-proto', 'registeredVia' => 'myLeviton');
        $req = lev_request('/Person/login', $body, "POST");
        if ($req['responseInfo']['http_code'] != '200'){
                        echo "Response ".$req['responseInfo']['http_code'].": ".$req['result'];
                        return null;
        }
        return $req['result'];
}

function lev_logout($session){
        $body = [];
        $req = lev_request('/Person/logout', $body, "POST", $session['id']);
        if ($req['responseInfo']['http_code'] != '200' && $req['responseInfo']['http_code'] != '204'){
                var_dump($req);
                return false;
        }
}

function lev_residential_permissions($session){
        $req = lev_request("/Person/".$session['userId']."/residentialPermissions", null, "GET", $session['id']);
        if ($req['responseInfo']['http_code'] != '200' && $req['responseInfo']['http_code'] != '204'){
                var_dump($req);
                return false;
        }
        return $req['result'];
}

function lev_residences($session, $residential_account_id){
        $req = lev_request("/ResidentialAccounts/".$residential_account_id."/Residences", null, "GET", $session['id']);
        if ($req['responseInfo']['http_code'] != '200'){
                var_dump($req);
                return null;
        }
        return $req['result'];
}

function lev_iot_switches($session, $residence_id){
        $req = lev_request("/Residences/".$residence_id."/iotSwitches", null, "GET", $session['id']);
        if ($req['responseInfo']['http_code'] != '200'){
                var_dump($req);
                return null;
        }
        return $req['result'];
}

function lev_update_switch($session, $switch_id, $power, $brightness){
        $body = [ 'brightness' => $brightness, 'power' => $power ];
        $req = lev_request("/IotSwitches/".$switch_id, $body, "PUT", $session['id']);
        if ($req['responseInfo']['http_code'] != '200' && $req['responseInfo']['http_code'] != '204'){
                var_dump($req);
                return false;
        }
        return $req['result'];
}

$session = null;

if (is_null($session)){
        $session = lev_login($USERNAME, $PASSWORD);
}

if (is_null($session)){
        echo 'Login failed.\n';
        exit;
}

$launch = false;
if ($argv[1] == "start"){
	$launch = true;
} else if ($argv[1] == 0){
	$state = 'OFF';
	$percent = '0';
} else {
	$state = 'ON';
	$percent = $argv[1];
}

$perm = lev_residential_permissions($session);
$residences = lev_residences($session, $perm[0]['residentialAccountId']);
echo "Selected Residence: ".$residences[0]['name']." (".$residences[0]['geopoint']['lat'].", ".$residences[0]['geopoint']['lng'].")\n";
if($launch){
	$current = shell_exec("sunwait poll ".$TWILIGHT." ".$OFFSET."".$residences[0]['geopoint']['lat']."N ".$residences[0]['geopoint']['lng']."E");
	$switches = lev_iot_switches($session, $residences[0]['id']);
	foreach($switches as $switch){
		if(trim($current) == "NIGHT"){
			//Night - Turn Switch On
			$state = "ON";
			$percent = $ONPERCENT;
		} else {
			//Day - Turn Switch OFF
			$state = "OFF";
			$percent = 0;
		}
		if($switch['power'] != $state || $switch['brightness'] != $percent){
			echo "Turning switch ".$switch['name']." ".$state." at ".$percent."%\n";
			lev_update_switch($session, $switch['id'], $state, $percent);
		}
	}
	lev_logout($session);
	$lastHour = -1;
	while(true){
		//Run at noon and midnight
		if(date("H")%12 == 0 && date("H") != $lastHour){
			if(trim(shell_exec("sunwait poll ".$TWILIGHT." ".$OFFSET."".$residences[0]['geopoint']['lat']."N ".$residences[0]['geopoint']['lng']."E")) == "NIGHT"){
				//NIGHT - Turn Off Light at sunrise
				echo shell_exec("sunwait wait ".$TWILIGHT." rise ".$OFFSET."".$residences[0]['geopoint']['lat']."N ".$residences[0]['geopoint']['lng']."E && ".$argv[0]." 0");
			} else {
				//Day - Turn On Light at sunset
				echo shell_exec("sunwait wait ".$TWILIGHT." set ".$OFFSET."".$residences[0]['geopoint']['lat']."N ".$residences[0]['geopoint']['lng']."E && ".$argv[0]." ".$ONPERCENT);
			}
			$lastHour = date("H");
		}
		//Sleep 30 Minutes
		sleep(1800);
	}
	exit;
}

$switches = lev_iot_switches($session, $residences[0]['id']);

foreach($switches as $switch){
        echo "Turning switch ".$switch['name']." ".$state." at ".$percent."%\n";
        lev_update_switch($session, $switch['id'], $state, $percent);
}
lev_logout($session);

?>