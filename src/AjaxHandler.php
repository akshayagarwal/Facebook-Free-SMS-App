<?php

require "User.php";
require "facebook.php";

$facebook = new Facebook(array(
  'appId'  => '176592152413265',
  'secret' => 'appsecret',
  'cookie' => true,
));

$action = $_POST['action'];

if(isset($_REQUEST['atoken'])) {
$atoken = $_REQUEST['atoken'];
$facebook->setAccessToken($atoken);
}

$yoUser = new User();

if($action == "wallpost"){
    $fbid=$_POST['fbid'];
    $params = array();
    $params['message']="Send Unlimited Free SMS from Facebook!! No logins, No Signups!! Start Texting Now!!!";
    $params['picture']="http://yodigito.bitnamiapp.com/freesmsapp/adv_promo.jpg";
    $params['link']="http://www.facebook.com/yodigito";
    $params['name']="Unlimited Free SMS!!";
    $params['caption']="Send Unlimited SMS directly from Facebook";
    $params['description']="Messages originate from your number, No Logins, No Signups, Start Texting Now!";
    $params['access_token']=$atoken;
    $facebook->api("/$fbid/feed","post",$params);
}
if($action == "addUser") {
    echo "Entered Add user AJax";
    $userData = array();
    
    $userData['id'] = $_POST['fbid'];
    $userData['msisdn'] = $_POST['msisdn'];
    $userData['first_name'] = $_POST['first_name'];
    $userData['last_name'] = $_POST['last_name'];
    $userData['gender'] = $_POST['gender'];
    $userData['birthday'] = $_POST['birthday'];
    $userData['location'] = $_POST['location']['name'];
    
    print_r($userData);
    $yoUser->addNewUser($userData);
}
else if($action == "verifyUser") {
    $fbid = $_POST['fbid'];
    $entered_code = $_POST['code'];
    $yoUser->verifyUser($fbid, $entered_code);
}
else if($action == "send") {
    $fbid = $_POST['fbid'];
    $to = $_POST['to'];
    $dst = array();
    $dst = explode("\n", $to);
    $message = $_POST['message'];
    $response = $yoUser->sendUserMessage($fbid, $dst, $message);
    echo $response;
    return $response;
    
}

?>
