<?php
require_once('./global.php');
$logfile = '/var/www/testforum/vb/logttpush3.txt';
$str='<table align="center" border="1" cellpadding="5" cellspacing="0" style="text-align: center;">
  <tr>
    <th>pushdata</th>
    <th width="30%">time</th>
  </tr>';
$file = file($logfile);
foreach($file as $line => $content){
    $arr = json_decode($content,true);
    $time = $arr['pushtime'];
    $date = date('Y-m-d H:i:s',$time);
    $date = date('Y-m-d H:i:s',strtotime("$date+8 hour"));
    $str.='
  <tr>
    <td>'.$content.'</td>
    <td width="30%">'.$date.'</td>
  </tr>
';
}
$str.=' </table>';
echo $str;


