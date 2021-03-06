<?php 
/**
  *			 Copyright 2016 Shalom Carmel
  *			Licensed under the Apache License, Version 2.0 (the "License");
  *			you may not use this file except in compliance with the License.
  *			You may obtain a copy of the License at
  *
  *			http://www.apache.org/licenses/LICENSE-2.0
  *
  *			Unless required by applicable law or agreed to in writing, software
  *			distributed under the License is distributed on an "AS IS" BASIS,
  *			WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  *			See the License for the specific language governing permissions and
  *			limitations under the License.
  *
**/

$version = "1.9.5";
$author = 'Shalom Carmel 2016';

require_once('./lib/GeoIP/GeoIP.php');

$ini_file= "config.txt"; 
$configuration_array = array (); 
if (file_exists($ini_file) ) {
		$configuration_array = parse_ini_file($ini_file);
}

if (!defined("JSON_PRETTY_PRINT")) {
    define("JSON_PRETTY_PRINT", 128);
}

if (!defined("JSON_UNESCAPED_SLASHES")) {
    define("JSON_UNESCAPED_SLASHES", 64);
}


 
 // Instruct browsers, proxies and CDN to not cache
 header('Cache-Control: private, no-cache'); 
 
 // Allow usage with AJAX 
 header('Access-Control-Allow-Origin: *'); 

// Output json by default, output text only if instructed to by an appropriate header or a query string item. 
if ( empty($_SERVER['HTTP_X_ECHO_TYPE']) && empty($_GET['x-echo-type'] ) && empty($configuration_array["response-type"])) {
	$json=true; 
} elseif ($configuration_array["response-type"] == 'text' ) {
	$json = false;
} elseif ($_SERVER['HTTP_X_ECHO_TYPE'] == 'text' ) {
	$json = false;
} elseif ($_GET['x-echo-type'] == 'text' ) {
	$json = false;
} else {
	$json = true;
}

// By default do not fetch and output geoip, unless instructed to by an appropriate header or a query string item. 
if ( empty($_SERVER['HTTP_X_ECHO_GEOIP']) && empty($_GET['x-echo-geoip'] ) && empty($configuration_array["resolve-geoip"])) {
	$geoip=false; 
} elseif ($configuration_array["resolve-geoip"] == true ) {
	$geoip = true;
} elseif ($_SERVER['HTTP_X_ECHO_GEOIP'] == 'on' ) {
	$geoip = true;
} elseif ($_GET['x-echo-geoip'] == 'on' ) {
	$geoip = true;
} else {
	$geoip = false;
}
// Check if to do device detection 
if (file_exists('vendor/mobiledetect/mobiledetectlib/Mobile_Detect.php')) { 
	$devicedetect = true;
} else {
	$devicedetect = false;
}

// Override client ip to support CDN settings
if ( empty($_SERVER['HTTP_X_OVERRIDE_CLIENTIP']) && empty($_GET['x-override-clientip'] ) && empty($configuration_array["override-clientip"])) {
	$override_clientip=false; 
} elseif (empty($configuration_array["override-clientip"]) == false ) {
	$override_clientip = true;
	$clientip_header=$configuration_array["override-clientip"]; 
} elseif (empty($_SERVER['HTTP_X_OVERRIDE_CLIENTIP']) == false ) {
	$override_clientip = true;
	$clientip_header=$_SERVER['HTTP_X_OVERRIDE_CLIENTIP']; 
} elseif (empty($_GET['x-override-clientip']) == false ) {
	$override_clientip = true;
	$clientip_header=$_GET['x-override-clientip'];
} else {
	$override_clientip = false;
}



if ($json) { 
	header('Content-Type: application/json');
	$response = array ();
	date_default_timezone_set("UTC");
	$time = time(); 
    $headers = apache_request_headers();
	$response["meta"]["description"]='HTTP echo service';
	$response["meta"]["author"]=$author;
	$response["meta"]["version"]=$version;
	$response["meta"]["source"]="https://github.com/shalomc/HTTP-echo/";
	$response["request"]["timestamp"]=$time;
	$response["request"]["date"]=date('Y-m-d h:i:sO',$time);
	$response["request"]["date"]=date('c',$time);
	$response["request"]["server"]= $_SERVER["SERVER_NAME"] ;
	$response["request"]["port"]= $_SERVER["SERVER_PORT"] ;
	$response["request"]["protocol"]= $_SERVER["SERVER_PROTOCOL"] ;
	$clientip = $override_clientip && !empty($headers[$clientip_header]) ? $headers[$clientip_header] : $_SERVER["REMOTE_ADDR"] ;
	$response["request"]["client_ip"]= $clientip ;
	$response["request"]["method"]= $_SERVER["REQUEST_METHOD"] ;
	$response["request"]["uri"]= $_SERVER["REQUEST_URI"] ;
	$response["request"]["path"]= parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ;
	$response["request"]["query_string"]= empty($_SERVER["QUERY_STRING"]) ? null : $_SERVER["QUERY_STRING"] ;
	$response["request"]["https"]= $_SERVER["HTTPS"] ;
	$response["request"]["remote_user"]= $_SERVER["REMOTE_USER"] ;	
	$response["request"]["params"]= $_GET ;		
	$response["headers"]= $headers; 
	
    $HTTP_body = file_get_contents('php://input');
	
	if ( strtolower($headers["Content-Type"]) == "application/json") {
		$json_body = json_decode($HTTP_body);
		if (is_null($json_body)) {
		   $response["body"]= empty($HTTP_body) ? null : $HTTP_body; 
		   $response["meta"]["error"]="Invalid JSON Payload";
		} else {
			$response["body"] = $json_body;
		}
	} else {  // non json payload
		$response["body"]= empty($HTTP_body) ? null : $HTTP_body; 
	}
	
	// geoip integration
	if ($geoip) {
		$ip_source=array("IP-source" => $override_clientip && !empty($headers[$clientip_header]) ? $clientip_header : "REMOTE_ADDR" ); 
		$response["geoip_info"] = array_merge( $ip_source, get_geoip_info( $clientip ) ); 
	}

	// Device detection integration
	if ($devicedetect) {
		require_once 'vendor/mobiledetect/mobiledetectlib/Mobile_Detect.php';
		$detect = new Mobile_Detect;
		$response["device"]["is_mobile"]= $detect->isMobile() ? true : false; 
		$response["device"]["is_tablet"]= $detect->isTablet() ? true : false; 
		$rules = $detect->getRules();
		foreach ($rules as $rule => $value) {
			if ($detect->is($rule)) {
				$response["device"][$rule]=  true ;
			}
		}
	}
	
	echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ; 
    echo PHP_EOL;	
	
} else {
	header('Content-Type: text/plain');
	echo $_SERVER['REQUEST_METHOD'] .' '.  $_SERVER['REQUEST_URI']. ' ' . $_SERVER['SERVER_PROTOCOL'] . PHP_EOL;

    $headers = apache_request_headers();
    foreach ($headers as $header => $value) {
       echo "$header: $value" ;
	   echo PHP_EOL;
    }

    $HTTP_body = file_get_contents('php://input');
    echo PHP_EOL;
    echo "$HTTP_body" ;
    echo PHP_EOL;	
}


 
if( !function_exists('apache_request_headers') ) {
    function apache_request_headers() {
        $out_array = array();
        $rx_http = '/\AHTTP_/';

		// Look only for $_SERVER keys that look like HTTP_SOMETHING
        foreach($_SERVER as $key => $val) {
            if( preg_match($rx_http, $key) ) {
                // Convert something like this "HTTP_X_FORWARDED_FOR"
				//  into something like this "X-Forwarded-For"
                $out_array_key = preg_replace($rx_http, '', $key);
				$rx_matches = array();
                $rx_matches = explode('_', $out_array_key);

                if( count($rx_matches) > 0 and strlen($out_array_key) > 2 ) {
                    foreach($rx_matches as $ak_key => $ak_val) {
                        $rx_matches[$ak_key] = ucfirst($ak_val);
                    }

                    $out_array_key = implode('-', $rx_matches);
                }

                $out_array[$out_array_key] = $val;
            }
        }

        return( $out_array );
    }
}



?>