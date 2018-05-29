<?php
require_once(__DIR__.'/config/config.default.php');
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

$del = [];
foreach ($C["autodellimit"] as $limit) {
	echo "delete ".$limit[0]." ".$limit[1]." < ".date("Y-m-d H:i:s", time()-$limit[2])." (".(time()-$limit[2]).")\n";
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}_autodel` WHERE `type` = :type AND `text` REGEXP :text AND `date` < :date ORDER BY `date`");
	$sth->bindValue(":type", $limit[0]);
	$sth->bindValue(":text", trim($limit[1], "/"));
	$sth->bindValue(":date", time()-$limit[2]);
	$sth->execute();
	$del = array_merge($del, $sth->fetchAll(PDO::FETCH_ASSOC));
}
foreach ($del as $message) {
	echo "delete ".$message["message_id"]." ".$message["first_name"]." ".$message["type"]." ".$message["text"]." ".date("Y-m-d H:i", $message["date"]);
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

$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}_autodel` (`message_id`, `first_name`, `type`, `text`, `update_id`, `date`) VALUES (:message_id, :first_name, :type, :text, :update_id, :date)");
foreach ($tg["result"] as $update) {
	if (isset($update["update_id"]) && isset($update["message"]["message_id"])) {
		$type = "unknown";
		$text = "";
		if (isset($update["message"]["text"])) {
			$type = "text";
			$text = $update["message"]["text"];
		} else if (isset($update["message"]["sticker"])) {
			$type = "sticker";
			$text = $update["message"]["sticker"]["file_id"];
		} else if (isset($update["message"]["document"])) {
			$type = "document";
			$text = $update["message"]["document"]["file_id"];
		} else if (isset($update["message"]["photo"])) {
			$type = "photo";
		}
		echo $update["message"]["message_id"]." ".$update["message"]["from"]["first_name"]." ".$type." ".$text." ".$update["update_id"]." ".$update["message"]["date"]."\n";
		$sth->bindValue(":message_id", $update["message"]["message_id"]);
		$sth->bindValue(":first_name", $update["message"]["from"]["first_name"]);
		$sth->bindValue(":type", $type);
		$sth->bindValue(":text", $text);
		$sth->bindValue(":update_id", $update["update_id"]);
		$sth->bindValue(":date", $update["message"]["date"]);
		$res = $sth->execute();
		if ($res === false) {
			echo "db fail: ".$sth->errorInfo()[2]."\n";
			return;
		}
	}
}
