<?php

$dbuser = "user";
$dbpass = "password";
$dbname = "dbname";

$link_id=@mysql_connect("localhost",$dbuser,$dbpass);
@mysql_select_db($dbname);

?>
