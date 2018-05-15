<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/telegram.php');
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

$time = time();
echo "The time now is ".date("Y-m-d H:i:s", $time)." (UTC)\n";

foreach ($C["renewlimit"] as $renewlimit) {
	$sth = $G["db"]->prepare("SELECT *, RAND() AS `rnd` FROM `{$C['DBTBprefix']}` WHERE `messagetime` < :messagetime ORDER BY `rnd` LIMIT 1");
	$sth->bindValue(":messagetime", date("Y-m-d H:i:s", time()-$renewlimit));
	$sth->execute();
	$res = $sth->fetch(PDO::FETCH_ASSOC);
	if ($res !== false) {
		deleteMessage($res["message_id"], $res["messagetime"]);
		sendMessage($res["type"], $res["title"], $res["message"], $res["starttime"]);
		break;
	}
}
