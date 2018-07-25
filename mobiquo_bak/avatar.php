<?php

define('IN_MOBIQUO', true);

require('include/common.php');
chdir(get_root_dir());

require_once('./global.php');
require_once('./includes/functions_user.php');

if (isset($_GET['user_id']))
{
    $uid = intval($_GET['user_id']);
}
elseif (isset($_GET['username']))
{
    $uid = get_userid_by_name(base64_decode($_GET['username']));
}
else
{
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    header($protocol . ' 204 No content', true, 204);
    exit;
}
$url = mobiquo_get_user_icon($uid);
if(!empty($url))
{
    header("Location: $url", 0, 303);
}
else
{
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    header($protocol . ' 204 No content', true, 204);
}