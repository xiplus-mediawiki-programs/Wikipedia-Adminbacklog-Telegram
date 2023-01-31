<?php

function getDBList($type) {
	global $C, $G;
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}task` WHERE `type` = :type");
	$sth->bindValue(":type", $type);
	$sth->execute();
	$row = $sth->fetchAll(PDO::FETCH_ASSOC);
	$list = array();
	foreach ($row as $page) {
		$list[$page["title"]] = $page;
	}
	return $list;
}

function writelog($msg = "") {
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}log` (`msg`) VALUES (:msg)");
	$sth->bindValue(":msg", $msg);
	$res = $sth->execute();
}

function getMessageFromDB($message_id) {
	global $C, $G;
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}task` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
	$row = $sth->fetch(PDO::FETCH_ASSOC);
	return $row;
}
