<?php

// Functions used in the code below
	
function utf8ize($d) {
    if (is_array($d)) 
        foreach ($d as $k => $v) 
            $d[$k] = utf8ize($v);

     else if(is_object($d))
        foreach ($d as $k => $v) 
            $d->$k = utf8ize($v);

     else 
        return utf8_encode($d);

    return $d;
}
function get_data($s)
{
	if (isset($_POST[$s]))
	{
		$return_var = $_POST[$s];
	}
	return $return_var;
}

// Get data and set correct networks strings

$security_token = get_data('security_token');
$prod_type = get_data('prod_type');
$network = get_data('network');
switch($network)
{
	case 'mtn':
		$network = strtoupper($network);
		break;
	case 'vodacom':
		$network = 'Vodacom';
		break;
	case 'cellc':
		$network = 'Cell C';
		break;
	case 'telkom':
		$network = "Telkom";
		break;
	case 'virgin':
		$network = "Virgin Mobile";
		break;
}

//Make the call
    
    $options_get = array
    (
        'http' => array
        (
            'header' =>"Content-type: application/x-www-form-urlencoded\r\nAuthorization:Bearer $security_token",
            'method' => 'GET', 
            'content' => ''
		)
	);
    $context_get = stream_context_create($options_get);
    $url = 'https://www.smartcallesb.co.za:8101/webservice/smartload/networks';
    $result_networks = file_get_contents($url,false, $context_get);

    if ($result_networks === FALSE)
    {
        return 'There was an issue with the products fetch';
    }
    else 
    {
        
        $utf_string = utf8_encode($result_networks);
        //echo $utf_string;
        
        $json_products = json_decode($utf_string);
        //var_dump($json_products);
        
        $the_array = array();
        $i = 0;
        foreach ($json_products->networks as $row)
        {
            
            if ($row->description == $network)
            {
            	
                $the_over_array = $row->productTypes;
                //echo "The overall array";
				//var_dump($the_over_array);
                foreach ($the_over_array as $product_row)
                {
                    if ($product_row->code == $prod_type)
                    {
                    	foreach ($product_row->products as $product_item)
						{
							$item = array('id' => $product_item->id,'name' => $product_item->name,'thevalue' => $product_item->maxAmount) ;
							array_push($the_array, $item);
							//array_push_assoc($the_array, 'item-$i' , $item);						
						}
                    }
                }
            }
        }
        //var_dump($the_array);
        $out = utf8ize($the_array);
        $no_dup_out = array_unique($out,SORT_REGULAR);
		$response = array('items'=>$no_dup_out);
		//echo "The output:";
		//var_dump($out);

        $response_json = json_encode($response, JSON_FORCE_OBJECT );
		echo $response_json;
		//var_dump($response_json);
		//echo 'Error: ' .json_last_error();
        // var_dump($response_json);

       //TODO log transaction 
       /* $sql = "INSERT INTO tokens (token) VALUES ('" . $security_json->accessToken ."')";

if($conn->query($sql) === TRUE)
        {
            echo "Security token added successfully.";
        }
        else
        {
            echo "Error: ".$sql."<br/>".$conn->error."<br/>";
        }
*/

       
        
        return $response_json;
  
    }
?>