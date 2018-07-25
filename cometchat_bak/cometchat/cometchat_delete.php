<?php
	include_once dirname(__FILE__).DIRECTORY_SEPARATOR."cometchat_init.php";
	$to = $_REQUEST['to'];
	$UID = mysqli_real_escape_string($GLOBALS['dbh'],$_REQUEST['from']);
	$TO = mysqli_real_escape_string($GLOBALS['dbh'],$to);

	$_SESSION['cometchat']['cometchat_user_'.$TO] =array();

	$sql1 = "UPDATE cometchat
			SET direction = 1
			WHERE cometchat.from = ".$UID." AND direction = 0 AND cometchat.to = ".$TO;

	$sql2 = "UPDATE cometchat
			SET direction = 2
			WHERE cometchat.from = ".$TO." AND direction = 0 AND cometchat.to = ".$UID;

	$sql3 = "DELETE FROM cometchat WHERE direction = 1 AND cometchat.from=".$TO." AND cometchat.to = ".$UID;
	$sql4 = "DELETE FROM cometchat WHERE direction = 2 AND cometchat.from=".$UID." AND cometchat.to = ".$TO;

	//$sql = $sql1.';'.$sql2.';'.$sql3.';'.$sql4.';';
	//$sql = ("insert into cometchat_deletions (cometchat_deletions.from,cometchat_deletions.to) values ('".$UID."', '".mysqli_real_escape_string($GLOBALS['dbh'],$to)."')");
			
	$query = mysqli_query($GLOBALS['dbh'],$sql1);
	$query = mysqli_query($GLOBALS['dbh'],$sql2);
	$query = mysqli_query($GLOBALS['dbh'],$sql3);
	$query = mysqli_query($GLOBALS['dbh'],$sql4);

	$error = mysqli_error($GLOBALS['dbh']);
	
	$response = array();
	$response['id'] = $TO;
	if (!empty($error) ) {
		$response['result'] = "0";
		header('content-type: application/json; charset=utf-8');
		$response['error'] = mysqli_error($GLOBALS['dbh']);
		echo json_encode($response);
		exit;
	}

	header('content-type: application/json; charset=utf-8');
	
	$response['result'] = "1";
	echo json_encode($response);
?>

