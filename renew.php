<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/telegram.php');
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

$time = time();
echo "The time now is ".date("Y-m-d H:i:s", $time)." (UTC)\n";

$sth = $G["db"]->prepare("SELECT *, RAND() AS `rnd` FROM `{$C['DBTBprefix']}` WHERE `starttime` < :starttime ORDER BY `rnd` LIMIT 1");
$sth->bindValue(":starttime", date("Y-m-d H:i:s", time()-$C["renewlimit"]));
$sth->execute();
$res = $sth->fetch(PDO::FETCH_ASSOC);
if ($res === false) {
	exit("nothing to renew\n");
}

deleteMessage($res["message_id"], $res["starttime"]);
sendMessage($res["type"], $res["title"], $res["message"]);