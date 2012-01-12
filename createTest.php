<?php
$app_id = '176592152413265';
   $app_secret = 'secret';
   $app_url = 'http://apps.facebook.com/yodigitofreesms';
   $access_token = 'secret';
  header("Location: https://graph.facebook.com/$app_id/accounts/test-users?installed=false&name=Mani&method=post&access_token=$access_token");
?>
