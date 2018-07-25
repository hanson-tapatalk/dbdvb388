<?php
/*======================================================================*\
 || #################################################################### ||
 || # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Quoord Systems Ltd.                                              # ||
 || # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
 || #################################################################### ||
 \*======================================================================*/

define('IN_MOBIQUO', true);
define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));
define('MBQ_PATH', (($getcwd = getcwd()) ? $getcwd : '.') . '/');
define('MBQ_3RD_LIB_PATH', (($getcwd = getcwd()) ? $getcwd : '.') . '/include/');
require_once(MBQ_3RD_LIB_PATH. "ExceptionHelper.php");

if (isset($_SERVER['HTTP_APP_VAR'] ) && $_SERVER['HTTP_APP_VAR'])
    @header('App-Var: '.$_SERVER['HTTP_APP_VAR']);

if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0', '<'))
{
    @set_magic_quotes_runtime(0);
}

if (version_compare(PHP_VERSION, '5.0.0', '>=')) {
    include("./include/xmlrpc.inc");
    include("./include/xmlrpcs.inc");
}
else
{
    include("./include/xmlrpc.legacy.inc");
    include("./include/xmlrpcs.legacy.inc");
}
$_POST['xmlrpc'] = 'true';
include("./include/classTTConnection.php");
include("./include/classTTCipherEncrypt.php");
include("./include/pretreat.php");


if(isset($_POST['session']) && isset($_POST['api_key']) && isset($_POST['subject']) && isset($_POST['body']) || isset($_POST['email_target']))
{
    include(CWD1."/functions/invitation.php");
}

@ob_start();

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require(CWD1."/include/common.php");
require(CWD1."/server_define.php");
require(CWD1.'/env_setting.php');
require(CWD1.'/xmlrpcresp.php');

define('SCRIPT_ROOT', get_root_dir());

chdir(SCRIPT_ROOT);

if(in_array($request_method, array('get_config', 'login', 'sign_in', 'register', 'forget_password','prefetch_account')))
{
    define('THIS_SCRIPT', 'register');
    define('CSRF_PROTECTION', false);
    define('CSRF_SKIP_LIST', 'login');
}


$tt_server_post_method = array('set_api_key', 'user_subscription', 'push_content_check', 'reset_push_slug', 'set_forum_info', 'get_contact', 'sync_user');
$tt_server_get_method = array();
if(isset($_POST['method_name']) && in_array($_POST['method_name'], $tt_server_post_method)){
    include CWD1 . '/include/function_server.php';
    $function = $_POST['method_name'].'_func';
}

if(isset($_GET['method_name']) && in_array($_GET['method_name'], $tt_server_get_method)){
    include CWD1 . '/include/function_server.php';
    $function = $_GET['method_name'].'_func';
}

if (isset($function) && function_exists($function)){
    $function();
    exit;
}



if ($function_file_name && isset($server_param[$request_method]))
    require(CWD1.'/functions/'.$function_file_name.'.php');
else{
    require_once('./global.php');
    return_fault("Request function $request_method does not exist!");
}
if(!$mobiquo_is_login_processed)
{
    if (strpos($request_method, 'm_') !== 0 || strpos($request_method, 'm_get') === 0)
    {
        header('Mobiquo_is_login:'.(isset($vbulletin) && $vbulletin->userinfo['userid'] != 0 ? 'true' : 'false'));
    }
}
if (!isset($tt_config))
{
    require_once(CWD1.'/config/conf_init.php');
    $mobiquo_config = new mobiquo_config();
    $tt_config = $mobiquo_config->get_config();
    $GLOBALS['tt_config'] = $tt_config;
}

// check if moderation function is allowed
if (strpos($request_method, 'm_') === 0 && !$tt_config['allow_moderate'])
    return_fault('Moderation action is not allowed on this forum!');

if (strpos($request_method, 'm_') === 0 && $vbulletin->userinfo['userid'] == 0)
    return_fault();

if($tt_config['guest_okay'] == 0 && $vbulletin->userinfo['userid'] == 0 && $request_method != 'get_config' && $request_method != 'login' && $request_method != 'register' && $request_method != 'sign_in' && $request_method != 'prefetch_account' && $request_method != 'forget_password')
    return_fault();

if($tt_config['disable_search'] == 1){
    if($request_method == 'search_topic' or $request_method == 'search_post'){
        return_fault();
    }
}

if(!$tt_config['is_open'] && $request_method != 'logout_user' && $request_method != 'get_config')
    return_fault('Server is not available');

define('SHORTENQUOTE', isset($tt_config['shorten_quote']) ? $tt_config['shorten_quote'] : false);

if(!empty($tt_config['hide_forum_id']))
{
    foreach($tt_config['hide_forum_id'] as $h_forumid) {
        $vbulletin->userinfo['forumpermissions'][$h_forumid] = 655374;
    }
}


$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding ='UTF-8';
$rpcServer->service();

exit;

