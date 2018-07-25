<?php

defined('IN_MOBIQUO') or exit;
define('THIS_SCRIPT', 'register');
define('CSRF_PROTECTION', false);
define('CSRF_SKIP_LIST', 'login');

require_once ( dirname(__FILE__)."/common.php" );
require_once ( dirname(__FILE__)."/classTTJson.php" );

chdir(dirname(dirname(dirname(__FILE__))));

if(!defined('VB_AREA'))
{
    require_once( "./global.php" );
}
function reset_push_slug_func()
{
    global $vbulletin;

    $code = trim($_REQUEST['code']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'reset_push_slug');   
    $result = array( 'result' => false );
    if($response === true)
    {
        $query = "UPDATE ". TABLE_PREFIX . "tapatalk_push SET title = '0' WHERE userid = 0 LIMIT 1 ";
        $vbulletin->db->query_write($query);
        $result['result'] = true;
    }
    else if($response)
    {
        $result['result_text'] = $response;
    }
    $response = ($format == 'json') ? json_encode($result) : serialize($result);
    echo $response;
    exit;
}
function set_api_key_func()
{
    require_once(DIR . '/includes/adminfunctions.php');
    require_once(DIR . '/includes/adminfunctions_options.php');
    global $vbulletin;
    $code = trim($_REQUEST['code']);
    $key  = trim($_REQUEST['key']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_api_key');
    $result = false;
    if($response === true)
    {
        $vbulletin->input->clean_array_gpc('p', array(
            'setting'  => TYPE_ARRAY,
            'advanced' => TYPE_BOOL
        ));
        $vbulletin->GPC['setting'] = array('push_key' => $key);
        save_settings($vbulletin->GPC['setting']);
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
     );
    
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
}

/*
This function submits the basic user information required by the Trending Email program if the forum is configured to participate.
You change the trending email settings in Tapatalk forum owner page. For more information please visit Tapatalk.com.

Setting the flag 'allow_trending' to '0' in file mobiquo/config/config.txt will disable this function locally.
Note, if this function is disabled then the forum cannot participate in the Trending Email program until it is enabled locally and in the forum owners control panel.
*/
function sync_user_func()
{
    global $db, $vbulletin;
    $code = trim($_POST['code']);
    $start = intval(isset($_POST['start']) ? $_POST['start'] : 0);
    $limit = intval(isset($_POST['limit']) ? $_POST['limit'] : 1000);
    $format = trim($_POST['format']);

    require_once(CWD1.'/config/conf_init.php');
    $mobiquo_config = new mobiquo_config();
    $tt_config = $mobiquo_config->get_config();

    if (isset($tt_config['allow_trending']) && $tt_config['allow_trending'] == 1)
    {
        $api_key = trim($vbulletin->options['push_key']);
        if (preg_match('/[A-Z0-9]{32}/', $api_key))
        {
            $connection = new classTTConnection();
            $response = $connection->actionVerification($code, 'sync_user');
            if($response === true)
            {
                // Get cipher
                $cipher = new TT_Cipher();
                // Get users...
                $users = array();
                $users_result = $db->query_read_slave("
                    SELECT userid as uid, username , email , user.options , title as language, user.joindate, user.posts, user.lastactivity
                    FROM " . TABLE_PREFIX . "user as user
                    INNER JOIN " . TABLE_PREFIX . "language as lang
                    WHERE userid > $start and (user.languageid = lang.languageid or user.languageid = 0) and email <> ''
                    ORDER BY uid ASC LIMIT $limit
                ");
                while ($user = $db->fetch_array($users_result))
                {
                    $tuser = array(
                        'uid'           => $user['uid'],
                        'username'      => mobiquo_encode($user['username']),
                        'language'      => $user['language'],
                        'allow_email'   => ((intval($user['options'])&16) == 0)? false : true,
                        'encrypt_email' => base64_encode($cipher->encrypt($user['email'],$api_key)),
                        'reg_date'      => $user['joindate'],
                        'post_num'      => $user['posts'],
                        'last_active'   => $user['lastactivity'],
                    );
                    $users[] = $tuser;
                }
                $data = array(
                    'result'      => true,
                    'new_encrypt' => true,
                    'users'       => $users,
                );
            }
            else
            {
                $data = array(
                    'result' => false,
                    'error' => $response,
                );
            }
        }
        else
        {
            $data = array(
                'result' => false,
                'error' => 'Invalid API Key',
            );
        }
    }
    else
    {
        $data = array(
            'result' => false,
            'error' => 'Function disabled',
        );
    }
    
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}

function user_subscription_func(){
    global $db;

    $code = trim($_POST['code']);
    $uid = intval(trim($_POST['uid']));
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';

    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'user_subscription');

        $data = array( 'result' => false );
        if ($response !== true){
            $data['result_text'] = $response;
            echo ($format == 'json') ? json_encode($data) : serialize($data);
            exit;
        }

        $subsforumsQ = $db->query_read_slave("
            SELECT sf.forumid as fid, f.title_clean as name
            FROM " . TABLE_PREFIX . "subscribeforum as sf
            LEFT JOIN " . TABLE_PREFIX . "forum as f ON sf.forumid = f.forumid
            WHERE sf.userid = " . $uid
        );
        while ($forum = $db->fetch_array($subsforumsQ)){
            $data['forums'][] = $forum;
        }
        $substhreadQ = $db->query_read_slave("
            SELECT threadid
            FROM " . TABLE_PREFIX . "subscribethread
            WHERE userid = " . $uid
        );
        while ($thread = $db->fetch_array($substhreadQ)){
            $data['topics'][] = $thread['threadid'];
        }
        $data['result'] = true;
    }catch (Exception $e){
        $data = array(
                'result' => false,
                'result_text' => $e->getMessage(),
        );
    }
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}

function push_content_check_func(){
    global $vbulletin, $db;
    $code = trim($_POST['code']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';
    $data = unserialize(trim($_POST['data']));

    $result = array( 'result' => false );
    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'push_content_check');
        if ($response !== true){
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if(!isset($vbulletin->options['push_key']) || !isset($data['key']) || $vbulletin->options['push_key'] != $data['key']){
            $result['result_text'] = 'incorrect api key';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if (!isset($data['dateline']) || time() - intval($data['dateline']) > 86400){
            $result['result_text'] = 'time out';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        switch ($data['type']){
            case 'newtopic':
            case 'sub':
            case 'quote':
            case 'tag':
                $query = "
                    SELECT p.postid
                    FROM " . TABLE_PREFIX . "post AS p
                    WHERE p.postid={$data['subid']}
                        AND p.threadid={$data['id']}
                        AND p.userid={$data['authorid']}
                        AND p.dateline={$data['dateline']}
                ";
                break;
            case 'pm':
                $id = $data['id'];
                if (preg_match('/_(\d+)$/', $id, $matches)){
                    $id = $matches[1];
                }
                $query = "
                    SELECT pmt.pmtextid
                    FROM " . TABLE_PREFIX. "pmtext as pmt
                    WHERE pmt.pmtextid={$id}
                        AND pmt.fromuserid={$data['authorid']}
                        AND pmt.dateline={$data['dateline']}
                ";
                break;
        }
        if (isset($query) && !empty($query)){
            $query_result = $db->query_first($query);
            if (!empty($query_result)){
                $result['result']=true;
            }
        }
    }catch (Exception $e){
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;
}

function get_contact_func()
{
    global $vbulletin, $request_params;

    $code = trim($_POST['code']);
    $user_id  = trim($_POST['user_id']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';

    $data = array( 'result' => false );
    if (empty($code))
    {
        $data['result_text'] = 'Invalid code';
        echo ($format == 'json') ? json_encode($data) : serialize($data);
        exit;
    }

    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'get_contact');

    if($response !== true)
    {
        $data['result_text'] = $response;
        echo ($format == 'json') ? json_encode($data) : serialize($data);
        exit;
    }

    // Get cipher
    $cipher = new TT_Cipher();

    $api_key = trim($vbulletin->options['push_key']);
    if (!preg_match('/[A-Z0-9]{32}/', $api_key))
    {
        $data['result_text'] = 'Invalid api key.';
        echo ($format == 'json') ? json_encode($data) : serialize($data);
        exit;
    }

    if (!empty($user_id)){
        $user_ids = preg_split('/,/', $user_id);
        include_once get_root_dir().'includes/functions_misc.php';
        $all_languages = fetch_language_titles_array();
        foreach ($user_ids as $user_id){
            $userinfo = mobiquo_verify_id('user', $user_id, 0, 1);
            if (empty($userinfo)) continue;
            $activated = ($vbulletin->usergroupcache[$userinfo['usergroupid']]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']) && (!in_array($userinfo['usergroupid'], array(4,3)));
            $language_id = empty($userinfo['languageid']) ? $vbulletin->options['languageid'] : $userinfo['languageid'];
            $data['users'][] = array(
                'user_id'       => $userinfo['userid'],
                'display_name'  => basic_clean($userinfo['username']),
                'enc_email'     => base64_encode($cipher->encrypt(trim($userinfo['email']), $api_key)),
                'allow_email'   => $userinfo['adminemail'],
                'language'      => $all_languages[$language_id],
                'activated'     => $activated,
            );
        }
        $data['result'] = true;
    }
    echo ($format == 'json') ? json_encode($data) : serialize($data);
    exit;
}
