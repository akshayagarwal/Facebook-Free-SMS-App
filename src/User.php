<?php

require_once("dbSettings.php");
require_once("class.smpp.php");

class User {
   
   
    function addNewUser($userData=array()) {
        //echo "Entered add new user";
        //echo print_r($userData);
        $id = $userData['id'];
        $msisdn = $userData['msisdn'];
        $fname = $userData['first_name'];
        $lname = $userData['last_name'];
        if ( $userData['gender']=="male" ) {
            $gender=0;
        }
        else
        $gender = 1;
        $bday = $userData['birthday'];
        $location = $userData['location'];
        $verification_code = rand(1000, 9999);
        
        $query = "INSERT INTO `users`(`fbid`, `msisdn`, `fname`, `lname`, `gender`, `bday`, `location`, `verification_code`, `verified`, `user_since`, `active`, `last_sent`, `counter`) VALUES ($id,'$msisdn','$fname','$lname',$gender,'$bday','$location',$verification_code,0,NULL,1,NULL,NULL)";
        //echo "<br>$query";
        $result = mysql_query($query);
        
        if ( $result ) {
            $this->sendVerificationCode($msisdn, $verification_code);
            return 0;
        }
        else 
            return -1;
   
    }
    
    function userExists( $fbid ) {
        $query = "SELECT fbid,verified FROM users WHERE fbid=$fbid";
        $result = mysql_query($query);
        if(mysql_num_rows($result)>0) {
            return TRUE;
        }
        return FALSE;
    }
    
    function makeInactive( $fbid ) {
        $query = "UPDATE users SET active=0 WHERE fbid=$fbid";
        $result = mysql_query($query);
        
        if ( $result ) {
            return 0;
        }
        else 
            return -1;
    }
    
    function makeActive( $fbid ) {
        $query = "UPDATE users SET active=1 WHERE fbid=$fbid";
        $result = mysql_query($query);
        
        if ( $result ) {
            return 0;
        }
        else 
            return -1;
    }
    
    function isActive( $fbid ) {
        $query = "SELECT active FROM users WHERE fbid=$fbid";
        $result = mysql_query($query);
        $active = mysql_fetch_row($result);
        return $active;
    }
    
    function sendVerificationCode( $msisdn, $code ) {        
        $message = "Thanks for registering with YoDigito free SMS App. Your registration code is $code";
        $delivery = $this->sendMessage("YoDigito", $msisdn, $message);
         if ( $delivery!=0 ) {
          $this->sendHttpSMS($msisdn, $message);
      }
    }
    
    function verifyUser( $fbid,$entered_code ) {
        echo "id = $fbid, code = $entered_code";
        $query = "SELECT verification_code FROM users WHERE fbid=$fbid";
        $result = mysql_query($query);
        $required_code = mysql_fetch_row($result);
        //echo "required = $required_code[0]";
        echo print_r($required_code);
        if ( $entered_code == $required_code[0] ) {
            //echo "Codes match";
            $query = "UPDATE users SET verified=1 WHERE fbid=$fbid";
            $result = mysql_query($query);
            if ( $result ) {
                return 0;
            }
            else
                return 1;
        }
        else
            return -1;
    }
    
    function isVerified( $fbid ) {
        $query = "SELECT verified FROM users WHERE fbid=$fbid";
        $result =  mysql_query($query);
        $verified = mysql_fetch_row($result);
        //echo "is verified returns $verified[0]";
        if($verified[0]==1) {
            return TRUE;
        }
        return FALSE;
    }
    
    function isThrottled( $fbid ) {
        $query = "SELECT last_sent FROM users WHERE fbid=$fbid";
        $result = mysql_query($query);
        $last_sent = mysql_fetch_row($result);
        $today = time();
        $datediff = $today - $last_sent;
        if ( floor($datediff/(60*60*24)) <=1 ) {
            $query = "SELECT counter FROM users WHERE fbid=$fbid";
            $result = mysql_query($query);
            $count = mysql_fetch_row($result);
            
            $query = "SELECT value FROM config WHERE id=2";
            $result = mysql_query($query);
            $maxlimit = mysql_fetch_row($result);
            
            if ($count < $maxlimit) {
                return false;
            }
            else 
                return true;
        }
        return false;
    }
    
    function updateCounter ( $fbid, $count ) {
        $now = time();
        $query = "UPDATE users SET last_sent=$now,counter=counter+$count WHERE fbid=$fbid";
        $result = mysql_query($query);
       if ($result)
       {
           return 0;
       }
       else
           return -1;
    }
    
    function addFooterMessage ( ) {
        $footer = "-via www.yodigito.com";
        return $footer;
    }
    
    function sendUserMessage ( $fbid,$dst = array(),$message ) {
        
        //checks
        if(strlen($message)>135){
            $response = $this->prepareResponse("MSGLONG");
            return $response;
        }
        if($message==""){
            $response = $this->prepareResponse("MSGBLANK");
            return $response;
        }
        if(count($dst)>1000) {
            $response = $this->prepareResponse("DSTEXCEED");
            return $response;
        }
        if(count($dst)==0){
            $response = $this->prepareResponse("DSTBLANK");
            return $response;
        }
        
        
        $query = "SELECT msisdn FROM users WHERE fbid=$fbid";
        $result = mysql_query($query);
        $msisdn = mysql_fetch_row($result);
        $src = $msisdn[0];
        $message = $message."\n".$this->addFooterMessage();
        //echo "sendUsermessage from $src to $dst as $message";
        $s = new smpp();
        $s->debug=0;
         // $host,$port,$system_id,$password
        $s->open("8.8.8.8", 8888, "user", "pass");
        
        $count = 0;
        while (list(, $destination_addr) = each($dst)) {
           if(!(strlen($destination_addr)<10 || strlen($destination_addr)>13)) {
               if(strlen($destination_addr)==13) {
                   $destination_addr = "+".substr($destination_addr,1);
               }
            //echo "Sending from $src to $destination_addr as $message"; 
            $status = $s->submit_sm($src, $destination_addr, $message);
            if($status == 0) {
                $count++;
            }
           }
        }
        
            $this->addCount($fbid, $count);
            $response = $this->prepareResponse("SUCCESS",$count);

        $s->close();
        
        return $response;
    }
    
    function addCount($fbid,$count){
        $query="UPDATE users SET counter = counter + $count WHERE fbid = $fbid";
        $result = mysql_query($query);
    }
    
    function prepareResponse($type,$count=0) {
        switch ($type){
            case "SUCCESS":
                $html = "<div style='background:green;'><center><h2>$count messages have been sent successfully</h2></center><h4>Please note that currently we are unable to send messages to DND numbers</h4><h5>To check if your number is DND, please <a href='http://nccptrai.gov.in/nccpregistry/search.misc' target='_new'>click here</a></h5></div>";
                break;
            case "MSGBLANK":
                $html = "<div style='background:red;'><center><h3>Message field cannot be left blank</h3></div></center></div>";
               break;
            case "MSGLONG":
                $html = "<div style='background:red;'><center><h3>Message field cannot contain more than 140 characters</h3></div></center></div>";
                break;
            case "DSTBLANK":
                $html = "<div style='background:red;'><center><h3>Number(s) field cannot be left blank</h3></div></center></div>";
                break;
            case "DSTEXCEED":
                $html = "<div style='background:red;'><center><h3>Number(s) field cannot contain contain more than 1000 numbers at once</h3></div></center></div>";
                break;
            case "THROTTLED":
                $html = "<div style='background:red;'><center><h3>Sorry, You have reached your daily limit of 2000 messages. Kindly try again tomorrow</h3></div></center></div>";
                break;
            default:
                $html = "<div style='background:red;'><center><h3>Oops, something went wrong, please try again</h3></div></center></div>";
                break;
        }
     return $html;
    }
    
    function checkAbuse($msg) {
        
       
    }
    
    function sendMessage($src,$dst,$message) {
     
      $s = new smpp();
      $s->debug=1;

      // $host,$port,$system_id,$password
      $s->open("8.8.8.8", 8888, "user", "pass");

      // $source_addr,$destintation_addr,$short_message,$utf=0,$flash=0
      $delivery = $s->send_long($src, $dst, $message);

     
      /* To send unicode 
      $utf = true;
      $message = iconv('Windows-1256','UTF-16BE',$message);
      $s->send_long($src, $dst, $message, $utf);
      */  

      $s->close();
      return $delivery;
  }
  
  function sendHttpSMS ( $dst,$message ) {
    
        $username="user";
        $api_password="pass";
        $sender="YoDigito";
        $domain="sms.litglobal.co.in";
        $priority="1";// 1-Normal,2-Priority,3-Marketing
        $method="POST";

	$mobile=$dst;
        
	$username=urlencode($username);
	$password=urlencode($password);
	$sender=urlencode($sender);
	$message=urlencode($message);
	
	$parameters="username=$username&api_password=$api_password&sender=$sender&to=$mobile&message=$message&priority=$priority";

	if($method=="POST")
	{
		$opts = array(
		  'http'=>array(
			'method'=>"$method",
			'content' => "$parameters",
			'header'=>"Accept-language: en\r\n" .
					  "Cookie: foo=bar\r\n"
		  )
		);

		$context = stream_context_create($opts);

		$fp = fopen("http://$domain/pushsms.php", "r", false, $context);
	}
	else
	{
		$fp = fopen("http://$domain/pushsms.php?$parameters", "r");
	}

	$response = stream_get_contents($fp);
        echo $response;
	fpassthru($fp);
	fclose($fp);
  }
     
}
?>
