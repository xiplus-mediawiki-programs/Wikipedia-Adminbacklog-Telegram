<?php
require_once __DIR__ . '/config/config.default.php';
require_once __DIR__ . '/function/database.php';
require_once __DIR__ . '/function/telegram.php';
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

stream_context_set_default(
	array('http' => array(
		'ignore_errors' => true),
	)
);

$time = time();
echo "The time now is " . date("Y-m-d H:i:s", $time) . " (UTC)\n";

if (!isset($argv[1])) {
	exit("please give 1 argument is id\n");
}

if (preg_match("/^(\d+)-(\d+)$/", $argv[1], $m)) {
	$startid = $m[1];
	$endid = $m[2];
} else if (is_numeric($argv[1])) {
	$startid = $argv[1];
	$endid = $argv[1];
} else {
	exit("cannot parse id\n");
}

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}` WHERE `message_id` >= :startid AND `message_id` <= :endid");
$sth->bindValue(":startid", $startid);
$sth->bindValue(":endid", $endid);
$sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
foreach ($row as $page) {
	echo "delete " . $page["message_id"] . "\n";
	deleteMessage($page["message_id"], $page["starttime"]);
}
