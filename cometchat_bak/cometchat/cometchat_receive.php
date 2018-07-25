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

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."cometchat_init.php");

$response = array();
$messages = array();
$lastPushedAnnouncement = 0;
$processFurther = 1;

$status['available'] = $language[30];
$status['busy'] = $language[31];
$status['offline'] = $language[32];
$status['invisible'] = $language[33];
$status['away'] = $language[34];

if (empty($_REQUEST['f'])) {
	$_REQUEST['f'] = 0;
}

if ($userid > 0) {
	if (!empty($_REQUEST['chatbox'])) {
		getChatboxData($_REQUEST['chatbox']);
	} else {

		if (!empty($_REQUEST['status'])) {
			setStatus($_REQUEST['status']);
		}

		if (!empty($_REQUEST['initialize']) && $_REQUEST['initialize'] == 1) {

			if (USE_COMET == 1) {
				$key = '';
				if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
					$key = KEY_A.KEY_B.KEY_C;
				}
				$response['cometid']['id'] = md5($userid.$key);

				if (empty($_SESSION['cometchat']['cometmessagesafter'])) {
					$_SESSION['cometchat']['cometmessagesafter'] = getTimeStamp().'999';
				}
				$response['initialize'] = 0;
				$response['init'] = '1';

			} else {

				$sql = ("select id from cometchat order by id desc limit 1");
				$query = mysqli_query($GLOBALS['dbh'],$sql);
				if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
				$result = mysqli_fetch_assoc($query);

				$response['init'] = '1';
				$response['initialize'] = $result['id'];
			}

			getStatus();

			if (!empty($_COOKIE[$cookiePrefix.'state'])) {
				$states = explode(':',urldecode($_COOKIE[$cookiePrefix.'state']));

				$openChatboxId = '';

				if(isset($states[2]))
				if ($states[2] != '' && $states[2] != ' ') {
					$openChatboxId = $states[2];
				}

				getChatboxData($openChatboxId);
			}


		}

		if (!empty($_REQUEST['buddylist']) && $_REQUEST['buddylist'] == 1 && $processFurther) { getBuddyList(); }

		if (USE_COMET == 0) { getLastTimestamp(); }
		if (defined('DISABLE_ISTYPING') && DISABLE_ISTYPING != 1 && $processFurther) { typingTo(); }
		if (defined('DISABLE_ANNOUNCEMENTS') && DISABLE_ANNOUNCEMENTS != 1 && $processFurther) { checkAnnoucements(); }

		if ($processFurther) {
			fetchMessages();
		}
	}

        $time = getTimeStamp();

	if ($processFurther) {
		if (empty($_SESSION['cometchat']['cometchat_lastlactivity']) || ($time-$_SESSION['cometchat']['cometchat_lastlactivity'] >= REFRESH_BUDDYLIST/4)) {
			$sql = updateLastActivity($userid);
                        if (function_exists('hooks_updateLastActivity')) {
                            hooks_updateLastActivity($userid);
                        }

			$query = mysqli_query($GLOBALS['dbh'],$sql);
			if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
			$_SESSION['cometchat']['cometchat_lastlactivity'] = $time;
		}
		if (!empty($_REQUEST['typingto']) && $_REQUEST['typingto'] != 0 && DISABLE_ISTYPING != 1) {
			$sql = ("insert into cometchat_status (userid,typingto,typingtime) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['typingto'])."','".getTimeStamp()."') on duplicate key update typingto = '".mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['typingto'])."', typingtime = '".getTimeStamp()."'");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
		}
    }

} else {

	$response['loggedout'] = '1';
	setcookie($cookiePrefix.'state','',time()-3600,'/');
	unset($_SESSION['cometchat']);
}

function getStatus() {
	global $response;
	global $userid;
	global $status;
	global $startOffline;
	global $processFurther;

        if ($userid > 10000000) {
            $sql = getGuestDetails($userid);
        } else {
            $sql = getUserDetails($userid);
        }
 	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	$chat = mysqli_fetch_assoc($query);

	if (!empty($_REQUEST['callbackfn'])) {
		$_SESSION['cometchat']['startoffline'] = 1;
	}

	if ($startOffline == 1 && empty($_SESSION['cometchat']['startoffline'])) {
		$_SESSION['cometchat']['startoffline'] = 1;
		$chat['status'] = 'offline';
		setStatus('offline');
		$_SESSION['cometchat']['cometchat_sessionvars']['buddylist'] = 0;

		$processFurther = 0;
	} else {
		if (empty($chat['status'])) {
			$chat['status'] = 'available';
		} else {
			if ($chat['status'] == 'away') {
				$chat['status'] = 'available';
				setStatus('available');
			}

			if ($chat['status'] == 'offline') {
				$processFurther = 0;
				$_SESSION['cometchat']['cometchat_sessionvars']['buddylist'] = 0;
			}

		}
	}

	if (empty($chat['message'])) {
		$chat['message'] = $status[$chat['status']];
	}

	$channelprefix = '';

	if(preg_match('/www\./', $_SERVER['HTTP_HOST']))
	{
		$channelprefix = $_SERVER['HTTP_HOST'];
	}else
	{
		$channelprefix = 'www.'.$_SERVER['HTTP_HOST'];
	}

	$chat['message'] = html_entity_decode($chat['message']);

    $s = array('id' => $chat['userid'], 'n' => $chat['username'], 'l' => fetchLink($chat['link']), 'a' => getAvatar($chat['avatar']), 's' => $chat['status'], 'm' => $chat['message'],'push_channel' => md5($channelprefix."USER_".$userid.BASE_URL));

	$response['userstatus'] = $_SESSION['cometchat']['user'] = $s;
}

function setStatus($message) {

	global $userid;

	$sql = ("insert into cometchat_status (userid,status) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".mysqli_real_escape_string($GLOBALS['dbh'],sanitize_core($message))."') on duplicate key update status = '".mysqli_real_escape_string($GLOBALS['dbh'],sanitize_core($message))."'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	if (function_exists('hooks_activityupdate')) {
		hooks_activityupdate($userid,$message);
	}

}

function getLastTimestamp() {
	if (empty($_REQUEST['timestamp'])) {
		$_REQUEST['timestamp'] = 0;
	}

	if ($_REQUEST['timestamp'] == 0) {
		foreach ($_SESSION['cometchat'] as $key => $value) {
			if (substr($key,0,15) == "cometchat_user_") {
				if (!empty($_SESSION['cometchat'][$key]) && is_array($_SESSION['cometchat'][$key])) {
					$temp = end($_SESSION['cometchat'][$key]);
					if (isset($temp['id']) && $_REQUEST['timestamp'] < $temp['id']) {
						$_REQUEST['timestamp'] = $temp['id'];
					}
				}
			}
		}

		if ($_REQUEST['timestamp'] == 0) {
			$sql = ("select id from cometchat order by id desc limit 1");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
			$chat = mysqli_fetch_assoc($query);

			$_REQUEST['timestamp'] = $chat['id'];
		}
	}

}


function getBuddyList() {
	global $response;
	global $userid;
	global $db;
	global $status;
	global $hideOffline;
	global $plugins;
	global $guestsMode;
	global $cookiePrefix;
    global $chromeReorderFix;

	$time = getTimeStamp();
	$buddyList = array();

	if ((empty($_SESSION['cometchat']['cometchat_buddytime'])) || (!empty($_REQUEST['initialize']) && $_REQUEST['initialize'] == 1)  || ($_REQUEST['f'] == 1)  || (!empty($_SESSION['cometchat']['cometchat_buddytime']) && ($time-$_SESSION['cometchat']['cometchat_buddytime'] >= REFRESH_BUDDYLIST || MEMCACHE <> 0))) {

		if ((!empty($_REQUEST['initialize']) && $_REQUEST['initialize'] == 1) && !empty($_SESSION['cometchat']['cometchat_buddyblh']) && ($time-$_SESSION['cometchat']['cometchat_buddytime'] < REFRESH_BUDDYLIST)) {

			$response['buddylist'] = $_SESSION['cometchat']['cometchat_buddyresult'];
			$response['blh'] = $_SESSION['cometchat']['cometchat_buddyblh'];

		} else {

			$onlineCacheKey = 'all_online';
			if($userid > 10000000){
				$onlineCacheKey .= 'guest';
			}

			if (false) {
				$buddyList = unserialize($onlineUsers);
			} else {
				$sql = getFriendsList($userid,$time);
				if ($guestsMode) {
					$sql = getGuestsList($userid,$time,$sql);
				}
				$query = mysqli_query($GLOBALS['dbh'],$sql);
				if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

				while ($chat = mysqli_fetch_assoc($query)) {
					if (((($time-processTime($chat['lastactivity'])) < ONLINE_TIMEOUT) || $chat['isdevice'] == 1) && $chat['status'] != 'invisible' && $chat['status'] != 'offline') {
						if (($chat['status'] != 'busy' && $chat['status'] != 'away') || $chat['isdevice'] == 1) {
							$chat['status'] = 'available';
						}
					} else {
						$chat['status'] = 'offline';
					}

					if ($chat['message'] == null) {
						$chat['message'] = $status[$chat['status']];
					}

					$link = fetchLink($chat['link']);
					$avatar = getAvatar($chat['avatar']);

					if (function_exists('processName')) {
						$chat['username'] = processName($chat['username']);
					}

					if (empty($chat['grp'])) {
						$chat['grp'] = '';
					}

					if (!empty($chat['username'])) {
						$buddyList[$chromeReorderFix.$chat['userid']] = array('id' => $chat['userid'], 'n' => $chat['username'], 'l' => $link,  'a' => $avatar, 'd' => $chat['isdevice'], 's' => $chat['status'], 'm' => $chat['message'], 'g' => $chat['grp']);
					}
				}
			}

			if (in_array('block',$plugins)) {
				$blockId = array();
				if($blockedUsers = getCache($cookiePrefix.'blocked_id_of_'.$userid, 30)) {
					$blockId = unserialize($blockedUsers);
				} else {
					$sql = ("select group_concat(toid) blockedids from cometchat_block where fromid = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."'");
					$query = mysqli_query($GLOBALS['dbh'],$sql);
					$blockIds = mysqli_fetch_assoc($query);
					if (!empty($blockIds['blockedids'])) {
					$blockId = explode(',',$blockIds['blockedids']);
					}
					setCache($cookiePrefix.'blocked_id_of_'.$userid,serialize($blockId),3600);
				}

				foreach ($blockId as $bid) {
					if (isset($buddyList[$chromeReorderFix.$bid])) {
						$buddyList[$chromeReorderFix.$bid]['s'] = 'banned';
					}
				}
			}


			if (isset($buddyList[$chromeReorderFix.$userid])) {
				unset($buddyList[$chromeReorderFix.$userid]);
			}

			if (function_exists('hooks_forcefriends') && is_array(hooks_forcefriends())) {
				$buddyList = array_merge(hooks_forcefriends(),$buddyList);
			}

			$_SESSION['cometchat']['cometchat_buddytime'] = $time;

			$blh = md5(serialize($buddyList));

			if ((empty($_REQUEST['blh'])) || (!empty($_REQUEST['blh']) && $blh != $_REQUEST['blh'])) {
				$response['buddylist'] = $buddyList;
				$response['blh'] = $blh;
			}

			$_SESSION['cometchat']['cometchat_buddyresult'] = $buddyList;
			$_SESSION['cometchat']['cometchat_buddyblh'] = $blh;
		}
	}
}

function fetchMessages() {
	global $response;
	global $userid;
	global $db;
	global $messages;
	global $cookiePrefix;
	global $chromeReorderFix;
	$timestamp = 0;

	if (USE_COMET == 1) { return; }

	$sql = ("select cometchat.id, cometchat.from, cometchat.to, cometchat.message, cometchat.sent, cometchat.read, cometchat.direction from cometchat where ((cometchat.to = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and cometchat.direction <> 2) or (cometchat.from = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and cometchat.direction <> 1)) and (cometchat.id > '".mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['timestamp'])."' or (cometchat.to = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and cometchat.read != 1)) order by cometchat.id");

	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	while ($chat = mysqli_fetch_assoc($query)) {

		$self = 0;
		$old = 0;
		if ($chat['from'] == $userid) {
			$chat['from'] = $chat['to'];
			$self = 1;
			$old = 1;
		}

		if ($chat['read'] == 1) {
			$old = 1;
		}

		if (!empty($_COOKIE[$cookiePrefix.'lang']) && $self == 0 && $old == 0) {

			$translated = text_translate($chat['message'],'',$_COOKIE[$cookiePrefix.'lang']);

			if ($translated != '') {
				$chat['message'] = strip_tags($translated).' <span class="untranslatedtext">('.$chat['message'].')</span>';
			}
		}

		$messages[$chromeReorderFix.$chat['id']] = array('id' => $chat['id'], 'from' => $chat['from'], 'message' => $chat['message'], 'self' => $self, 'old' => $old, 'sent' => ($chat['sent']));

		if (empty($SESSION['cometchat']['cometchat_user'.$chat['from']][$chromeReorderFix.$chat['id']]['id'])) {
			$_SESSION['cometchat']['cometchat_user_'.$chat['from']][$chromeReorderFix.$chat['id']] = array('id' => $chat['id'], 'from' => $chat['from'], 'message' => $chat['message'], 'self' => $self, 'old' => 1, 'sent' => ($chat['sent']));
		}

		$timestamp = $chat['id'];
	}

	if (!empty($messages) && (empty($_REQUEST['callbackfn']) || (isset ($_REQUEST['callbackfn']) && $_REQUEST['callbackfn'] != 'ccmobiletab'))) {
		$sql = ("update cometchat set cometchat.read = '1' where cometchat.to = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and cometchat.id <= '".mysqli_real_escape_string($GLOBALS['dbh'],$timestamp)."'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
	}
}

function typingTo() {
	global $response;
	global $userid;
	global $db;
	global $messages;
	$timestamp = 0;

	if (USE_COMET == 1) { return; }

	$sql = ("select GROUP_CONCAT(userid, ',') from cometchat_status where typingto = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and ('".getTimeStamp()."'-typingtime < 10)");
	$query = mysqli_query($GLOBALS['dbh'],$sql);
	if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

	$chat = mysqli_fetch_assoc($query);

	if (!empty($chat[0])) {
		$response['tt'] = $chat[0];
	} else {
		$response['tt'] = '';
	}
}

function checkAnnoucements() {
	global $response;
	global $userid;
	global $db;
	global $messages;
	global $cookiePrefix;
	global $notificationsFeature;
	global $notificationsClub;

	$timestamp = 0;

	if ($notificationsFeature) {

		$sql = ("select count(id) as count from cometchat_announcements where `to` = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and  `recd` = '0'");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
		$count = mysqli_fetch_assoc($query);
		$count = $count['count'];

		if ($count > 0) {
			$sql = ("select id,announcement from cometchat_announcements where `to` = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."' and  `recd` = '0' order by id desc limit 1");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
			$announcement = mysqli_fetch_assoc($query);

			if (!empty($announcement[1])) {
				$sql = ("update cometchat_announcements set `recd` = '1' where `id` <= '".mysqli_real_escape_string($GLOBALS['dbh'],$announcement[0])."' and `to`  = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."'");
				$query = mysqli_query($GLOBALS['dbh'],$sql);

				$response['an'] = array('id' => $announcement[0], 'm' => $announcement[1], 'o' => $count);
				return;
			}
		}
	}

	if ($latest_announcement = getCache($cookiePrefix.'latest_announcement',30)) {
		$announcement = unserialize($latest_announcement);
	} else {
		$sql = ("select id,announcement from cometchat_announcements where `to` = '0' or `to` = '-1' order by id desc limit 1");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
		$announcement = mysqli_fetch_assoc($query);
		$latest_announcements = array();
		if(!empty($announcement['id'])) {
			$latest_announcements = array('id' =>$announcement['id'],'announcement' =>$announcement['announcement']);
		}
		setCache($cookiePrefix.'latest_announcement',serialize($latest_announcements),3600);
	}

	if (!empty($announcement['announcement']) && (empty($_COOKIE[$cookiePrefix.'an']) || (!empty($_COOKIE[$cookiePrefix.'an']) && $_COOKIE[$cookiePrefix.'an'] < $announcement['id']))) {
		$response['an'] = array('id' => $announcement['id'], 'm' => $announcement['announcement']);
	}
}

header('Content-type: application/json; charset=utf-8');

if (isset($response['initialize'])) {
	$initialize = $response['initialize'];
	unset($response['initialize']);
	$response['initialize'] = $initialize;
}

if (!empty($messages)) {
	$response['messages'] = $messages;
}


$useragent = (isset($_SERVER["HTTP_USER_AGENT"])) ? $_SERVER["HTTP_USER_AGENT"] : '';
if(phpversion()>='4.0.4pl1'&&(strstr($useragent,'compatible')||strstr($useragent,'Gecko'))){
	if(extension_loaded('zlib')&&GZIP_ENABLED==1 && !in_array('ob_gzhandler', ob_list_handlers())){
		ob_start('ob_gzhandler');
	}else{
		ob_start();
	}
}else{
	ob_start();
}
if (!empty($_GET['callback'])) {
	echo $_GET['callback'].'('.json_encode($response).')';
} else {
	echo json_encode($response);
}
exit;

