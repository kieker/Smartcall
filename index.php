<?php
//Reload the domain over https connection for security.	
if($_SERVER["HTTPS"] != "on")
{
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
    exit();
}

//the details for connecting to the API

$str = 'username:password';
$new_str = base64_encode($str);

// functions



function curl_request($url, $method, $auth = "Bearer") #used for making the calls to the api
{
	$ch = curl_init();
    $bearer_check = strtoupper($auth);
	if ($auth == "BASIC")
    {
        $auth_method = 'Basic '.$GLOBALS['new_str'];
    }
    else
    {
        $auth_method = 'Bearer '.$GLOBALS['security_token'];
    }
      
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded','Authorization:'.$auth_method));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
	
	if (curl_error($ch))
	{
		$error = curl_error($ch);
		echo $error;
	}
	
    $data = curl_exec($ch);
	curl_close($ch);
	return $data;
}	


#DB Strings
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "smartcall_db1";
$conn = new mysqli($servername,$username,$password,$dbname);

if ($conn->connect_error)
{
    die("Connection failed: " . $conn->connect_error);
}

$token_available = curl_request('https://www.smartcallesb.co.za:8101/webservice/auth/token', 'GET', 'BASIC');
$token_avail_json = json_decode($token_available);

/* Reset all tokens, first step to ensure that there are tokens available to work with */
if ($token_avail_json->availableTokens == 0)
{
    $sec_clear = curl_request('https://www.smartcallesb.co.za:8101/webservice/auth/token', 'DELETE', 'BASIC');
    //var_dump($sec_clear);
    $sql = "DELETE FROM tokens";
    if($conn->query($sql) === TRUE)
    {
        //echo "Security tokens cleared.";
    }
    else
    {
        echo "Error: ".$sql."<br/>".$conn->error."<br/>";
    }
}


if ($token_avail_json->availableTokens < 50) /*There are security tokens available, get them from the DB */
{
	$date_now = new DateTime();
	$sql_select = "SELECT token,expires FROM tokens ORDER BY RAND() LIMIT 1";
    if($conn->query($sql_select) !== FALSE)
    {
	    $date = new DateTime(); // get the current time
        $data = $conn->query($sql_select);
        //echo "Security token fetched from the db <br/>";
        if ($data->num_rows > 0)
        {
	        while ($rows = $data->fetch_assoc())
            {

             $date_token = $rows['expires'];
             $date_format = new DateTime($date_token);
             
             
             if ($date > $date_format) //token is still valid, use it
             {
	             $security_token = $rows['token'];
	             #echo "valid token fetched from db";
             }
             else
             {
	             $security_token = $rows['token'];
	             $expire_req = curl_request('https://www.smartcallesb.co.za:8101/webservice/auth/', 'DELETE', 'BEARER');
	             $sql = "DELETE FROM tokens WHERE token= '".$security_token."'";
	             if($conn->query($sql) === TRUE)
				{
					#echo "Expired token deleted, new token will be generated";
				}
				else
				{
					echo "Error: ".$sql."<br/>".$conn->error."<br/>";
				}

	             //Token deleted from smartcall and db, request a new one
	             
	             $security_token_req = curl_request('https://www.smartcallesb.co.za:8101/webservice/auth/', 'POST', 'BASIC');
				$security_json = json_decode($security_token_req);
				$security_token = $security_json->accessToken;
				$date = new DateTime();
				$date->modify("+30 minutes"); //or whatever value you want
				$time= $date->format('Y-m-d H:i:s');
				$sql = "INSERT INTO tokens (token, expires) VALUES ('" . $security_json->accessToken ."','$time')";
			
				if($conn->query($sql) === TRUE)
				{
					#echo "New token generated";
					//echo "Security token added successfully.";
					
				}
				else
				{
					echo "Error: ".$sql."<br/>".$conn->error."<br/>";
				}
             }
            }
             
        }
    }
    else
    {
        echo "Error on ".$sql_select."<br/>Error description: ".$conn->error."<Br/>";
    }
}
else /*Create token */
{
	
	$security_token_req = curl_request('https://www.smartcallesb.co.za:8101/webservice/auth/', 'POST', 'BASIC');
	$security_json = json_decode($security_token_req);
	$security_token = $security_json->accessToken;

	$date = new DateTime();
	$date->modify("+30 minutes"); //or whatever value you want
	$time= $date->format('Y-m-d H:i:s');
	$sql = "INSERT INTO tokens (token, expires) VALUES ('" . $security_json->accessToken ."','$time')";

	if($conn->query($sql) === TRUE)
	{
		#echo "Security token added successfully.";
		
	}
	else
	{
		echo "Error: ".$sql."<br/>".$conn->error."<br/>";
	}


}
    
$networks = curl_request('https://www.smartcallesb.co.za:8101/webservice/utilities/health',"GET", 'Bearer');
$networks_json = json_decode($networks);

$network_arr = array();

foreach ($networks_json as $key =>$value)
{
	if ($value == "UP")
	{
		array_push($network_arr, $key);
    }  
}
# Main body
?>


<html>
	<head>
		<title> Buy Airtime </title>
		<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,800" rel="stylesheet">
		<script src="https://code.jquery.com/jquery-3.3.1.js" integrity="sha256-2Kok7MbOyxpgUVvAk/HJ2jigOSYS2auK4Pfzbm7uH60=" crossorigin="anonymous"></script>
		<script type="text/javascript">
			function showToast() 
			{
			  // Get the snackbar DIV
			  var x = document.getElementById("snackbar");
			
			  // Add the "show" class to DIV
			  x.className = "show";
			
			  // After 3 seconds, remove the show class from DIV
			  setTimeout(function(){ x.className = x.className.replace("show", ""); }, 8000);
			}
        	$(document).ready(function()
        	{
	        	$('#network').on('change', function()
	        	{
					if ($(this).val() == 'mtn')
					{
						$('#product_type').empty().append('<option name="data" value="DV">Data</option><option name="airtime" value="V ">Airtime</option>');
					}	   
					else
					{
						$('#product_type').empty().append('<option name="data" value="X ">Data</option><option name="airtime" value="V ">Airtime</option>');
					}        
				});

				$('#network,#product_type').on('change',function()
				{
					console.log($('#network').val());
					$.ajax(
					{
						url: 'getproducts.php',
						type:'POST',
						data: {'security_token':'<?php echo $security_token;?>', 'network':$("#network").val(),'prod_type':$("#product_type").val()},
						success:function(response) 
						{
		                    var prod_response = JSON.parse(response);
		                    console.log(prod_response);
		                    var i = 0;
		                    var listitems = '';
		                    
		                   
		                    if (Object.keys(prod_response.items).length > 0)
		                    {
			                    $("#submit").attr("disabled",false);
			                    $.each(prod_response.items, function(key, value)
							{
								listitems += '<option value="' + this.id + '" data-minvalue="'+ this.thevalue +'">' + this.name + '</option>';
								i++;
							});
		                    }
		                    else
		                    {
			                    listitems += '<option value="0" data-minvalue="0">No products available, please choose a different option</option>';
								
		                    }
							
								
							
							$('#products').empty().append(listitems);
				

                		}
					});
				});
                $('#submit').on('click',function(e)
                {
	                var phone = document.getElementById('mobile');
	                var flag = false;
	                var allowedChars = "+01234567890";
	                var allowedLength = Array(10,11,12);
	                
					for (var i = 0; i < phone.value.length; i++) 
					{
						if (allowedChars.indexOf(phone.value.charAt(i)) == -1)
						{
							alert ("Illegal characters detected! Please enter a valid phone number."); 
							phone.focus(); 
							flag = true;
						}
					}
					if (!allowedLength.includes(phone.value.length))
					{
						alert("Phone number isn't correct. Please enter a valid phone number.");
						flag = true;
					}
					if (flag)
					{
						return;
					}
	                console.log("clicked");
	            		var check = false;
	                
	                	$('form').find('p').remove();
	                	
	               	                
					$('form').find("input").each(function(){
						
						if ($(this).attr("required"))
						{
							if($(this).val() == '')
							{
								check = false;
								$(this).after('<p class="err">This field is required</p>');
								console.log("field is required");
							}
							else
							{
								check = true;
																
							}
							
						}

					});
					 	if ($('#products').val() == 0)
	                	{
		                	check = false;
		                	$("#products").after("<p class='err'>Please select a valid product</p>");
		                	 $('#submit').attr('disabled', true);
	                	}

					setTimeout(function(){
						if (check)
	            		{
		            		$('.ajax-loader').show();
		            		$.ajax(
									{
										url:'checkvoucher.php',
										type:'POST',
										data:
										{ 
											'security_token': '<?php echo $security_token; ?>',
											'vouchernumber':$('#voucher').val(),
											'network':$('#network').val(),
											'product_type':$('#product_type').val(),
											'product_id':$('#products').val(),
											'product_name': $('#products').text(),
											'mobile_nr':$("#mobile").val(),
											'minvalue': $('#products').find(':selected').attr('data-minvalue')
										},
										success:function(response)
										{
											$('#snackbar').html("<p>" + response + "</p>");
											showToast();
											console.log(response);  
											$('.ajax-loader').hide();
						        		}   
						        	});

	            		}

					},500);
					e.preventDefault();

					}); 
        	});
    	</script>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
	</head>
	<body>
		<div class="main_container">
			<img src="/img/logo.png"/>
			<h1>Recharge</h1>
			<div class="form_container">
				<form id="smartcall_form" method="post">
					<div class="mainfields_container">
					<label for="network">On what network do you want to recharge:</label>
					<select name="network" id="network">
						<option name="none" value="none">Please select a network</option>
						<?php 
							foreach ($network_arr as $network)
							{
								echo '<option name="'.$network.'">'.$network.'</option>';
			    			}
			    		?>
		    		</select><br/>
		    		
				    <label for="product_type">Would you like data or airtime:</label>
				    <select name="product_type" id="product_type">
				    	<option value="X ">Data</option>
				    	<option value="V ">Airtime</option>
				    </select><br/>
				    
				    <label for="product">Product:</label>
				    <select name="product" id="products">
				     	<option value="0">Select a network & data or airtime first</option>
				    </select><br/>
				    
				    <label for="mobile">Cell phone number: </label>
				    <input type="text" name="mobile" id="mobile" required>
					</div>
					<div class="voucherfield_container">
				    <label for="voucher_nr">NSFAS Wallet voucher number:</label>
					<input type="text" name="voucher_nr" id="voucher" required><br/>
					</div>
	
				    
				    <button type="submit" id="submit" >Buy</button>
				</form>
			</div>
		<div id="snackbar"></div>
		<div class="ajax-loader"><img src="/img/ajax-loader.gif"></div>
		</div>
	</body>
</html>
	
	<?php $conn->close(); ?>