<?php

require 'src/User.php';

function parsePageSignedRequest() {
    if (isset($_REQUEST['signed_request'])) {
      $encoded_sig = null;
      $payload = null;
      list($encoded_sig, $payload) = explode('.', $_REQUEST['signed_request'], 2);
      $sig = base64_decode(strtr($encoded_sig, '-_', '+/'));
      $data = json_decode(base64_decode(strtr($payload, '-_', '+/'), true));
      //print_r($data);
      return $data;
    }
    return false;
  }
  
  $signed_request = parsePageSignedRequest();
  $fbid = $signed_request->user_id;
  //print_r($signed_request);
  if(isset($_REQUEST['atoken'])) {
                $atoken = $_REQUEST['atoken'];
                header("Location: http://yodigito.bitnamiapp.com/freesmsapp/examples/example.php?fbid=$fbid&atoken=$atoken");
    }
    
    if($signed_request->page->liked) {
        $yoUser = new User();
        $fbid = $signed_request->user_id;
        //echo "fbid=$fbid";
        
        if($yoUser->userExists($fbid) && $yoUser->isVerified($fbid)) {
            header("Location: http://yodigito.bitnamiapp.com/freesmsapp/examples/messagesender.php?fbid=$fbid");
        }
        else {
                $app_id = '176592152413265';
                $app_secret = 'secret';
                $my_url = "http://yodigito.bitnamiapp.com/freesmsapp/callback.php";

                $dialog_url = "http://www.facebook.com/dialog/oauth?client_id=" 
                   . $app_id . "&redirect_uri=" . urlencode($my_url)."&scope=user_birthday,user_location,email,publish_stream";

                 echo("<script> top.location.href='" . $dialog_url . "'</script>");   
            }
    }
  
?>


<html>

    <head>
        <title> yoDigito Free SMS App </title>
    </head>
    
    <body>
<img src='http://yodigito.bitnamiapp.com/freesmsapp/facebook_page2.jpg'>
  </body>
</html>
