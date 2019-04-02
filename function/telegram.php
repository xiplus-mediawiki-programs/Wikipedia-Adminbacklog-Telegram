<?php

function getago($starttime = null) {
	if (is_null($starttime)) {
		$starttime = time();
	} else {
		$starttime = strtotime($starttime);
	}
	$ago = round((time() - $starttime) / 86400);
	if ($ago == 0) {
		$ago = "新";
	} else {
		$ago .= "天";
	}
	return $ago;
}

function sendMessage($type, $title, $message, $starttime = null, $skipsend = false) {
	global $C, $G, $run;

	if (in_array($type, $C["disabletype"])) {
		return;
	}
	if (is_null($starttime)) {
		$starttime = date("Y-m-d H:i:s");
	}
	echo "sendMessage: " . $title . " / " . $message;
	$ago = getago($starttime);
	if (in_array($type, $C["hiddentype"]) || $skipsend) {
		$tg = [
			"ok" => true,
		];
		$message_id = rand(0, 9999);
	} else {
		$url = 'https://api.telegram.org/bot' . $C['token'] . '/sendMessage?' . http_build_query(array(
			"chat_id" => $C["chat_id"],
			"parse_mode" => "HTML",
			"disable_web_page_preview" => true,
			"text" => $message . " " . $ago,
		));
		$tgs = @file_get_contents($url);
		if ($tgs === false) {
			echo "network fail\n";
			writelog("network fail send: " . $title . " / " . $message);
			return;
		}
		$tg = json_decode($tgs, true);
		if (!$tg["ok"]) {
			echo "\tsend fail\n";
			echo $url . "\n";
			var_dump($tg);
			writelog("send fail: " . $message . " " . $tgs);
			return;
		}
		$message_id = $tg["result"]["message_id"];
	}
	echo "\t" . $message_id;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}` (`type`, `title`, `starttime`, `date`, `message_id`, `message`) VALUES (:type, :title, :starttime, :date, :message_id, :message)");
	$sth->bindValue(":type", $type);
	$sth->bindValue(":title", $title);
	$sth->bindValue(":starttime", $starttime);
	$sth->bindValue(":date", time());
	$sth->bindValue(":message_id", $message_id);
	$sth->bindValue(":message", $message);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: " . $sth->errorInfo()[2] . "\n";
		return;
	}
	echo "\n";
	$run[] = "description";
}

function editMessage($message_id, $message, $starttime = null) {
	global $C, $G, $run;
	echo "editMessage: " . $message_id . " / " . $message;
	$ago = getago($starttime);
	$url = 'https://api.telegram.org/bot' . $C['token'] . '/editMessageText?' . http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id,
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message . " " . $ago,
	));
	$tgs = @file_get_contents($url);
	if ($tgs === false) {
		echo "network fail\n";
		writelog("network fail edit: " . $message_id . " / " . $message);
		return;
	}
	$tg = json_decode($tgs, true);
	if (!$tg["ok"]) {
		echo "\tedit fail\n";
		echo $url . "\n";
		var_dump($tg);
		if (!in_array($tg["description"], ["Bad Request: message is not modified", "Bad Request: message to edit not found"])) {
			writelog("edit fail: " . $message_id . " / " . $message . " / " . $tgs);
			return;
		}
	}
	$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}` SET `message` = :message WHERE `message_id` = :message_id");
	$sth->bindValue(":message", $message);
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: " . $sth->errorInfo()[2] . "\n";
		return;
	}
	echo "\n";
	$run[] = "description";
}

function deleteMessage($message_id, $date) {
	global $C, $G, $run;
	if ($date < time() - 86400 * 2) {
		editMessage($message_id, "#已完成工作 ■■■■■");
	} else {
		echo "deleteMessage: " . $message_id;
		$url = 'https://api.telegram.org/bot' . $C['token'] . '/deleteMessage?' . http_build_query(array(
			"chat_id" => $C["chat_id"],
			"message_id" => $message_id,
		));
		$tgs = @file_get_contents($url);
		if ($tgs === false) {
			echo "network fail\n";
			writelog("network fail delete: " . $message_id);
			return;
		}
		$tg = json_decode($tgs, true);
		if (!$tg["ok"]) {
			echo "\tdelete fail\n";
			echo $url . "\n";
			var_dump($tg);
			if (!in_array($tg["description"], ["Bad Request: message to delete not found"])) {
				writelog("delete: " . $message_id . " / " . $tgs . " / " . json_encode(getMessageFromDB($message_id)), JSON_UNESCAPED_UNICODE);
				return;
			}
		}
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: " . $sth->errorInfo()[2] . "\n";
		return;
	}
	echo "\n";
	$run[] = "description";
}

function setChatDescription() {
	global $C, $G;
	echo "setChatDescription\n";
	$sth = $G["db"]->prepare("SELECT COUNT(*) AS `count`, `type` FROM `{$C['DBTBprefix']}` GROUP BY `type`");
	$sth->execute();
	$row = $sth->fetchAll(PDO::FETCH_ASSOC);
	$cnt = array();
	foreach ($row as $pages) {
		$cnt[$pages["type"]] = $pages["count"];
	}
	$description = $C["ChatDescription"];
	foreach ($C["typelist"] as $key => $types) {
		$count = 0;
		foreach ($types as $type) {
			if (isset($cnt[$type])) {
				$count += $cnt[$type];
			}
		}
		$description = str_replace("%" . $key . "%", $count, $description);
	}
	echo $description . "\n";
	$url = 'https://api.telegram.org/bot' . $C['token'] . '/setChatDescription?' . http_build_query(array(
		"chat_id" => $C["chat_id"],
		"description" => $description,
	));
	$tg = @file_get_contents($url);
	$tg = json_decode($tg, true);
	var_dump($tg);
}
