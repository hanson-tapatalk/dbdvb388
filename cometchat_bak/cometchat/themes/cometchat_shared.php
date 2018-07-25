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
global $dbh,$userid,$memcache;

if(!function_exists("mysqli_connect")){

	function mysqli_connect($db_server,$db_username,$db_password,$db_name,$port){
		return mysql_connect($db_server.':'.$port,$db_username,$db_password);
	}

	function mysqli_real_escape_string($dbh,$userid){
		return mysql_real_escape_string($userid);
	}

	function mysqli_select_db($dbh,$db_name){
		return mysql_select_db($db_name,$dbh);
	}

	function mysqli_connect_errno($dbh){
		return !$dbh;
	}

	function mysqli_query($dbh,$sql){
		return mysql_query($sql);
	}

	function mysqli_error($dbh){
		return mysql_error();
	}

	function mysqli_fetch_assoc($query){
		return mysql_fetch_assoc($query);
	}

	function mysqli_insert_id($dbh){
		return mysql_insert_id();
	}

	function mysqli_num_rows($query){
		return mysql_num_rows($query);
	}
}

function cometchatDBConnect()
{
	global $dbh;
	$port = DB_PORT;
	if(empty($port)){
		$port = '3306';
	}

	$dbserver = explode(':',DB_SERVER);

	if(!empty($dbserver[1])){
	    $port = $dbserver[1];
	}

	$db_server = $dbserver[0];
	$dbh = mysqli_connect($db_server,DB_USERNAME,DB_PASSWORD,DB_NAME,$port);

	if (mysqli_connect_errno($dbh)) {
		$dbh = mysqli_connect(DB_SERVER,DB_USERNAME,DB_PASSWORD,DB_NAME,$port,'/tmp/mysql5.sock');
	}

	if (mysqli_connect_errno($dbh)) {
		echo "<h3>Unable to connect to database due to following error(s). Please check details in configuration file.</h3>";
		if (!defined('DEV_MODE') || (defined('DEV_MODE') && DEV_MODE != '1')){
			ini_set('display_errors','On');
			echo mysqli_connect_error($dbh);
			ini_set('display_errors','Off');
		}
		exit();
	}

	mysqli_select_db($dbh,DB_NAME);
	mysqli_query($dbh,"SET NAMES utf8");
	mysqli_query($dbh,"SET CHARACTER SET utf8");
	mysqli_query($dbh,"SET COLLATION_CONNECTION = 'utf8_general_ci'");
}

function cometchatMemcacheConnect(){
	include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."cometchat_cache.php");
	global $memcache;
	if(MC_NAME=='memcachier'){
		$memcache = new MemcacheSASL();
		$memcache->addServer(MC_SERVER,MC_PORT);
		$memcache->setSaslAuthData(MC_USERNAME,MC_PASSWORD);
	}elseif(MEMCACHE!=0){
		phpFastCache::setup("path",dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'cache');
		phpFastCache::setup("storage",MC_NAME);
		$memcache = phpFastCache();
	}
}

function sanitize($text) {
	global $smileys;
	global $smileys_default;

	$temp = $text;
	$text = sanitize_core($text);
	$text = $text." ";
	$text = str_replace('&amp;','&',$text);

	$search  = "/((?#Email)(?:\S+\@)?(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/|~\/|\/)?(?#Username:Password)(?:\w+:\w+@)?(?#Subdomains)(?:(?:[-\w]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|a[cdefgilmnoqrstuwz]|b[abdefghijmnorstvwyz]|c[acdfghiklmnoruvxyz]|d[ejkmnoz]|e[ceghrst]|f[ijkmnor]|g[abdefghilmnpqrstuwy]|h[kmnrtu]|i[delmnoqrst]|j[emop]|k[eghimnprwyz]|l[abcikrstuvy]|m[acdghklmnopqrstuvwxyz]|n[acefgilopruz]|om|p[aefghklmnrstwy]|qa|r[eouw]|s[abcdeghijklmnortuvyz]|t[cdfghjkmnoprtvwz]|u[augkmsyz]|v[aceginu]|w[fs]|y[etu]|z[amw]|aero|arpa|biz|com|coop|edu|info|int|gov|mil|museum|name|net|org|pro))(?#Port)(?::[\d]{1,5})?(?#Directories)(?:(?:(?:\/(?:[-\w~!$+|.,=]|%[a-f\d]{2})+)+|\/)+|#)?(?#Query)(?:(?:\?(?:[-\w~!$+|\/.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)(?:&(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)*)*(?#Anchor)(?:#(?:[-\w~!$+|\/.,*:=]|%[a-f\d]{2})*)?)([^[:alpha:]]|\?)/i";

	if (DISABLE_LINKING != 1) {
		$text = preg_replace_callback($search, "autolink", $text);
	}
	if (DISABLE_SMILEYS != 1) {

		foreach ($smileys_default as $default_pattern => $default_result) {
		$title = str_replace("-"," ",ucwords(preg_replace("/\.(.*)/","",$default_result)));
		$class = str_replace("-"," ",preg_replace("/\.(.*)/","",$default_result));
		$text = str_ireplace(str_replace('&amp;','&',htmlspecialchars($default_pattern, ENT_NOQUOTES)).' ','<img class="cometchat_smiley" height="20" width="20" src="'.BASE_URL.'images/smileys/'.$default_result.'" title="'.$title.'"> ',$text.' ');
		}

		foreach ($smileys as $pattern => $result) {
			$title = str_replace("-"," ",ucwords(preg_replace("/\.(.*)/","",$result)));
			$class = str_replace("-"," ",preg_replace("/\.(.*)/","",$result));
			$text = str_ireplace(str_replace('&amp;','&',htmlspecialchars($pattern, ENT_NOQUOTES)).' ','<img class="cometchat_smiley" height="20" width="20" src="'.BASE_URL.'images/smileys/'.$result.'" title="'.$title.'"> ',$text.' ');
		}
	}
	return trim($text);
}

function sanitize_core($text) {
	global $bannedWords;
	$text = htmlspecialchars($text, ENT_NOQUOTES);
	$text = str_replace("\n\r","\n",$text);
	$text = str_replace("\r\n","\n",$text);
	$text = str_replace("\n"," <br> ",$text);

	for ($i=0;$i < count($bannedWords);$i++) {
		$text = str_ireplace(' '.$bannedWords[$i].' ',' '.$bannedWords[$i][0].str_repeat("*",strlen($bannedWords[$i])-1).' ',' '.$text.' ');
	}
	$text = trim($text);
	return $text;
}

function autolink($matches) {

	$link = $matches[1];

	if (preg_match("/\@/",$matches[1])) {
		$text = "<a href=\"mailto: {$link}\">{$matches[0]}</a>";
	} else {
		if (!preg_match("/(file|gopher|news|nntp|telnet|http|ftp|https|ftps|sftp):\/\//",$matches[1])) {
			$link = "http://".$matches[1];
		}

		if (DISABLE_YOUTUBE != 1 && preg_match('#(?:<\>]+href=\")?(?:http://)?((?:[a-zA-Z]{1,4}\.)?youtube.com/(?:watch)?\?v=(.{11}?))[^"]*(?:\"[^\<\>]*>)?([^\<\>]*)(?:)?#',$link,$match)) {

			/*

			// Bandwidth intensive function to fetch details about the YouTube video

			$contents = file_get_contents("http://gdata.youtube.com/feeds/api/videos/{$match[2]}?alt=json");

			$data = json_decode($contents);
			$title = $data->entry->title->{'$t'};

			if (strlen($title) > 50) {
				$title = substr($title,0,50)."...";
			}

			$description = substr($data->entry->content->{'$t'},0,100);
			$length = seconds2hms($data->entry->{'media$group'}->{'yt$duration'}->seconds);
			$rating = $data->entry->{'gd$rating'}->average;

			*/

			$text = '<a href="'.$link.'" target="_blank">'.$link.'</a><br/><a href="'.$link.'" target="_blank" style="display:inline-block;margin-bottom:3px;margin-top:3px;"><img src="http://img.youtube.com/vi/'.$match[2].'/default.jpg" border="0" style="padding:0px;display: inline-block; width: 120px;height:90px;">
			<div style="margin-top:-30px;text-align: right;width:110px;margin-bottom:10px;">
			<img height="20" border="0" width="20" style="opacity: 0.88;" src="'.BASE_URL.'images/play.gif"/>
			</div></a>';

		} else {
			$text = $matches[1];

			if (strlen($matches[1]) > 30) {
				$left = substr($matches[1],0,22);
				$right = substr($matches[1],-5);
				$matches[1] = $left."...".$right;
			}

			$text = "<a href=\"{$link}\" target=\"_blank\" title=\"{$text}\">{$matches[1]}</a>$matches[2]";
		}
	}


	return $text;
}

function seconds2hms ($sec, $padHours = true) {
	$hms = "";
	$hours = intval(intval($sec) / 3600);
	if ($hours != 0) {
		$hms .= ($padHours) ? str_pad($hours, 2, "0", STR_PAD_LEFT). ':' : $hours. ':';
	}

	$minutes = intval(($sec / 60) % 60);
	$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ':';
$seconds = intval($sec % 60);
	$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
	return $hms;
}

function sendMessageTo($to,$message) {
	$response = sendMessage($to,$message,1);
	parsePusher($to,$response['id'],$response['m']);
}

function sendSelfMessage($to,$message,$sessionMessage = '') {
	$id_message = sendMessage($to,$message,2);
	return $id_message;
}

function sendMessage($to,$message,$dir = 0) {

	global $userid;
	global $cookiePrefix;

	if (!empty($to) && isset($message) && $message!='' && $userid > 0) {
		if($dir === 0){
			$message = str_ireplace('CC^CONTROL_','',$message);
			$message = sanitize($message);
		}

		if (!empty($_REQUEST['callback'])) {
		    if (!empty($_SESSION['cometchat']['duplicates'][$_REQUEST['callback']])) {
		        exit;
		    }
		    $_SESSION['cometchat']['duplicates'][$_REQUEST['callback']] = 1;
		}

		if (USE_COMET == 1) {

			$insertedid = getTimeStamp().rand(100,999);
			$response = array("id" => $insertedid, "m" => $message);

            $key = '';
			if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
				$key = KEY_A.KEY_B.KEY_C;
			}

			$key_prefix = $dir === 2 ? $userid:$to;
			$from = $dir === 2 ? $to:$userid;
			$self = $dir === 2 ? 1 : 0;
			$channel = md5($key_prefix.$key);
			$comet = new Comet(KEY_A,KEY_B);
			$info = $comet->publish(array(
				'channel' => $channel,
				'message' => array ( "from" => $from, "message" => ($message), "sent" => $insertedid, "self" => $self)
			));

			if (defined('SAVE_LOGS') && SAVE_LOGS == 1) {
				$sql = ("insert into cometchat (cometchat.from,cometchat.to,cometchat.message,cometchat.sent,cometchat.read, cometchat.direction) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."', '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."','".mysqli_real_escape_string($GLOBALS['dbh'],$message)."','".getTimeStamp()."',1,$dir)");
				$query = mysqli_query($GLOBALS['dbh'],$sql);
			}
		} else {
			$sql = ("insert into cometchat (cometchat.from,cometchat.to,cometchat.message,cometchat.sent,cometchat.read, cometchat.direction) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."', '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."','".mysqli_real_escape_string($GLOBALS['dbh'],$message)."','".getTimeStamp()."',0,$dir)");
			$query = mysqli_query($GLOBALS['dbh'],$sql);

			if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }

			$insertedid = mysqli_insert_id($GLOBALS['dbh']);
			$response = array("id" => $insertedid, "m" => $message);
   		}
   		return array("id" => $insertedid,"m" => $message);
	}
}

function sendChatroomMessage($to = 0,$message = '',$notsilent = 1) {
	global $userid;
	global $cookiePrefix;
	global $bannedUserIDs;

	if(($to == 0 && empty($_POST['currentroom'])) || ($message == '' && $notsilent == 0) || (isset($_POST['message']) && $_POST['message'] == '') || empty($userid) || in_array($userid, $bannedUserIDs)){
		return;
	}

	if (isset($_POST['message']) && !empty($_POST['currentroom'])) {
		$to = $_POST['currentroom'];
		$message = $_POST['message'];
	}

	if($notsilent !== 0){
		$message = str_ireplace('CC^CONTROL_','',$message);
		$message = sanitize($message);
	}

	$styleStart = '';
	$styleEnd = '';

	if (!empty($_COOKIE[$cookiePrefix.'chatroomcolor']) && preg_match('/^[a-f0-9]{6}$/i', $_COOKIE[$cookiePrefix.'chatroomcolor']) && $notsilent == 1) {
		$styleStart = '<span style="color:#'.$_COOKIE[$cookiePrefix.'chatroomcolor'].'">';
		$styleEnd = '</span>';
	}

	if (USE_COMET == 1 && COMET_CHATROOMS == 1) {
		$insertedid = getTimeStamp().rand(100,999);

		if($notsilent == 1){
			sendCCResponse(json_encode(array("id" => $insertedid,"m" => $styleStart.$message.$styleEnd)));
		}

		$comet = new Comet(KEY_A,KEY_B);
		if (empty($_SESSION['cometchat']['username'])) {
			$name = '';
			$sql = getUserDetails($userid);

			if($userid>10000000) $sql = getGuestDetails($userid);
			$result = mysqli_query($GLOBALS['dbh'],$sql);

			if($row = mysqli_fetch_assoc($result)) {
				if (function_exists('processName')) {
					$row['username'] = processName($row['username']);
				}
				$name = $row['username'];
			}

			$_SESSION['cometchat']['username'] = $name;
		} else {
			$name = $_SESSION['cometchat']['username'];
		}



		if (!empty($name)) {
			$info = $comet->publish(array(
					'channel' => md5('chatroom_'.$to.KEY_A.KEY_B.KEY_C),
					'message' => array ( "from" => $name, "fromid"=> $userid, "message" => $styleStart.$message.$styleEnd, "sent" => $insertedid)
				));
			if (defined('SAVE_LOGS') && SAVE_LOGS == 1) {
				$sql = ("insert into cometchat_chatroommessages (userid,chatroomid,message,sent) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."', '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."','".$styleStart.mysqli_real_escape_string($GLOBALS['dbh'],$message).$styleEnd."','".getTimeStamp()."')");
				$query = mysqli_query($GLOBALS['dbh'],$sql);
			}
		}
	} else {
		$sql = ("insert into cometchat_chatroommessages (userid,chatroomid,message,sent) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."', '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."','".$styleStart.mysqli_real_escape_string($GLOBALS['dbh'],$message).$styleEnd."','".getTimeStamp()."')");
		$query = mysqli_query($GLOBALS['dbh'],$sql);
		$insertedid = mysqli_insert_id($GLOBALS['dbh']);

		if($notsilent == 1){
			sendCCResponse(json_encode(array("id" => $insertedid,"m" => $styleStart.$message.$styleEnd)));
		}

		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
	}

	parsePusher($to,$insertedid,$message,'1');

	$sql = ("update cometchat_chatrooms set lastactivity = '".getTimeStamp()."' where id = '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."'");
	$query = mysqli_query($GLOBALS['dbh'],$sql);

	if($notsilent != 0) {
			return $insertedid;
		}
}

function sendAnnouncement($to,$message) {
	global $userid;

	if (!empty($to) && !empty($message)) {

		$sql = ("insert into cometchat_announcements (announcement,time,`to`) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$message)."', '".getTimeStamp()."','".mysqli_real_escape_string($GLOBALS['dbh'],$to)."')");
		$query = mysqli_query($GLOBALS['dbh'],$sql);

		if (defined('DEV_MODE') && DEV_MODE == '1') { echo mysqli_error($GLOBALS['dbh']); }
	}
}

function getChatboxData($id) {
	global $messages;
	global $userid;
	global $chromeReorderFix;

	if (!empty($id) && USE_COMET == 1) {

		if (!empty($_SESSION['cometchat']['cometmessagesafter'])) {
			$key = '';
			if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
				$key = KEY_A.KEY_B.KEY_C;
			}
			$channel = md5($userid.$key);

			$comet = new Comet(KEY_A,KEY_B);
			$history = $comet->history(array(
			  'channel' => $channel,
			  'limit'   => COMET_HISTORY_LIMIT
			));

			if (!empty($_SESSION['cometchat']['cometchat_user_'.$id])) {
				$messages = array_merge($messages,$_SESSION['cometchat']['cometchat_user_'.$id]);
			}

			$moremessages = array();

			$messagesafter = $_SESSION['cometchat']['cometmessagesafter'];

			if (!empty($_SESSION['cometchat']['cometchat_user_'.$id.'_clear']) && $_SESSION['cometchat']['cometchat_user_'.$id.'_clear']['timestamp'] > $_SESSION['cometchat']['cometmessagesafter']) {
				$messagesafter = $_SESSION['cometchat']['cometchat_user_'.$id.'_clear']['timestamp'];
			}

			if (!empty($history)) {
				foreach ($history as $key => $message) {

					if ($message['from'] == $id && $message['sent'] >= $messagesafter) {
						$moremessages[$chromeReorderFix.$message['sent']] = array("id" => $message['sent'], "from" => $message['from'], "message" => $message['message'], "self" => $message['self'], "old" => 1, 'sent' => (($message['sent']/1000)));
					}
				}
			}
			$messages = array_merge($messages,$moremessages);
			usort($messages, 'comparetime');

		}
	} else {
		if (!empty($id) && !empty($_SESSION['cometchat']['cometchat_user_'.$id])) {
			$messages = array_merge($messages,$_SESSION['cometchat']['cometchat_user_'.$id]);
		}
		$prependLimit = 10;
		if(!empty($_REQUEST['prepend'])){
			$messages = array();
			$prepend = intval($_REQUEST['prepend']);
			$custom = "AND ((cometchat.direction = 2 AND cometchat.from = $userid ) OR (cometchat.direction = 0) OR (cometchat.direction = 1 AND cometchat.to = $userid) )";
			if($prepend != -1){
				$sql = "SELECT * from cometchat where ((cometchat.from = ".$userid." AND cometchat.to = ".$id.") OR ( cometchat.from = ".$id." AND cometchat.to = ".$userid.")) AND (cometchat.id < $prepend) $custom ORDER BY cometchat.id desc LIMIT $prependLimit;";
			} else {
				$sql = "SELECT * from cometchat where ((cometchat.from = ".$userid." AND cometchat.to = ".$id.") OR ( cometchat.from = ".$id." AND cometchat.to = ".$userid.")) $custom ORDER BY cometchat.id desc LIMIT $prependLimit;";
			}

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

				$messages[$chromeReorderFix.$chat['id']] = array('id' => $chat['id'], 'from' => $chat['from'], 'message' => $chat['message'], 'self' => $self, 'old' => $old, 'sent' => ($chat['sent']));
			}
			$messages = array_reverse($messages);
		}
	}
}

function comparetime($a, $b) { return strnatcmp($a['sent'], $b['sent']); }

function text_translate($text, $from = 'en', $to = 'en') {
	include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'realtimetranslate'.DIRECTORY_SEPARATOR.'translate.php');
	return translate_text($text,$from,$to);
}

function unescapeUTF8EscapeSeq($str) {
	return preg_replace_callback("/\\\u([0-9a-f]{4})/i", create_function('$matches', 'return bin2utf8(hexdec($matches[1]));'), $str);
}

function bin2utf8($bin) {
	if ($bin <= 0x7F) {
		return chr($bin);
	} elseif ($bin >= 0x80 && $bin <= 0x7FF) {
		return pack("C*", 0xC0 | $bin >> 6, 0x80 | $bin & 0x3F);
	} else if ($bin >= 0x800 && $bin <= 0xFFF) {
		return pack("C*", 0xE0 | $bin >> 11, 0x80 | $bin >> 6 & 0x3F, 0x80 | $bin & 0x3F);
	} else if ($bin >= 0x10000 && $bin <= 0x10FFFF) {
		return pack("C*", 0xE0 | $bin >> 17, 0x80 | $bin >> 12 & 0x3F, 0x80 | $bin >> 6& 0x3F, 0x80 | $bin & 0x3F);
	}
}

function checkcURL($http = 0, $url = '', $params = '', $return = 0, $cookiefile = '') {
	if (!function_exists('curl_init')) {
		return false;
	}
	if (empty($url)) {
		if ($http == 0) {
			$url = 'http://www.microsoft.com';
		} else {
			$url = 'https://www.microsoft.com';
		}
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	if (!empty($cookiefile)) {
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);
	}
	if ($return == 1) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
	}

	$data = curl_exec($ch);
	curl_close($ch);
	if ($return == 1) {
		return $data;
	}
	if (empty($data)) {
		return false;
	}
	return true;
}

function setCache($key,$contents,$timeout = 30) {
	if (MEMCACHE == 0) {
		return false;
	}
	removeCache($key);
	$contentstarray = unserialize($contents);
	if (!empty($contentstarray)) {
		global $memcache;
		$memcache->set($key,$contents,$timeout);
	} else {
		$_SESSION['cometchat']['memcache'][$key] = time();
	}
}

function getCache($key, $timeout = 30) {
	$contents = false;
	if (MEMCACHE <> 0) {
		global $memcache;
		$contents = $memcache->get($key);
		if (empty($contents)) {
			if (!empty($_SESSION['cometchat']['memcache'][$key])) {
				if ((time() - $_SESSION['cometchat']['memcache'][$key]) < $timeout) {
					$contentstarray = array();
					$contents = serialize($contentstarray);
				} else {
					unset($_SESSION['cometchat']['memcache'][$key]);
				}
			}
		}
	}
	return $contents;
}

function removeCache($key) {
	if (MEMCACHE == 0) {
		return;
	} else {
		global $memcache;
		$memcache->delete($key);
		unset($_SESSION['cometchat']['memcache'][$key]);
	}
}

function parsePusher($to,$insertedid,$message,$isChatroom = '0'){
	include_once (dirname(__FILE__).DIRECTORY_SEPARATOR."extensions".DIRECTORY_SEPARATOR."mobileapp".DIRECTORY_SEPARATOR."parse_push.php");
	global $userid;

	$channelprefix = '';

	if(preg_match('/www\./', $_SERVER['HTTP_HOST']))
	{
		$channelprefix = $_SERVER['HTTP_HOST'];
	}else
	{
		$channelprefix = 'www.'.$_SERVER['HTTP_HOST'];
	}

	if($isChatroom === '0'){
		$rawMessage = array("name" => $_SESSION['cometchat']['user']['n'], "fid"=> $userid, "m" => $message, "sent" => $insertedid);
		if(strlen($insertedid) < 13) {
			$rawMessage['id'] = $insertedid;
		}
		$channel = md5($channelprefix."USER_".$to.BASE_URL);
	} else {
		$parse_message = $_SESSION['cometchat']['user']['n']."@".$_SESSION['cometchat']['chatroom']['n'].": ".$message;
		if (strpos($message, "has shared a file") !== false) {
			$parse_message = $_SESSION['cometchat']['user']['n']."@".$_SESSION['cometchat']['chatroom']['n'].": "."has shared a file";
		}

		$rawMessage = array( "id" => $insertedid, "from" => $_SESSION['cometchat']['user']['n'], "fid"=> $userid, "m" => sanitize($parse_message), "sent" => $insertedid, "cid" => $to);
		$channel = md5($channelprefix."CHATROOM_".$to.BASE_URL);
	}

	$parse = new Parse();
	$parse->sendNotification($channel, $rawMessage, $isChatroom);
}

function incrementCallback(){
	if(!empty($_REQUEST['callback'])){
		$explodedCallback = explode('_',$_REQUEST['callback']);
		$explodedCallback[1]++;
		$_REQUEST['callback'] = implode('_', $explodedCallback);
	}
}
function decrementCallback(){
	if(!empty($_REQUEST['callback'])){
		$explodedCallback = explode('_',$_REQUEST['callback']);
		$explodedCallback[1]--;
		$_REQUEST['callback'] = implode('_', $explodedCallback);
	}
}

function sendCCResponse($response){
	@ob_end_clean();
	header("Connection: close");
	ignore_user_abort();

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
	echo $response;

	$size = ob_get_length();
	header("Content-Length: $size");
	ob_end_flush();
	flush();
}
