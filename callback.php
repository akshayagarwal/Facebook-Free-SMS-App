<?php

require "src/dbSettings.php";

$app_id = '176592152413265';
$app_secret = 'secret';
$my_url = "http://yodigito.bitnamiapp.com/freesmsapp/callback.php";

$code = $_REQUEST['code'];

$token_url = "https://graph.facebook.com/oauth/access_token?"
       . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
       . "&client_secret=" . $app_secret . "&code=" . $code;

     $response = file_get_contents($token_url);
     $params = null;
     parse_str($response, $params);     
     
$pageUrl = "http://apps.facebook.com/yodigitofreesms?atoken=".$params['access_token'];
 echo("<script> top.location.href='" . $pageUrl . "'</script>");
?>
