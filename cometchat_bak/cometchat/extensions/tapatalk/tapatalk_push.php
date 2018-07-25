<?php

include_once (dirname(__FILE__).DIRECTORY_SEPARATOR."config.php");
include_once (dirname(__FILE__).DIRECTORY_SEPARATOR."emoji.php");

class TapatalkPush {

	private $pushUrl;
	private $pushApiKey;

	public function TapatalkPush() {
		$this->pushUrl = 'https://push.tapatalk.com/push.php';
		$this->pushApiKey = PUSH_KEY;

	}

	public function sendNotification($toId,$fromId,$fromName,$messageId,$messageData,$test=0) {
		global $emojiUTF8;

		if(strpos($messageData,'cometchat_smiley')!==false){
		preg_match_all('/<img[^>]+\>/i',$messageData,$matches);
			for($i=0;$i<sizeof($matches[0]);$i++){
				$msgpart = (explode('/images/smileys/',$matches[0][$i]));
				$imagenamearr = explode('"',$msgpart[1]);
				$imagename = $imagenamearr[0];
				$smileynamearr = explode('.',$imagename);
				$smileyname = $smileynamearr[0];
				if(!empty($imagename)&&!empty($emojiUTF8[$imagename])){
					$messageData = str_replace($matches[0][$i],$emojiUTF8[$imagename],$messageData);
				}else{
					$messageData = str_replace($matches[0][$i],':'.$smileyname.':',$messageData);
				}
			}
		}

		$breaks = array("<br />","<br>","<br/>");
		$messageData = str_ireplace($breaks, "\n", $messageData);
		$messageData = strip_tags ( $messageData );
		$messageData = htmlspecialchars_decode($messageData);

		$message = array();

		$message['userid'] = intval($toId);
		$message['type'] = 'chat';
		$message['content'] = $messageData;
		$message['id'] = $messageId;
		$message['author'] = $fromName;
		$message['authorid'] = $fromId;
		$message['dateline'] = time();

		$payload = array();
		$payload[] = $message;

		$serverName = $_SERVER['SERVER_NAME'];
		$self = $_SERVER['PHP_SELF'];

		$self = str_replace('/cometchat/cometchat_send.php', '', $self);
		$forumUrl = $serverName.$self;

		$pushPayload = array();
		$pushPayload['url'] = $forumUrl;
		$pushPayload['data']  = base64_encode(serialize($payload));
		$pushPayload['key'] = $this->pushApiKey;

		$curl = curl_init();
		curl_setopt($curl,CURLOPT_URL,$this->pushUrl);
		curl_setopt($curl,CURLOPT_PORT,443);
		curl_setopt($curl,CURLOPT_POST,1);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl,CURLOPT_POSTFIELDS, $pushPayload);
		$response = curl_exec($curl);
		
		if($test == 'testPush'){
			$testPayload = json_encode($pushPayload);
			$testMessage = json_encode($message);
			$Log = "Curl Url:$this->pushUrl\nResponse: ".$response."\nThe Message".$testMessage."\nPayload For push".$testPayload."\n-----------------\n";
			error_log($Log,3,"PushLogs.txt");
		}
		if(($response == '1' || $response == 1 )&& $test == 1) {
			$testLog = '// Test Push //';
			$testLog .= "\nResponse :".$response;
			$testLog .= "\nPayload :\n";
			$testLog .= json_encode($payload);

			error_log($testLog,3,"PushLogs.txt");
		}else{
			error_log ("\n\n".'Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl),3,"PushLogs.txt");
		}
		curl_close($curl);
	}
}

?>
