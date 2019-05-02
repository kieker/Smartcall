<?php
	
//functions
	
define('TBL_ATTEMPTS', 'lockouts');
define('TIME_PERIOD','1');
define('ATTEMPTS_NUMBER','5');

function get_client_ip() 
{
	$ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function confirmIPAddress($value,$conn) 
{ 
	$q = "SELECT attempts, (CASE when lastlogin is not NULL and DATE_ADD(LastLogin, INTERVAL ".TIME_PERIOD. " MINUTE)>NOW() then 1 else 0 end) as Denied FROM ".TBL_ATTEMPTS." WHERE ip = '$value'"; 
	$result = $conn->query($q);
	if($result !== FALSE)
	{
		$data = $result->fetch_array();
	}
	else
	{
	
	}

	//Verify that at least one login attempt is in database 

	if (!$data) 
	{ 
    	return 0; 
	} 
	if ($data["attempts"] >= ATTEMPTS_NUMBER) 
	{ 
    	if($data["Denied"] == 1) 
		{ 
			return 1; 
    	} 
		else 
		{ 
			clearLoginAttempts($value,$conn); 
			return 0; 
    	} 
	} 
	return 0; 
} 

function addLoginAttempt($value,$conn) 
{
	//Increase number of attempts. Set last login attempt if required.
	$q = "SELECT * FROM ".TBL_ATTEMPTS." WHERE ip = '$value'"; 
	$result = $conn->query($q);
	if($result !== FALSE)
	{
		$data = $result->fetch_array();
	}
	if($data)
	{
		$attempts = $data["attempts"]+1;         

		if($attempts==3) 
		{
			$q = "UPDATE ".TBL_ATTEMPTS." SET attempts=".$attempts.", lastlogin=NOW() WHERE ip = '$value'";
			$result = $conn->query($q);
		}
		else 
		{
			$q = "UPDATE ".TBL_ATTEMPTS." SET attempts=".$attempts." WHERE ip = '$value'";
			$result = $conn->query($q);
		}
	}
	else 
	{
		$q = "INSERT INTO ".TBL_ATTEMPTS." (attempts,IP,lastlogin) values (1, '$value', NOW())";
		$result = $conn->query($q);
	}
}

function clearLoginAttempts($value,$conn) 
{
	$q = "UPDATE ".TBL_ATTEMPTS." SET attempts = 0 WHERE ip = '$value'"; 
	return $conn->query($q);
}

function get_data($s)
{
	if (isset($_POST[$s]))
	{
		$return_var = $_POST[$s];
	}
	return $return_var;
}

function array_push_assoc($array, $key, $value)
{
	$array[$key] = $value;
	return $array;
}

function curl_request_vend($url, $method, $auth = "Bearer",$data,$s_token)
{
	$ch = curl_init();
    $bearer_check = strtoupper($auth);
	if ($auth == "BASIC")
    {
        $auth_method = 'Basic '.$s_token;
    }
    else
    {
        $auth_method = 'Bearer '.$s_token;
    }
 
    $data_string = json_encode($data); 
    //echo $data_string;
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json','Authorization:'.$auth_method));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 
	
	
	if (curl_error($ch))
	{
		$error = curl_error($ch);
		echo $error;
	}
    $data = curl_exec($ch);
	curl_close($ch);
	return $data;
}	

 
$my_ip = get_client_ip();

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "smartcall_db1";
$conn = new mysqli($servername,$username,$password,$dbname);

if ($conn->connect_error)
{
    die("Connection failed: " . $conn->connect_error);
}
$result = confirmIPAddress($my_ip,$conn);

if ($result == 1)
{
	echo '<div> Your access has been blocked. Please try again in 30 min</div>';
}
addLoginAttempt($my_ip,$conn);
$paid = false;

//data
$voucher_nr = get_data('vouchernumber');

$product_id = get_data('product_id');
$mobile = get_data('mobile_nr');
if ($mobile[0] == '0')
{
	$mobile = substr_replace($mobile, '27',0,1);
}
if ($mobile[0] == '+')
{
	$mobile = substr_replace($mobile, '',0,1);
}

$product_type = get_data("product_type");
$minvalue = (int)get_data("minvalue");
$minvalue_cellbux = $minvalue*100;
$s_token = get_data('security_token');
$reference = $mobile .'-'. date('Ymd').'-'.$product_id.'-'.uniqid();
$content = array
(
	"VoucherNo" => $voucher_nr,
	"Reference" => "$reference",
	"Amount" => "$minvalue_cellbux",
	"MEID" => "Z3g0WG11SXM1NmZwZVdfY3hb",
	"Currency" =>"ZAR"
);

$content_json = json_encode($content);
//echo $content_json;
$content_length = strlen($content_json);
//echo 'content length is' .$content_length;
//echo $content_json;
$token = 'ART49ufn9fuofoq04edfnwHpkg3inc';



$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://celbux-url-goes-here",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => $content_json,
  CURLOPT_HTTPHEADER => array(
    "Authorization: auth_token",
    "Content-Type: application/json",
     ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
 
}

$response_array = json_decode($response);


if ($response_array->State == "Failed" && $result != 1)
{
	echo 'There was an issue with the voucher check: ' . $response_array->Error;
	if ($response_array->Error == "Connection Error")
	{
		echo "<br/>Please check your voucher number.";
	}
	addLoginAttempt($my_ip,$conn);

}
else
{
	if ($result != 1)
	{
		$paid = true;
		clearLoginAttempts($my_ip,$conn);
	}
	
}
	
if ($paid)
{
	$sql = "INSERT INTO transactions (phone_nr,product_type,product,reference,amount) VALUES ('" . $mobile."','".$product_type."','".$product_id."','".$reference."','".$minvalue."')";
	if ($conn->query($sql) === TRUE)
	{
		echo "Transaction successful";
	}
	else
	{
		echo "Your transaction was successful, but we failed to create a transaction record";
		  echo "Error: ".$sql."<br/>".$conn->error."<br/>";
	}
	
	
		//TODO: Smartcall vend airtime/data
        $data = array('smartloadId'=>'00000000000','clientReference'=> $reference, 'smsRecipientMsisdn'=> $mobile,'productId' => $product_id, 'amount'=>$minvalue, 'pinless'=>true,'sendSms'=>true) ;
        $vend = curl_request_vend('https://www.smartcallesb.co.za:8101/webservice/smartload/recharges','POST', 'BEARER',$data,$s_token);


}

	?>