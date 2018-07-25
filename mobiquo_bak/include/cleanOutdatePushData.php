<?php

if (is_object($vbulletin->db))
{
    $expiry = $vbulletin->options['alert_outdate_period'];
    if(empty($expiry)) $expiry = "90";
    $outDateline = strtotime("-".$expiry." days");
    $vbulletin->db->query_write("DELETE FROM ".TABLE_PREFIX."tapatalk_push WHERE dateline < ".$outDateline);
    $deleted_rows = $vbulletin->db->affected_rows();
}

