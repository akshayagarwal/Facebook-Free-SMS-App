<?php


define(CM_BIND_TRANSMITTER, 0x00000002);
define(CM_SUBMIT_SM, 0x00000004);
define(CM_SUBMIT_MULTI, 0x00000021);
define(CM_UNBIND, 0x00000006);
define(CM_ENQUIRELINK, 0x00000015);

/*
  $src  = "9970103544"; // or text 
  $dst = array();
  $dst[0] = "7276023270";
  $dst[1] = "7588439547";
  $tolist = "7276023270,7588439547";
  $dest = "7276023270";
  $message = "With changed";
 
  $s = new smpp();
  $s->debug=1;

  // $host,$port,$system_id,$password
  $s->open("174.36.194.138", 8888, "leadind", "Le@d0");

  // $source_addr,$destintation_addr,$short_message,$utf=0,$flash=0
  $s->submit_multi($src, $dst, $message);
  
  //$s->send_long($src,$dst[0], $message);
  //$s->send_long($src,$dst[1], $message);
  //$s->send_long($src,$dst[0], $message);
  
  $s->close();

  /* To send unicode 
  $utf = true;
  $message = iconv('Windows-1256','UTF-16BE',$message);
  $s->send_long($src, $dst, $message, $utf);
  */  




class smpp {

  var $socket=0;
  var $seq=0;
  var $debug=0;
  var $data_coding=0;
  var $timeout = 10;

  //////////////////////////////////////////////////
  function send_pdu($id,$data) {

    // increment sequence
    $this->seq +=1;
    // PDU = PDU_header + PDU_content
    $pdu = pack('NNNN', strlen($data)+16, $id, 0, $this->seq) . $data;
    // send PDU
    //echo print_r($pdu);
    fputs($this->socket, $pdu);
    //$this->ExpectPDU($this->seq);
   
// Get response length
    $data = fread($this->socket, 4);
    if($data==false) die("\nSend PDU: Connection closed!");
    $tmp = unpack('Nlength', $data);
    $command_length = $tmp['length'];
    if($command_length<12) return;

    // Get response 
    $data = fread($this->socket, $command_length-4);
    $pdu = unpack('Nid/Nstatus/Nseq', $data);
    if($this->debug) print "\n< R PDU (id,status,seq): " .join(" ",$pdu) ;

    return $pdu;
  }


  //////////////////////////////////////////////////
  function open($host,$port,$system_id,$password) {

    // Open the socket
    $this->socket = fsockopen($host, $port, $errno, $errstr);
    if ($this->socket===false)
       die("$errstr ($errno)<br />");
    //if (function_exists('stream_set_timeout'))
       //stream_set_timeout($this->socket, $this->timeout); // function exists for php4.3+
    if($this->debug) print "\n> Connected" ;


    // Send Bind operation
    $data  = sprintf("%s\0%s\0", $system_id, $password); // system_id, password 
    $data .= sprintf("%s\0%c", "smpp", 0x34);  // system_type, interface_version
    $data .= sprintf("%c%c%s\0", 5, 0, ""); // addr_ton, addr_npi, address_range 

    $ret = $this->send_pdu(2, $data);
    if($this->debug) print "\n> Bind done!" ;

    return ($ret['status']==0);
  }


  //////////////////////////////////////////////////
  function submit_sm($source_addr,$destintation_addr,$short_message,$optional='') {

    $data  = sprintf("%s\0", "smpp"); // service_type
    $data .= sprintf("%c%c%s\0", 0,0,$source_addr); // source_addr_ton, source_addr_npi, source_addr
    $data .= sprintf("%c%c%s\0", 1,1,$destintation_addr); // dest_addr_ton, dest_addr_npi, destintation_addr
    $data .= sprintf("%c%c%c", 0,0,0); // esm_class, protocol_id, priority_flag
    $data .= sprintf("%s\0%s\0", "",""); // schedule_delivery_time, validity_period
    $data .= sprintf("%c%c", 0,0); // registered_delivery, replace_if_present_flag
    $data .= sprintf("%c%c", $this->data_coding,0); // data_coding, sm_default_msg_id
    $data .= sprintf("%c%s", strlen($short_message), $short_message); // sm_length, short_message
    $data .= $optional;

    $ret = $this->send_pdu(0x00000004, $data);
    return $ret['status'];
  }


  //////////////////////////////////////////////////
  function close() {

    $ret = $this->send_pdu(6, "");
    fclose($this->socket);
    return true;
  }


  //////////////////////////////////////////////////
  function send_long($source_addr,$destintation_addr,$short_message,$utf=0,$flash=0) {

    if($utf)
      $this->data_coding=0x08;

    if($flash)
      $this->data_coding=$this->data_coding | 0x10;


    $size = strlen($short_message);
    if($utf) $size+=20;

    if ($size<160) { // Only one part :)
      $delivery = $this->submit_sm($source_addr,$destintation_addr,$short_message);
    } else { // Multipart
      $sar_msg_ref_num =  rand(1,255);
      $sar_total_segments = ceil(strlen($short_message)/130);

      for($sar_segment_seqnum=1; $sar_segment_seqnum<=$sar_total_segments; $sar_segment_seqnum++) {
        $part = substr($short_message, 0 ,130);
        $short_message = substr($short_message, 130);

        $optional  = pack('nnn', 0x020C, 2, $sar_msg_ref_num);
        $optional .= pack('nnc', 0x020E, 1, $sar_total_segments);
        $optional .= pack('nnc', 0x020F, 1, $sar_segment_seqnum);

        if ($this->submit_sm($source_addr,$destintation_addr,$part,$optional)===false)
           return false;
      }
    }


   return $delivery;

  }
  
  function send_multi($source_addr,$toList,$short_message,$optional='') {
      $desintation_arr = array();
      $destination_arr = explode(",", $tolist);
      echo "Destination array is ".print_r($destination_arr);
      $this->submit_multi($source_addr, $destination_arr, $short_message);
  }
        

function submit_multi($source_addr,$destination_arr = array(),$short_message,$optional='') {
    
    $number_destinations = count($destination_arr);
    //echo "Total destinations = $number_destinations";
    //reset($destintation_arr);

    $data  = sprintf("%s\0", "smpp"); // service_type
    $data .= sprintf("%c%c%s\0", 0,0,$source_addr); // source_addr_ton, source_addr_npi, source_addr
    $data .= sprintf("%c", $number_destinations ); // number_of_dests
    
    //while (list(, $destination_addr) = each($destination_arr)) {
        //$destination_addr .= chr(0);
        //$dest_len = strlen($destination_addr);
        
        $data .= sprintf("%c%c%c%s\0",1, 1, 1, "7276023270");//dest_flag, dest_addr_ton, dest_addr_npi, dest_addr
        $data .= sprintf("%c%c%c%s\0",1, 1, 1, "7588439547");//dest_flag, dest_addr_ton, dest_addr_npi, dest_addr
        //}
                 
    $data .= sprintf("%c%c%c", 0,0,0); // esm_class, protocol_id, priority_flag
    $data .= sprintf("%s\0%s\0", "",""); // schedule_delivery_time, validity_period
    $data .= sprintf("%c%c", 0,0); // registered_delivery, replace_if_present_flag
    $data .= sprintf("%c%c", $this->data_coding,0); // data_coding, sm_default_msg_id
    $data .= sprintf("%c%s", strlen($short_message), $short_message); // sm_length, short_message
    $data .= $optional;
    
    $ret = $this->send_pdu(0x00000021, $data);
    return $ret['status'];
  }


  
  
  // Copied 
  
  function SendMulti($tolist, $text, $unicode = false)
{

$service_type = "smpp";
$source_addr = "9970103544";
//default source TON and NPI for international sender
$source_addr_ton = 0;
$source_addr_npi = 0;


$dest_addr_ton = 1;
$dest_addr_npi = 1;

$destination_arr = array();

$destination_arr[0] = "7276023270";
$destination_arr[1] = "7588439547";

$esm_class =0;
$protocol_id = 0;
$priority_flag = 0;
$schedule_delivery_time = "";
$validity_period = "";
$registered_delivery_flag = 0;
$replace_if_present_flag = 0;
$data_coding = 0;
$sm_default_msg_id = 0;

$short_message = $text;
$status = $this->SendSubmitMulti($service_type, $source_addr_ton, $source_addr_npi, $source_addr, $dest_addr_ton, $dest_addr_npi, $destination_arr, $esm_class, $protocol_id, $priority_flag, $schedule_delivery_time, $validity_period, $registered_delivery_flag, $replace_if_present_flag, $data_coding, $sm_default_msg_id, $sm_length, $short_message);
if ($status != 0) {
$result = false;
}
return $result;
}

function SendSubmitMulti($service_type, $source_addr_ton, $source_addr_npi, $source_addr, $dest_addr_ton, $dest_addr_npi, $destination_arr = array(), $esm_class, $protocol_id, $priority_flag, $schedule_delivery_time, $validity_period, $registered_delivery_flag, $replace_if_present_flag, $data_coding, $sm_default_msg_id, $sm_length, $short_message)
{
$service_type = $service_type . chr(0);
$service_type_len = strlen($service_type);
$source_addr = $source_addr . chr(0);
$source_addr_len = strlen($source_addr);
$number_destinations = count($destination_arr);
$spec = "a{$service_type_len}cca{$source_addr_len}c";
$pdu = pack($spec,
$service_type,
$source_addr_ton,
$source_addr_npi,
$source_addr,
$number_destinations
);

$dest_flag = 1;
reset($destination_arr);
while (list(, $destination_addr) = each($destination_arr)) {
$destination_addr .= chr(0);
$dest_len = strlen($destination_addr);
$spec = "ccca{$dest_len}";
$pdu .= pack($spec, $dest_flag, $dest_addr_ton, $dest_addr_npi, $destination_addr);
}
$schedule_delivery_time = $schedule_delivery_time . chr(0);
$schedule_delivery_time_len = strlen($schedule_delivery_time);
$validity_period = $validity_period . chr(0);
$validity_period_len = strlen($validity_period);
$message_len = $sm_length;
$spec = "ccca{$schedule_delivery_time_len}a{$validity_period_len}ccccca{$message_len}";

$pdu .= pack($spec,
$esm_class,
$protocol_id,
$priority_flag,
$schedule_delivery_time,
$validity_period,
$registered_delivery_flag,
$replace_if_present_flag,
$data_coding,
$sm_default_msg_id,
$sm_length,
$short_message);


$this->debug("\nMulti PDU: ");
for ($i = 0; $i < strlen($pdu); $i++) {
if (ord($pdu[$i]) < 32) $this->debug("."); else $this->debug($pdu[$i]);
}
$this->debug("\n");


$status = $this->SendPDU(0x00000021, $pdu);
return $status;
}

function SendPDU($command_id, $pdu)
{
$this->seq +=1;
$length = strlen($pdu) + 16;
$header = pack("NNNN", $length, $command_id, 0, $this->seq);
/*
$this->debug("Sending PDU, len == $length\n");
$this->debug("Sending PDU, header-len == " . strlen($header) .  "\n");
$this->debug("Sending PDU, command_id == " . $command_id  .  "\n"); */
fwrite($this->socket, $header.$pdu, $length);
$status = $this->ExpectPDU($this->seq);

return $status;
}

function ExpectPDU($our_sequence_number)
{
do {
$this->debug("Trying to read PDU.\n");
if (feof($this->socket)) {
$this->debug("Socket was closed.!!\n");
}
$elength = fread($this->socket, 4);
if (empty($elength)) {
$this->debug("Connection lost.\n");
return;
}
extract(unpack("Nlength", $elength));
$this->debug("Reading PDU     : $length bytes.\n");
$stream = fread($this->socket, $length - 4);
$this->debug("Stream len      : " . strlen($stream) . "\n");
extract(unpack("Ncommand_id/Ncommand_status/Nsequence_number", $stream));
$command_id &= 0x0fffffff;
$this->debug("Command id      : $command_id.\n");
$this->debug("Command status  : $command_status.\n");
$this->debug("sequence_number : $sequence_number.\n");
$pdu = substr($stream, 12);
switch ($command_id) {
case CM_BIND_TRANSMITTER:
$this->debug("Got CM_BIND_TRANSMITTER_RESP.\n");
$spec = "asystem_id";
extract($this->unpack2($spec, $pdu));
$this->debug("system id       : $system_id.\n");
break;
case CM_UNBIND:
$this->debug("Got CM_UNBIND_RESP.\n");
break;
case CM_SUBMIT_SM:
$this->debug("Got CM_SUBMIT_SM_RESP.\n");
if ($command_status == 0) {
$spec = "amessage_id";
extract($this->unpack2($spec, $pdu));
$this->debug("message id      : $message_id.\n");
}
break;
case CM_SUBMIT_MULTI:
$this->debug("Got CM_SUBMIT_MULTI_RESP.\n");
$spec = "amessage_id/cno_unsuccess/";
extract($this->unpack2($spec, $pdu));
$this->debug("message id      : $message_id.\n");
$this->debug("no_unsuccess    : $no_unsuccess.\n");
break;
case CM_ENQUIRELINK:
$this->debug("GOT CM_ENQUIRELINK_RESP.\n");
break;
default:
$this->debug("Got unknown SMPP pdu.\n");
break;
}
$this->debug("\nReceived PDU: ");
for ($i = 0; $i < strlen($stream); $i++) {
if (ord($stream[$i]) < 32) $this->debug("(" . ord($stream[$i]) . ")"); else $this->debug($stream[$i]);
}
$this->debug("\n");
} while ($sequence_number != $our_sequence_number);
return $command_status;
}

function debug($str)
{
if ($this->debug) {
echo $str;
}

}

function unpack2($spec, $data)
{
$res = array();
$specs = explode("/", $spec);
$pos = 0;
reset($specs);
while (list(, $sp) = each($specs)) {
$subject = substr($data, $pos);
$type = substr($sp, 0, 1);
$var = substr($sp, 1);
switch ($type) {
case "N":
$temp = unpack("Ntemp2", $subject);
$res[$var] = $temp["temp2"];
$pos += 4;
break;
case "c":
$temp = unpack("ctemp2", $subject);
$res[$var] = $temp["temp2"];
$pos += 1;
break;
case "a":
$pos2 = strpos($subject, chr(0)) + 1;
$temp = unpack("a{$pos2}temp2", $subject);
$res[$var] = $temp["temp2"];
$pos += $pos2;
break;
}
}
return $res;
}

}

?>