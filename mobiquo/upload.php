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
if(isset($_GET['checkAccess']))
{
	echo "yes";
	exit;
}
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	showTestScreen();
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
// ####################### SET PHP ENVIRONMENT ###########################

@set_time_limit(0);

define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));

error_reporting(0);
$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

@ob_start();

require_once("./include/common.php");

define('SCRIPT_ROOT', get_root_dir());

chdir(SCRIPT_ROOT);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newattachment');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();
$_POST['f'] = $_POST['forum_id'];
$_REQUEST['f'] = $_REQUEST['forum_id'];
// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_file.php');
if(isset($vbulletin) && $vbulletin->userinfo['userid'] != 0){
    header('Mobiquo_is_login:true');
} else {
    header('Mobiquo_is_login:false');
}

$method = $_POST['method_name'];

$server_param = array(
    "upload_attach" => array(
        "function" => "upload_attach_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'no parameters matched'
    ),

    "upload_avatar" => array(
        "function" => "upload_avatar_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'no parameters matched'
    ),
);

$rpcServer = new xmlrpc_server($server_param,false);
$xml = new xmlrpcmsg($method);
$request = $xml->serialize();
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding ='UTF-8';
$response = $rpcServer->service($request);


function upload_attach_func()
{
    global $vbulletin, $db, $forumperms, $permissions, $foruminfo;

    $vbulletin->input->clean_array_gpc('r', array(
        'group_id' => TYPE_UINT,
    ));

    $attachmentid = 0;

    $vbulletin->input->clean_gpc('f', 'attachment', TYPE_FILE);

    $group_id = $_POST['group_id'];

    if (!$vbulletin->userinfo['userid']) // Guests can not post attachments
    {
        $return = array(20, 'You need login to upload image.');
        return return_fault($return);
    }

    if(isset($group_id) && $group_id != null && strlen($group_id) == 32 && preg_match('/[\w]{32}/', $group_id)){
        $posthash  = $group_id;
    } else {
        $posthash  = md5(TIMENOW . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);
    }
    $forumperms = fetch_permissions($foruminfo['forumid']);

    // No permissions to post attachments in this forum or no permission to view threads in this forum.
    if (empty($vbulletin->userinfo['attachmentextensions']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
        $return = array(20, 'You do not have permission to post attachment in this forum');
        return return_fault($return);
    }

    if ((!$postid AND !$foruminfo['allowposting']) OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
        $return = array(20, 'You can not post in this forum');
        return return_fault($return);
    }

    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) && !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])) // newthread.php
    {
        $return = array(20, 'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }

    $_POST['add_file'] = true;

    $parentattach = '';
    $parentclickattach = '';
    $new_attachlist_js = '';

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    $show['errors'] = false;

    $currentattaches = $db->query_first("
        SELECT COUNT(*) AS count
        FROM " . TABLE_PREFIX . "attachment
        WHERE posthash = '$posthash'
            AND userid = " . $vbulletin->userinfo['userid']
    );
    $attachcount = $currentattaches['count'];

    if ($postid)
    {
        $currentattaches = $db->query_first("
            SELECT COUNT(*) AS count
            FROM " . TABLE_PREFIX . "attachment
            WHERE postid = $postid
        ");
        $attachcount += $currentattaches['count'];
        $show['postowner'] = true;
        $attach_username = $postinfo['username'];
    }
    else
    {
        $show['postowner'] = false;
        $attach_username = $vbulletin->userinfo['username'];
    }

    if (!$foruminfo['allowposting'] AND !$attachcount)
    {
        $return = array(20, 'security error (user may not have permission to access this feature.)');
        return return_fault($return);
    }

    $errors = array();
    require_once(DIR."/includes/class_upload.php");
    require_once(DIR.'/includes/class_image.php');

    $postinfo = array('posthash' => $posthash);

    // check for any funny business
    $filecount = 1;
    if (!empty($vbulletin->GPC['attachment']['tmp_name']))
    {
        foreach ($vbulletin->GPC['attachment']['tmp_name'] AS $filename)
        {
            if (!empty($filename))
            {
                if ($filecount > $vbulletin->options['attachboxcount'])
                {
                    @unlink($filename);
                }
                $filecount++;
            }
        }
    }

    // These are created each go around to insure memory has been freed
    $attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_ARRAY);
    $upload = new vB_Upload_Attachment($vbulletin);
    $image =& vB_Image::fetch_library($vbulletin);

    $upload->data =& $attachdata;
    $upload->image =& $image;
    if ($uploadsum > 1)
    {
        $upload->emptyfile = false;
    }

    $upload->foruminfo =& $foruminfo;
    $upload->postinfo =& $postinfo;

    $attachment = array(
        'name'     =>& $vbulletin->GPC['attachment']['name'][0],
        'tmp_name' =>& $vbulletin->GPC['attachment']['tmp_name'][0],
        'error'    =>& $vbulletin->GPC['attachment']['error'][0],
        'size'     =>& $vbulletin->GPC['attachment']['size'][0],
    );

    if (!$foruminfo['allowposting'])
    {
        $error = $vbphrase['this_forum_is_not_accepting_new_attachments'];
        $errors[] = array(
            'filename' => $attachment['name'],
            'error'    => $error
        );
    }
    else if ($vbulletin->options['attachlimit'] AND $attachcount > $vbulletin->options['attachlimit'])
    {
        $error = construct_phrase($vbphrase['you_may_only_attach_x_files_per_post'], $vbulletin->options['attachlimit']);
        $errors[] = array(
            'filename' => $attachment['name'],
            'error'    => $error
        );
    }
    else
    {
        if ($attachmentid = $upload->process_upload($attachment))
        {
            if ($vbulletin->userinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
            {
                $postinfo['attachmentid'] =& $attachmentid;
                $postinfo['forumid'] =& $foruminfo['forumid'];
                require_once(DIR . '/includes/functions_log_error.php');
                log_moderator_action($postinfo, 'attachment_uploaded');
            }
        }
        else
        {
            $attachcount--;
        }

        if ($error = $upload->fetch_error())
        {
            $errors[] = array(
                'filename' => is_array($attachment) ? $attachment['name'] : $attachment,
                'error'    => $error,
            );
        }
    }

    if (!empty($errors))
    {
        $errorlist = '';
        foreach ($errors AS $error)
        {
            $filename = htmlspecialchars_uni($error['filename']);
            $errorlist .= $error['error'];
        }

        return new xmlrpcresp(new xmlrpcval(array(
            'attachment_id' => new xmlrpcval(0, 'string'),
            'group_id'      => new xmlrpcval('', 'string'),
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(mobiquo_encode($errorlist), 'base64')
        ), 'struct'));
    }
    else
    {
        return new xmlrpcresp(new xmlrpcval(array(
            'attachment_id' => new xmlrpcval($attachmentid, 'string'),
            'group_id'      => new xmlrpcval($posthash, 'string'),
            'result'        => new xmlrpcval(true, 'boolean'),
            'result_text'   => new xmlrpcval('', 'base64')
        ), 'struct' ) );
    }
}


function upload_avatar_func()
{
    global $vbulletin, $db, $forumperms,$permissions, $foruminfo;

    if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmodifyprofile']))
    {
        $return = array(20, 'You can not modify your profile');
        return return_fault($return);
    }

    if (!$vbulletin->options['avatarenabled'])
    {
        $return = array(20, 'Avatar is not enabled in this forum');
        return return_fault($return);
    }

    $vbulletin->GPC['avatarid'] = 0;

    if ($vbulletin->GPC['avatarid'] == 0 AND ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
    {
        $vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

        // begin custom avatar code
        require_once(DIR . '/includes/class_upload.php');
        require_once(DIR . '/includes/class_image.php');

        $upload = new vB_Upload_Userpic($vbulletin);

        $upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
        $upload->image =& vB_Image::fetch_library($vbulletin);
        $upload->maxwidth = $vbulletin->userinfo['permissions']['avatarmaxwidth'];
        $upload->maxheight = $vbulletin->userinfo['permissions']['avatarmaxheight'];
        $upload->maxuploadsize = $vbulletin->userinfo['permissions']['avatarmaxsize'];
        $upload->allowanimation = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;

        if (!$upload->process_upload($vbulletin->GPC['avatarurl']))
        {
            $errors = $upload->fetch_error();
            $errorlist = '';
            foreach ($errors AS $error)
            {
                $filename = htmlspecialchars_uni($error['filename']);
                $errorlist .= $error['error'];
            }

            return new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(mobiquo_encode($errorlist), 'base64')
            ), 'struct' ) );

        }
    }

    // init user data manager
    $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
    $userdata->set_existing($vbulletin->userinfo);
    $userdata->set('avatarid', $vbulletin->GPC['avatarid']);
    $userdata->save();

    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'result_text' =>  new xmlrpcval('', 'base64')
    ), 'struct'));
}

function showTestScreen()
{
	echo "Attachment Upload Interface for Tapatalk Application<br>";
	echo "<br>
<a href=\"https://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a><br>
For more details, please visit <a href=\"https://tapatalk.com\" target=\"_blank\">https://tapatalk.com</a>";
	exit;
}