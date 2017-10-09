<?php
require_once(__DIR__.'/config/config.php');
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

stream_context_set_default(
	array('http' => array(
		'ignore_errors' => true)
	)
);

$time = time();
echo "The time now is ".date("Y-m-d H:i:s", $time)." (UTC)\n";

echo "delete before ".date("Y-m-d H:i:s", time()-$C["autodellimit"])." (".(time()-$C["autodellimit"]).")\n";
$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}_autodel` WHERE `date` < :date ORDER BY `date`");
$sth->bindValue(":date", time()-$C["autodellimit"]);
$sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
foreach ($row as $message) {
	echo "delete ".$message["message_id"]." ".$message["first_name"]." ".$message["text"]." ".date("Y-m-d H:i", $message["date"]);
	$url = 'https://api.telegram.org/bot'.$C['token'].'/deleteMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message["message_id"]
	));
	$tg = @file_get_contents($url);
	$tg = json_decode($tg, true);
	if (!$tg["ok"]) {
		echo "\tdelete fail";
		echo "\t".$url."\n";
		var_dump($tg);
	} else {
		echo "\t delete success";
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}_autodel` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message["message_id"]);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: ".$sth->errorInfo()[2]."\n";
	}
	echo "\n";
}

$sth = $G["db"]->prepare("SELECT MAX(`update_id`) AS `maxid` FROM `{$C['DBTBprefix']}_autodel`");
$sth->execute();
$maxid = (int)$sth->fetch(PDO::FETCH_ASSOC)["maxid"]+1;
if (is_null($maxid)) {
	$maxid = 0;
}
var_dump($maxid);

$url = 'https://api.telegram.org/bot'.$C['token'].'/getUpdates?'.http_build_query(array(
	"offset" => $maxid
));
$tg = file_get_contents($url);
if ($tg === false) {
	exit("fetch error");
}
$tg = json_decode($tg, true);

$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}_autodel` (`message_id`, `first_name`, `text`, `update_id`, `date`) VALUES (:message_id, :first_name, :text, :update_id, :date)");
foreach ($tg["result"] as $update) {
	if (isset($update["update_id"]) && isset($update["message"]["message_id"])) {
		echo $update["message"]["message_id"]." ".$update["message"]["from"]["first_name"]." ".($update["message"]["text"] ?? "")." ".$update["update_id"]." ".$update["message"]["date"]."\n";
		$sth->bindValue(":message_id", $update["message"]["message_id"]);
		$sth->bindValue(":first_name", $update["message"]["from"]["first_name"]);
		$sth->bindValue(":text", $update["message"]["text"] ?? "");
		$sth->bindValue(":update_id", $update["update_id"]);
		$sth->bindValue(":date", $update["message"]["date"]);
		$res = $sth->execute();
		if ($res === false) {
			echo "db fail: ".$sth->errorInfo()[2]."\n";
			return;
		}
	}
}
