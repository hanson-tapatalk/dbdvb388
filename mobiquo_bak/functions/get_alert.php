<?php
defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function get_alert_func($xmlrpc_params)
{
    global $vbulletin;

    $alert_format = array(
        'sub'       => '%s replied to "%s"',
        'like'      => '%s liked your post in thread "%s"',
        'thank'     => '%s thanked your post in thread "%s"',
        'quote'     => '%s quoted your post in thread "%s"',
        'tag'       => '%s mentioned you in thread "%s"',
        'newtopic'  => '%s started a new thread "%s"',
        'pm'        => '%s sent you a message "%s"',
        'ann'       => '%sNew Announcement "%s"',
    );

    $xmlrpc_params = php_xmlrpc_decode($xmlrpc_params);
    $page = (isset($xmlrpc_params[0]) && intval($xmlrpc_params[0]) > 0) ? intval($xmlrpc_params[0]) : 1;
    $perpage = (isset($xmlrpc_params[1]) && intval($xmlrpc_params[1]) > 0) ? intval($xmlrpc_params[1]) : 20;
    $start = ($page-1)*$perpage;
    $current_userid = $vbulletin->userinfo['userid'];
    if($current_userid == 0)
        return_fault();
    $num_query = "SELECT COUNT(*) FROM ". TABLE_PREFIX . "tapatalk_push as tp WHERE tp.userid = $current_userid";
    $num_results = $vbulletin->db->query_read_slave($num_query);
    $num_results = $vbulletin->db->fetch_array($num_results);
    $total_num = $num_results['COUNT(*)'];
    $alerts = array();

    if(!empty($total_num))
    {
        $query = "SELECT tp.* FROM ". TABLE_PREFIX . "tapatalk_push as tp WHERE tp.userid = $current_userid ORDER BY tp.dateline DESC LIMIT $start, $perpage";
        $results = $vbulletin->db->query_read_slave($query);
        while($result = $vbulletin->db->fetch_array($results))
        {
            if (!isset($alert_format[$result['type']])) continue;

            $message = sprintf($alert_format[$result['type']], mobiquo_encode($result['author']), mobiquo_encode($result['title']));
            $user_id = get_userid_by_name($result['author']);
            $vbulletin->GPC['userid'] = $user_id;

            $fetch_userinfo_options = (
            FETCH_USERINFO_AVATAR |
            FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
            FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
            );
            $userinfo = mobiquo_verify_id('user', $vbulletin->GPC['userid'], 0, 1, $fetch_userinfo_options);
            if(!is_array($userinfo)){
                continue;
            }
            $fetch_userinfo_options = (FETCH_USERINFO_AVATAR);
            $authorinfo = fetch_userinfo($user_id, $fetch_userinfo_options);
               
            $alert = array(
                'user_id'       => new xmlrpcval($user_id, 'string'),
                'username'      => new xmlrpcval(mobiquo_encode($result['author']), 'base64'),
                'user_type'     => new xmlrpcval(get_usertype_by_item($user_id), 'base64'),
                'icon_url'      => new xmlrpcval(mobiquo_get_user_icon($user_id), 'string'),
                'message'       => new xmlrpcval($message, 'base64'),
                'timestamp'     => new xmlrpcval($result['dateline'], 'string'),
                'content_type'  => new xmlrpcval($result['type'], 'string'),
                'content_id'    => new xmlrpcval($result['type'] == 'pm' ? $result['id'] : $result['subid'], 'string'),
            );
            if($result['type'] != 'pm')
            {
                $alert['topic_id']  = new xmlrpcval($result['id'], 'string');
            }
            $alerts[] = new xmlrpcval($alert, 'struct');
        }
    }
    $return_data = array(
        'total' => new xmlrpcval($total_num, 'int'),
        'items' => new xmlrpcval($alerts, 'array')
    );
    return new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}
