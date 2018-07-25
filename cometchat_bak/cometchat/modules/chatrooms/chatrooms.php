<?php

/*

CometChat
Copyright (c) 2014 Inscripts

CometChat ('the Software') is a copyrighted work of authorship. Inscripts
retains ownership of the Software and any copies of it, regardless of the
form in which the copies may exist. This license is not a sale of the
original Software or any copies.

By installing and using CometChat on your server, you agree to the following
terms and conditions. Such agreement is either on your own behalf or on behalf
of any corporate entity which employs you or which you represent
('Corporate Licensee'). In this Agreement, 'you' includes both the reader
and any Corporate Licensee and 'Inscripts' means Inscripts (I) Private Limited:

CometChat license grants you the right to run one instance (a single installation)
of the Software on one web server and one web site for each license purchased.
Each license may power one instance of the Software on one domain. For each
installed instance of the Software, a separate license is required.
The Software is licensed only to you. You may not rent, lease, sublicense, sell,
assign, pledge, transfer or otherwise dispose of the Software in any form, on
a temporary or permanent basis, without the prior written consent of Inscripts.

The license is effective until terminated. You may terminate it
at any time by uninstalling the Software and destroying any copies in any form.

The Software source code may be altered (at your risk)

All Software copyright notices within the scripts must remain unchanged (and visible).

The Software may not be used for anything that would represent or is associated
with an Intellectual Property violation, including, but not limited to,
engaging in any activity that infringes or misappropriates the intellectual property
rights of others, including copyrights, trademarks, service marks, trade secrets,
software piracy, and patents held by individuals, corporations, or other entities.

If any of the terms of this Agreement are violated, Inscripts reserves the right
to revoke the Software license at any time.

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

include_once(dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR."modules.php");
include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."config.php");
include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR."en.php");

if (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$lang.".php")) {
	include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$lang.".php");
}

if ($_REQUEST['basedata']) {
	$basedata = $_REQUEST['basedata'];
} else {
	$basedata = null;
}
$embed = '';
$embedcss = '';
$close = "setTimeout('window.close()',2000);";
$response = array();
if (!empty($_GET['embed']) && $_GET['embed'] == 'web') {
	$embed = 'web';
	$embedcss = 'embed';
	$close = "parent.closeCCPopup('invite');";
}

if (!empty($_GET['embed']) && $_GET['embed'] == 'desktop') {
	$embed = 'desktop';
	$embedcss = 'embed';
	$close = "parentSandboxBridge.closeCCPopup('invite');";
}

if ($userid == 0 || in_array($userid,$bannedUserIDs)) {
	$response['logout'] = 1;
	header('Content-type: application/json; charset=utf-8');
	echo json_encode($response);
	exit;
}

if(!empty($_GET['action']) && $_GET['action'] == 'sendmessage'){
	$_GET['action'] = 'sendChatroomMessage';
}

function heartbeat() {
	global $response;
	global $userid;
	global $chatrooms_language;
	global $chatroomTimeout;
	global $lastMessages;
	global $cookiePrefix;
	global $allowAvatar;
	global $moderatorUserIDs;
	global $guestsMode, $crguestsMode, $guestnamePrefix;
    global $chromeReorderFix;

	if(!empty($guestnamePrefix)){ $guestnamePrefix .= '-'; }

	$usertable = TABLE_PREFIX.DB_USERTABLE;
	$usertable_username = DB_USERTABLE_NAME;
	$usertable_userid = DB_USERTABLE_USERID;

	$time = getTimeStamp();
	$chatroomList = array();
	$cachedChatrooms = array();

	if (isset($_POST['popout']) && $_POST['popout'] == 0) {
		$_SESSION['cometchat']['cometchat_chatroomspopout'] = $time;
	}

	if (!empty($_POST['currentroom']) && $_POST['currentroom'] != 0) {
			$sql = ("insert into cometchat_chatrooms_users (userid,chatroomid,lastactivity,isbanned) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."','".mysqli_real_escape_string($GLOBALS['dbh'],$time)."','0') on duplicate key update chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."', lastactivity = '".mysqli_real_escape_string($GLOBALS['dbh'],$time)."'");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
		}

	if ((empty($_SESSION['cometchat']['cometchat_chatroomslist'])) || (!empty($_POST['force'])) || (!empty($_SESSION['cometchat']['cometchat_chatroomslist']) && ($time-$_SESSION['cometchat']['cometchat_chatroomslist'] > REFRESH_BUDDYLIST))) {

		if($chatroomCache = getCache($cookiePrefix.'chatroom_list',30)) {
			$cachedChatrooms = unserialize($chatroomCache);
		} else {
			$sql = ("select DISTINCT cometchat_chatrooms.id, cometchat_chatrooms.name, cometchat_chatrooms.type, cometchat_chatrooms.password, cometchat_chatrooms.lastactivity, cometchat_chatrooms.createdby, (SELECT count(userid) online FROM cometchat_chatrooms_users where cometchat_chatrooms_users.chatroomid = cometchat_chatrooms.id and '$time'-lastactivity<".ONLINE_TIMEOUT." and isbanned<>'1') online from cometchat_chatrooms order by name asc");
			$query = mysqli_query($GLOBALS['dbh'],$sql);

			while ($chatroom = mysqli_fetch_assoc($query)) {
				$cachedChatrooms[$chromeReorderFix.$chatroom['id']] = array('id' => $chatroom['id'], 'name' => $chatroom['name'], 'online' => $chatroom['online'], 'type' => $chatroom['type'], 'password' => $chatroom['password'], 'lastactivity' => $chatroom['lastactivity'], 'createdby' => $chatroom['createdby']);
			}
			setCache($cookiePrefix.'chatroom_list',serialize($cachedChatrooms),30);
		}

		foreach($cachedChatrooms as $key=>$chatroom) {
			if((($chatroom['createdby'] == 0|| ($chatroom['createdby'] <> 0 && $chatroom['type'] <> 2 && $time - $chatroom['lastactivity'] < $chatroomTimeout)) || $chatroom['createdby'] == $userid) && ($chatroom['type'] <> 3)) {
				$s = 0;
				if ($chatroom['createdby'] != $userid) {
					if(!(in_array($userid,$moderatorUserIDs))){
							$chatroom['password'] = '';
					} else {
						$s = 2;
					}
				} else {
					$s = 1;
				}
				$chatroomList[$chromeReorderFix.$chatroom['id']] = array('id' => $chatroom['id'], 'name' => $chatroom['name'], 'online' => $chatroom['online'], 'type' => $chatroom['type'], 'i' => $chatroom['password'], 's' => $s);
			}
		}

		$_SESSION['cometchat']['cometchat_chatroomslist'] = $time;

		$clh = md5(serialize($chatroomList));

		if ((empty($_POST['clh'])) || (!empty($_POST['clh']) && $clh != $_POST['clh'])) {
			$response['chatrooms'] = $chatroomList;
			$response['clh'] = $clh;
		}
	}

	if (!empty($_POST['currentroom']) && $_POST['currentroom'] != 0) {

		$users = array();
		$messages = array();

		if($cachedUsers = getCache($cookiePrefix.'chatrooms_users'.$_POST['currentroom'],30)) {
			$users = unserialize($cachedUsers);
		} else {
			$sql = ("select DISTINCT ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." userid, ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_NAME." username, ".DB_AVATARFIELD." avatar, cometchat_status.lastactivity lastactivity, cometchat_chatrooms_users.isbanned from ".TABLE_PREFIX.DB_USERTABLE." left join cometchat_status on ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." = cometchat_status.userid inner join cometchat_chatrooms_users on  ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." =  cometchat_chatrooms_users.userid ". DB_AVATARTABLE ." where chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."' and ('".mysqli_real_escape_string($GLOBALS['dbh'],$time)."' - cometchat_chatrooms_users.lastactivity < ".ONLINE_TIMEOUT.") order by username asc");
			if($guestsMode && $crguestsMode){
				$sql = getChatroomGuests($_POST['currentroom'],$time,$sql);
			}

			$query = mysqli_query($GLOBALS['dbh'],$sql);

			while ($chat = mysqli_fetch_assoc($query)) {
				if (function_exists('processName')) {
					$chat['username'] = processName($chat['username']);
				}
				$avatar = '';
				if($allowAvatar) {
					$avatar = getAvatar($chat['avatar']);
				}

				$users[$chromeReorderFix.$chat['userid']] = array('id' => $chat['userid'], 'n' => $chat['username'], 'a' => $avatar, 'b' => $chat['isbanned']);
			}
			setCache($cookiePrefix.'chatrooms_users'.$_POST['currentroom'],serialize($users),30);
		}

		$ulh = md5(serialize($users));

		if ((empty($_POST['ulh'])) || (!empty($_POST['ulh']) && $ulh != $_POST['ulh'])) {
			$response['ulh'] = $ulh;
			if (!empty($users)) {
				$response['users'] = $users;
			}
		}

		if (USE_COMET != 1 || COMET_CHATROOMS != 1) {

			$limit = $lastMessages;
			if ($lastMessages == 0) {
				$limit = 1;
			}

			$guestpart = "";
			$limitClause = " limit ".$limit." ";
			$timestampCondition = "";

			if ($_POST['timestamp'] != 0) {
				$timestampCondition = " and cometchat_chatroommessages.id > '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['timestamp'])."' ";
				$limitClause = "";
			} elseif (!empty($_SESSION['cometchat']['chatrooms_'.$_POST['currentroom'].'_clearId'])){
				$timestampCondition = " and cometchat_chatroommessages.sent > '".($_SESSION['cometchat']['chatrooms_'.$_POST['currentroom'].'_clearId']/1000)."' ";
			}

			if ($guestsMode && $crguestsMode) {
				$guestpart = " UNION select DISTINCT cometchat_chatroommessages.id id, cometchat_chatroommessages.message, cometchat_chatroommessages.sent, CONCAT('".$guestnamePrefix."',m.name) `from`, cometchat_chatroommessages.userid fromid, m.id userid from cometchat_chatroommessages join cometchat_guests m on m.id = cometchat_chatroommessages.userid where cometchat_chatroommessages.chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."' and cometchat_chatroommessages.message not like 'banned_%' and cometchat_chatroommessages.message not like 'kicked_%' and cometchat_chatroommessages.message not like 'deletemessage_%' ".$timestampCondition;
			}

			$sql = ("select DISTINCT cometchat_chatroommessages.id id, cometchat_chatroommessages.message, cometchat_chatroommessages.sent, m.$usertable_username `from`, cometchat_chatroommessages.userid fromid, m.$usertable_userid userid from cometchat_chatroommessages join $usertable m on m.$usertable_userid = cometchat_chatroommessages.userid  where cometchat_chatroommessages.chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."' and cometchat_chatroommessages.message not like 'banned_%' and cometchat_chatroommessages.message not like 'kicked_%' and cometchat_chatroommessages.message not like 'deletemessage_%' ". $timestampCondition . $guestpart." order by id desc ".$limitClause);
			$query = mysqli_query($GLOBALS['dbh'],$sql);

			while ($chat = mysqli_fetch_assoc($query)) {
				if (function_exists('processName')) {
					$chat['from'] = processName($chat['from']);
				}

				if ($lastMessages == 0 && $_POST['timestamp'] == 0) {
					$chat['message'] = '';
				}

				if ($userid == $chat['userid']) {
					$chat['from'] = $chatrooms_language[6];

				} else {

					if (!empty($_COOKIE[$cookiePrefix.'lang']) && !(strpos($chat['message'],"CC^CONTROL_")>-1)) {

						$translated = text_translate($chat['message'],'',$_COOKIE[$cookiePrefix.'lang']);

						if ($translated != '') {
							$chat['message'] = strip_tags($translated).' <span class="untranslatedtext">('.$chat['message'].')</span>';
						}
					}
				}

				array_unshift($messages,array('id' => $chat['id'], 'from' => $chat['from'],'fromid' => $chat['fromid'], 'message' => $chat['message'],'sent' => ($chat['sent'])));
			}

		} else {
                    if ($_POST['timestamp'] == 0) {
                        $comet = new Comet(KEY_A, KEY_B);
                        $history = $comet->history(array(
                            'channel' => md5('chatroom_' . $_POST['currentroom'] . KEY_A . KEY_B . KEY_C),
                            'limit' => $lastMessages + 5,
                        ));

                        $moremessages = array();
                        if (!empty($history)) {
                            foreach ($history as $message) {
                                if (strpos($message['message'], 'CC^CONTROL_') > -1)
                                        continue;
				$moremessages['_'.$message['sent']] = array("id" => $message['sent'], "from" => $message['from'], "fromid" => $message['fromid'], "message" => $message['message'], "old" => 1, 'sent' => (round($message['sent'] / 1000)));
                            }

                            $messages = array_merge($messages, $moremessages);
                            $count_msg = count($messages);
                            usort($messages, 'comparetime');
                            $messages = ($lastMessages > $count_msg) ? $messages : array_slice($messages, -$lastMessages);
                        }

                    }
                }

		if (!empty($messages)) {
			$response['messages'] = $messages;
		}

		$sql = ("select password from cometchat_chatrooms where id = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		if($room = mysqli_fetch_assoc($query)){
			if (!empty($room['password']) && (empty($_POST['currentp']) || ($room['password'] != $_POST['currentp']))) {
				$response['users'] = array();
				$response['messages'] = array();
			}
		}else{
			$response['alert'] = "ROOM_DOES_NOT_EXISTS";
		}
	}

	header('Content-type: application/json; charset=utf-8');
	$json_response = json_encode($response);
	if(!empty($_REQUEST['v3'])){
		$json_response = str_replace('"chatrooms":[]','"chatrooms":{}',$json_response);
	}
	echo json_encode($response);
}

function createchatroom() {

	global $userid;
	$name = $_POST['name'];
	$password = $_POST['password'];
	$type = $_POST['type'];

	$sql = ("select name from cometchat_chatrooms where name = '".$name."'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if(mysqli_num_rows($query) == 0) {
		if ($userid > 0) {
			$time = getTimeStamp();
			if (!empty($password)) {
				$password = sha1($password);
			} else {
				$password = '';
			}

			$sql = ("insert into cometchat_chatrooms (name,createdby,lastactivity,password,type) values ('".mysqli_real_escape_string($GLOBALS['dbh'],sanitize_core($name))."', '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".getTimeStamp()."','".mysqli_real_escape_string($GLOBALS['dbh'],sanitize_core($password))."','".mysqli_real_escape_string($GLOBALS['dbh'],sanitize_core($type))."')");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			$currentroom = mysqli_insert_id($GLOBALS['dbh']);

			$sql = ("insert into cometchat_chatrooms_users (userid,chatroomid,lastactivity) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".mysqli_real_escape_string($GLOBALS['dbh'],$currentroom)."','".mysqli_real_escape_string($GLOBALS['dbh'],$time)."') on duplicate key update chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$currentroom)."', lastactivity = '".mysqli_real_escape_string($GLOBALS['dbh'],$time)."'");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			echo $currentroom;
			exit(0);
		}
	} else {
		echo "0";
		exit;
	}
}

function checkpassword() {

	global $userid;
	global $cookiePrefix;
	global $moderatorUserIDs;
	$response = array();
	$_SESSION['cometchat']['isModerator'] = 0;
	$id = $_POST['id'];
	if(!empty($_POST['password'])) {
		$password = $_POST['password'];
	}
	header('Content-type: application/json; charset=utf-8');
	$sql = ("select * from cometchat_chatrooms_users where userid ='".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."' and isbanned = '1'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if(mysqli_num_rows($query) == 1){
		$response['s'] = 'BANNED';
		echo json_encode($response);
		exit;
	}
	if ($userid > 0) {
		$sql = ("select * from cometchat_chatrooms where id = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['id'])."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		if($room = mysqli_fetch_assoc($query)){
			if (!empty($room['password']) && (empty($_POST['password']) || ($room['password'] != $_POST['password']))) {
				$response['s'] = 'INVALID_PASSWORD';
			} else {
				$channelprefix = '';

				if(preg_match('/www\./', $_SERVER['HTTP_HOST']))
				{
					$channelprefix = $_SERVER['HTTP_HOST'];
				}else
				{
					$channelprefix = 'www.'.$_SERVER['HTTP_HOST'];
				}
				removeCache($cookiePrefix.'chatrooms_users'.$id);
				removeCache($cookiePrefix.'chatroom_list');

				$sql = ("delete from cometchat_chatrooms_users where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and isbanned <> '1' ");
				$query = mysqli_query($GLOBALS['dbh'],$sql);

				$sql = ("insert into cometchat_chatrooms_users (userid,chatroomid,lastactivity,isbanned) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".mysqli_real_escape_string($GLOBALS['dbh'],$id)."','".mysqli_real_escape_string($GLOBALS['dbh'],time())."','0') on duplicate key update chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."', lastactivity = '".mysqli_real_escape_string($GLOBALS['dbh'],time())."'");
				$query = mysqli_query($GLOBALS['dbh'],$sql);
				if ($room['createdby'] == $userid || in_array($userid,$moderatorUserIDs)) {
					$_SESSION['cometchat']['isModerator'] = 1;
				}
				$key = '';
				if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
					$key = KEY_A.KEY_B.KEY_C;
				}

				$response=array('s' => 'JOINED',
					'cometid' => md5('chatroom_'.$id.$key),
					'owner' => ($room['createdby'] == $userid?"1":"0"),
					'userid' => $userid,
					'ismoderator' => $_SESSION['cometchat']['isModerator'],
					'push_channel' => 'C_'.md5($channelprefix."CHATROOM_".$id.BASE_URL)
					);

				//Store chatroom name in session for push notifications
				$_SESSION['cometchat']['chatroom']['n'] = $room['name'];
				$_SESSION['cometchat']['chatroom']['id'] = $id;
			}
		}else{
			$response['s'] = 'INVALID_CHATROOM';
		}
		echo json_encode($response);
	}
}

function invite() {
	global $userid;
	global $chatrooms_language;
	global $language;
	global $embed;
	global $embedcss;
    global $guestsMode;
	global $basedata;
	global $cookiePrefix;
    global $chromeReorderFix;
	global $hideOffline;

	$status['available'] = $language[30];
	$status['busy'] = $language[31];
	$status['offline'] = $language[32];
	$status['invisible'] = $language[33];
	$status['away'] = $language[34];

	$id = $_GET['roomid'];
	$inviteid = $_GET['inviteid'];
	$roomname = $_GET['roomname'];
	$popoutmode = $_GET['popoutmode'];

	$time = getTimeStamp();
	$buddyList = array();

	$sql = ("select GROUP_CONCAT(userid) bannedusers from cometchat_chatrooms_users where ( isbanned=1 or ('".mysqli_real_escape_string($GLOBALS['dbh'],$time)."' - cometchat_chatrooms_users.lastactivity < ".ONLINE_TIMEOUT.") ) and chatroomid='".$id."' ");
	$query = mysqli_query($GLOBALS['dbh'],$sql);

	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	$result = mysqli_fetch_assoc($query);
	$bannedUsers = explode(',',$result['bannedusers']);

	$onlineCacheKey = 'all_online';
	if($userid > 10000000){
		$onlineCacheKey .= 'guest';
	}

	if ($onlineUsers = getCache($cookiePrefix.$onlineCacheKey, 30)) {
		$buddyList = unserialize($onlineUsers);
	} else {
		$sql = getFriendsList($userid,$time);
		if($guestsMode){
	    	$sql = getGuestsList($userid,$time,$sql);
		}
		$query = mysqli_query($GLOBALS['dbh'],$sql);

		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

		while ($chat = mysqli_fetch_assoc($query)) {

			if (((($time-processTime($chat['lastactivity'])) < ONLINE_TIMEOUT) && $chat['status'] != 'invisible' && $chat['status'] != 'offline') || $chat['isdevice'] == 1) {
				if ($chat['status'] != 'busy' && $chat['status'] != 'away') {
					$chat['status'] = 'available';
				}
			} else {
				$chat['status'] = 'offline';
			}

			$avatar = getAvatar($chat['avatar']);

			if (!empty($chat['username'])) {
				if (function_exists('processName')) {
					$chat['username'] = processName($chat['username']);
				}

				if (!(in_array($chat['userid'],$bannedUsers)) && $chat['userid'] != $userid && ($hideOffline == 0||($hideOffline == 1 && $chat['status']!='offline'))) {
					$buddyList[$chromeReorderFix.$chat['userid']] = array('id' => $chat['userid'], 'n' => $chat['username'], 'a' => $avatar, 's' => $chat['status']);
				}
			}
		}
	}

	if (DISPLAY_ALL_USERS == 0 && MEMCACHE <> 0) {
		$tempBuddyList = array();
		if ($onlineFrnds = getCache($cookiePrefix.'friend_ids_of_'.$userid, 30)) {
			$friendIds = unserialize($onlineFrnds);
		} else {
			$sql = getFriendsIds($userid);
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			if(mysqli_num_rows($query) == 1 ){
				$buddy = mysqli_fetch_assoc($query);
				$friendIds = explode(',',$buddy['friendid']);
			}else {
				while($buddy = mysqli_fetch_assoc($query)){
					$friendIds[]=$buddy['friendid'];
				}
			}
			setCache($cookiePrefix.'friend_ids_of_'.$userid,serialize($friendIds), 30);
		}
		foreach($friendIds as $friendId) {
			$friendId = $chromeReorderFix.$friendId;
			if (isset($buddyList[$friendId])) {
				$tempBuddyList[$friendId] = $buddyList[$friendId];
			}
		}
		$buddyList = $tempBuddyList;
	}

	if (function_exists('hooks_forcefriends') && is_array(hooks_forcefriends())) {
		$buddyList = array_merge(hooks_forcefriends(),$buddyList);
	}

	$s['available'] = '';
	$s['away'] = '';
	$s['busy'] = '';
	$s['offline'] = '';

	foreach ($buddyList as $buddy) {
		if($buddy['id'] != $userid){
			$s[$buddy['s']] .= '<div class="invite_1"><div class="invite_2" onclick="javascript:document.getElementById(\'check_'.$buddy['id'].'\').checked = document.getElementById(\'check_'.$buddy['id'].'\').checked?false:true;"><img height=30 width=30 src="'.$buddy['a'].'" /></div><div class="invite_3" onclick="javascript:document.getElementById(\'check_'.$buddy['id'].'\').checked = document.getElementById(\'check_'.$buddy['id'].'\').checked?false:true;"><span class="invite_name">'.$buddy['n'].'</span><br/><span class="invite_5">'.$status[$buddy['s']].'</span></div><input type="checkbox" name="invite[]" value="'.$buddy['id'].'" id="check_'.$buddy['id'].'" class="invite_4" /></div>';
		}
	}

	$inviteContent = '';
	$invitehide = '';
	$inviteContent = $s['available']."".$s['away']."".$s['offline'];
	if(empty($inviteContent)) {
		$inviteContent = $chatrooms_language[45];
		$invitehide = 'style="display:none;"';
	}

	echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$chatrooms_language[22]}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
	</head>
	<body>
		<form method="post" action="chatrooms.php?action=inviteusers&embed={$embed}&basedata={$basedata}">
			<div class="container">
				<div class="container_title {$embedcss}">{$chatrooms_language[21]}</div>
				<div class="container_body {$embedcss}">
					{$inviteContent}
					<div style="clear:both"></div>
				</div>
				<div class="container_sub {$embedcss}" {$invitehide}>
					<input type=submit value="{$chatrooms_language[20]}" class="invitebutton" />
				</div>
			</div>
			<input type="hidden" name="roomid" value="{$id}" />
			<input type="hidden" name="inviteid" value="{$inviteid}" />
			<input type="hidden" name="roomname" value="{$roomname}" />
		</form>
	</body>
</html>
EOD;
}

function inviteusers() {
	global $chatrooms_language;
	global $close;
	global $embedcss;

	if(!empty($_POST['invite'])){
		foreach ($_POST['invite'] as $user) {
			$response = sendMessage($user,"{$chatrooms_language[18]}<a href=\"javascript:jqcc.cometchat.joinChatroom('{$_POST['roomid']}','{$_POST['inviteid']}','{$_POST['roomname']}')\">{$chatrooms_language[19]}</a>",1);
			$processedMessage = $_SESSION['cometchat']['user']['n'].": "."has invited you to join ".$_SESSION['cometchat']['chatroom']['n'];
			parsePusher($user,$response['id'],$processedMessage);
		}
	}

	echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$chatrooms_language[18]}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
	</head>
	<body onload="{$close}">
		<div class="container">
			<div class="container_title {$embedcss}">{$chatrooms_language[21]}</div>
			<div class="container_body {$embedcss}">
				{$chatrooms_language[16]}
				<div style="clear:both"></div>
			</div>
		</div>
	</body>
</html>
EOD;
}



function passwordBox() {

	global $chatrooms_language;
	global $embedcss;

	$close = 'setTimeout("window.close()",2000);';
	if (!empty($_GET['embed']) && $_GET['embed'] == 'web') {
		$embed = 'web';
		$embedcss = 'embed';
		$close = 'parent.closeCCPopup(\'passwordBox\');';
	}

	$id = $_REQUEST['id'];
	$name = $_REQUEST['name'];
	$silent = $_REQUEST['silent'];


	$options=" <input type=button id='passwordBox' class='invitebutton' value='$chatrooms_language[19]' /><input type=button id='close' class='invitebutton' onclick=$close value='$chatrooms_language[51]' />";

echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$name}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
		<script type="text/javascript">
		$(document).ready(function() {

			$('#passwordBox').click(function(e) {
				if (typeof $('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms',parent.document)[0] == "undefined"){
					window.opener.jqcc.cometchat.checkChatroomPass($id,'$name',$silent,$('#chatroomPass').val());
				} else {
					$('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms',parent.document)[0].contentWindow.jqcc.cometchat.checkChatroomPass($id,'$name',$silent,$('#chatroomPass').val());
				}
				$close;
			});

			$('#chatroomPass').keyup(function(e) {
				if(e.keyCode == 13) {
					if (typeof $('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms',parent.document)[0] != "undefined"){
						$('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms',parent.document)[0].contentWindow.jqcc.cometchat.checkChatroomPass($id,'$name',$silent,$('#chatroomPass').val());
					}else{
						window.opener.jqcc.cometchat.checkChatroomPass({$id},'{$name}',{$silent},$('#chatroomPass').val());
					}
					{$close}
				}
			});
		});
		</script>
	</head>
	<body>
		<div class="container">
			<div class="container_title {$embedcss}">{$name}</div>
			<div style="overflow:hidden; height:40px !important;" class="container_body {$embedcss}">
			<div class="passwordbox_body">{$chatrooms_language[8]}</div>
			<input style="width: 100%;" id="chatroomPass" type="password" name="pwd" autofocus/>
				<div style="clear:both"></div>
			</div>
			<div align="right" class="container_sub {$embedcss}">{$options}</div>
		</div>
	</body>
</html>
EOD;
}

function loadChatroomPro() {

	global $chatrooms_language;
	global $embed;
	global $embedcss;
	global $userid;
	global $moderatorUserIDs;
	global $lightboxWindows;

	$close = 'setTimeout("window.close()",2000);';
	if (!empty($_GET['embed']) && $_GET['embed'] == 'web') {
		$embed = 'web';
		$embedcss = 'embed';
		$close = 'parent.closeCCPopup("loadChatroomPro");';
	}

	$id = $_GET['roomid'];
	$uid = $_GET['inviteid'];
	$owner = $_GET['owner'];
	$apiAccess = $_GET['apiAccess'];
	$options = "";
	$caller = "window.opener.";
	$popoutmode = $_GET['popoutmode'];

	if($apiAccess) {
		if($lightboxWindows) {
			$caller="$('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms',parent.document)[0].contentWindow.";
			$options=" <input type=button class='invitebutton' onclick=javascript:parent.jqcc.cometchat.chatWith($uid);$close value='".$chatrooms_language[43]."' />";
			if($popoutmode && $popoutmode != 'null') {
				$options =" <input type=button class='invitebutton' onclick=javascript:window.opener.parent.jqcc.cometchat.chatWith($uid);$close value='".$chatrooms_language[43]."' />";
			}
		}else {
			$options=" <input type=button class='invitebutton' onclick=javascript:window.opener.parent.jqcc.cometchat.chatWith($uid);$close value='".$chatrooms_language[43]."' />";
			if($popoutmode && $popoutmode != 'null') {
				$options =" <input type=button class='invitebutton' onclick=javascript:window.opener.window.opener.parent.jqcc.cometchat.chatWith($uid);$close value='".$chatrooms_language[43]."' />";
			}
		}
	}
	if($owner == 1 || in_array($userid,$moderatorUserIDs)) {

		$sql = ("select createdby from cometchat_chatrooms where id = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."' limit 1");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		$room = mysqli_fetch_assoc($query);

		if(!in_array($uid,$moderatorUserIDs) && $uid != $room['createdby']) {
			$options = "<input type=button value='".$chatrooms_language[40]."' onClick=javascript:".$caller."jqcc.cometchat.kickChatroomUser($uid,0);$close class='invitebutton' />
			<input type=button value='".$chatrooms_language[41]."' onClick=javascript:".$caller."jqcc.cometchat.banChatroomUser($uid,0);$close class='invitebutton' />".$options;
		}
	}

	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	$sql = getUserDetails($uid);

	if($uid>10000000) {
		$sql = getGuestDetails($uid);
	}

	$res = mysqli_query($GLOBALS['dbh'],$sql);
	$result = mysqli_fetch_assoc($res);
	$link = fetchLink($result['link']);
	$avatar = getAvatar($result['avatar']);

	if($link != '' && $uid < 10000000) {
		$options .= " <input type=button class='invitebutton' onClick=javascript:window.open('".$link."');".$close." value='".$chatrooms_language[42]."' />";
	}

echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$result['username']}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
	</head>
	<body>
		<form method="post">
			<div class="container">
				<div class="container_title {$embedcss}">{$result['username']}</div>
				<div class="chatroom_avatar"><img src="{$avatar}" height="50px" width="50px" /></div>
				<div class="control_buttons">{$options}</div>
			</div>
		</form>
	</body>
</html>
EOD;
}

function leavechatroom() {
	global $userid;
	global $cookiePrefix;
        if (empty($_REQUEST['banflag'])) {
            $sql = ("delete from cometchat_chatrooms_users where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['currentroom'])."'");
            $query = mysqli_query($GLOBALS['dbh'],$sql);
        }

	removeCache($cookiePrefix.'chatrooms_users'.$_POST['currentroom']);
	removeCache($cookiePrefix.'chatroom_list');

	unset($_SESSION['cometchat']['cometchat_chatroomslist']);
	unset($_SESSION['cometchat']['isModerator']);
	echo 1;
}

function kickUser() {
    global $cookiePrefix;
	$kickid = $_REQUEST['kickid'];
	$id = $_REQUEST['currentroom'];
	if (empty($_REQUEST['kick']) && empty($_SESSION['cometchat']['isModerator']) ) {
		echo 0;
		exit;
	}
	if($_REQUEST['kick']<>'0') {
		$sql = ("delete from cometchat_chatroommessages where id='".mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['kick'])."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
	}
	$sql = ("delete from cometchat_chatrooms_users where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$kickid)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);

	sendChatroomMessage($id,'CC^CONTROL_kicked_'.$kickid,0);
	removeCache($cookiePrefix.'chatrooms_users'.$id);
	removeCache($cookiePrefix.'chatroom_list');
	echo 1;
}

function banUser() {
	global $cookiePrefix;
	$banid = $_REQUEST['banid'];
	$id = $_REQUEST['currentroom'];
	$popoutmode	= $_REQUEST['popoutmode'];
	if (empty($_REQUEST['ban']) && empty($_SESSION['cometchat']['isModerator']) ) {
		echo 0;
		exit;
	}
	if($_REQUEST['ban']<>'0'){
		$sql = ("delete from cometchat_chatroommessages where id='".mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['ban'])."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
	}
	$sql = ("update cometchat_chatrooms_users set isbanned = '1' where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$banid)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);

	sendChatroomMessage($id,'CC^CONTROL_banned_'.$banid,0);
	removeCache($cookiePrefix.'chatrooms_users'.$id);
	removeCache($cookiePrefix.'chatroom_list');
	echo 1;
}

function unban() {
	global $userid;
	global $chatrooms_language;
	global $language;
	global $embed;
	global $embedcss;
	global $guestsMode;
	global $basedata;
        global $chromeReorderFix;

	$status['available'] = $language[30];
	$status['busy'] = $language[31];
	$status['offline'] = $language[32];
	$status['invisible'] = $language[33];
	$status['away'] = $language[34];

	$id = $_GET['roomid'];
	$inviteid = $_GET['inviteid'];
	$roomname = $_GET['roomname'];
	$popoutmode = $_GET['popoutmode'];

	$time = getTimeStamp();
	$buddyList = array();
	$sql = ("select DISTINCT ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." userid, ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_NAME." username, ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_NAME." link, ".DB_AVATARFIELD." avatar, cometchat_status.lastactivity lastactivity, cometchat_status.status, cometchat_status.message from ".TABLE_PREFIX.DB_USERTABLE." left join cometchat_status on ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." = cometchat_status.userid right join cometchat_chatrooms_users on ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." =cometchat_chatrooms_users.userid ".DB_AVATARTABLE." where ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." <> '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and cometchat_chatrooms_users.chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."' and cometchat_chatrooms_users.isbanned ='1' order by username asc");
	if ($guestsMode) {
		$sql = getChatroomBannedGuests($id,$time,$sql);
   }
	$query = mysqli_query($GLOBALS['dbh'],$sql);

	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	while ($chat = mysqli_fetch_assoc($query)) {

		$avatar = getAvatar($chat['avatar']);

		if (!empty($chat['username'])) {
			if (function_exists('processName')) {
				$chat['username'] = processName($chat['username']);
			}

			$buddyList[$chromeReorderFix.$chat['userid']] = array('id' => $chat['userid'], 'n' => $chat['username'], 'a' => $avatar);
		}
	}

	$s['count'] = '';

	foreach ($buddyList as $buddy) {

		$s['count'] .= '<div class="invite_1"><div class="invite_2" onclick="javascript:document.getElementById(\'check_'.$buddy['id'].'\').checked = document.getElementById(\'check_'.$buddy['id'].'\').checked?false:true;"><img height=30 width=30 src="'.$buddy['a'].'" /></div><div class="invite_3" onclick="javascript:document.getElementById(\'check_'.$buddy['id'].'\').checked = document.getElementById(\'check_'.$buddy['id'].'\').checked?false:true;"><span class="invite_name">'.$buddy['n'].'</span><br/></div><input type="checkbox" name="unban[]" value="'.$buddy['id'].'" id="check_'.$buddy['id'].'" class="invite_4" /></div>';
	}

	if($s['count'] == ''){
		$s['count'] = $chatrooms_language[44];
	}
	echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$chatrooms_language[21]}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
	</head>
	<body>
		<form method="post" action="chatrooms.php?action=unbanusers&embed={$embed}&basedata={$basedata}&popoutmode={&popoutmode}">
			<div class="container">
				<div class="container_title {$embedcss}">{$chatrooms_language[21]}</div>
				<div class="container_body {$embedcss}">
					{$s['count']}
					<div style="clear:both"></div>
				</div>
				<div class="container_sub {$embedcss}">
					<input type=submit value="Unban Users" class="invitebutton" />
				</div>
			</div>
			<input type="hidden" name="roomid" value="{$id}" />
			<input type="hidden" name="inviteid" value="{$inviteid}" />
			<input type="hidden" name="roomname" value="{$roomname}" />
		</form>
	</body>
</html>
EOD;
}

function unbanusers() {

	global $chatrooms_language;
	global $close;
	global $embedcss;

	if (empty($_SESSION['cometchat']['isModerator']) ) {
		echo 0;
		exit;
	}

	if(!empty($_POST['unban'])){
		foreach ($_POST['unban'] as $user) {
			$sql = ("delete from cometchat_chatrooms_users where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$user)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$_POST['roomid'])."'");
			$query = mysqli_query($GLOBALS['dbh'],$sql);

			sendMessage($user,"{$chatrooms_language[18]}<a href=\"javascript:jqcc.cometchat.joinChatroom('{$_POST['roomid']}','{$_POST['inviteid']}','{$_POST['roomname']}')\">{$chatrooms_language[19]}</a>",1);
		}
	}

	echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<title>{$chatrooms_language[18]}</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<link type="text/css" rel="stylesheet" media="all" href="../../css.php?type=module&name=chatrooms" />
	</head>
	<body onload="{$close}">
		<div class="container">
			<div class="container_title {$embedcss}">{$chatrooms_language[21]}</div>
			<div class="container_body {$embedcss}">
				{$chatrooms_language[16]}
				<div style="clear:both"></div>
			</div>
		</div>
	</body>
</html>
EOD;
}

function deleteChatroomMessage() {
	$id = $_REQUEST['currentroom'];
	$delid = $_REQUEST['delid'];
	global $allowdelete;
	global $userid;
        $deleteflag = 0;

        if (!empty($_SESSION['cometchat']['isModerator'])) {
            $deleteflag = 1;
        } elseif (empty($allowdelete)){
            if (USE_COMET == 1 && COMET_CHATROOMS == 1) {
                $sql = ("select message from cometchat_comethistory where message like '%s:13:\"".mysqli_real_escape_string($GLOBALS['dbh'],$delid)."\";%' ");
                $query = mysqli_query($GLOBALS['dbh'],$sql);
                $row = mysqli_fetch_assoc($query);
                $message = unserialize($row['message']);
                if ($message['fromid'] == $userid) {
                    $deleteflag = 1;
                }
            } else {
                $sql = ("select userid from cometchat_chatroommessages where id='".mysqli_real_escape_string($GLOBALS['dbh'],$delid)."'");
                $query = mysqli_query($GLOBALS['dbh'],$sql);
                $row = mysqli_fetch_assoc($query);
                if ($row['userid'] == $userid) {
                    $deleteflag = 1;
                }
            }
        }
	if (empty($deleteflag)) {
		echo 0;
		exit;
	} else {
            sendCCResponse(1);
        }
	if (USE_COMET == 1 && COMET_CHATROOMS == 1) {
		$sql = ("delete from cometchat_comethistory where message like '%s:13:\"".mysqli_real_escape_string($GLOBALS['dbh'],$delid)."\";%' ");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
	} else {
                $del = $delid;
		$sql = ("delete from cometchat_chatroommessages where id='".mysqli_real_escape_string($GLOBALS['dbh'],$del)."' and chatroomid = '".mysqli_real_escape_string($GLOBALS['dbh'],$id)."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
	}
	sendChatroomMessage($id,'CC^CONTROL_deletemessage_'.$delid,0);
}

$allowedActions = array('sendChatroomMessage','heartbeat','createchatroom','checkpassword','invite','inviteusers','unban','unbanusers','passwordBox','loadChatroomPro','leavechatroom','kickUser','banUser','deleteChatroomMessage');

if (!empty($_GET['action']) && in_array($_GET['action'],$allowedActions)) {
	call_user_func($_GET['action']);
}