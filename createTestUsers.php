<?php
require 'src/facebook.php';
require 'src/User.php';

// Create our Application instance (replace this with your appId and secret).
$facebook = new Facebook(array(
  'appId'  => '176592152413265',
  'secret' => 'secret',
));

$params =  array();
$params['installed']="false";
$params['name']="Vijay Singh";
$params['method']="post";
$params['access_token']="token";
try {
$response = $facebook->api("/176592152413265/accounts/test-users",$params);
}
catch (FacebookApiException $e) {
    error_log($e);
}

error_log($response);

echo print_r($response);
        
?>
