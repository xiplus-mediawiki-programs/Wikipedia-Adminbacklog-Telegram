<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/database.php');
require_once(__DIR__.'/function/telegram.php');
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

$time = time();
echo "The time now is ".date("Y-m-d H:i:s", $time)." (UTC)\n";

$limit = $time-$C["renewlimit"];
echo "getting before ".$limit." (".date("Y-m-d H:i:s", $limit).")\n";

$sth = $G["db"]->prepare("SELECT *, RAND()*900+`date` AS `rnd` FROM `{$C['DBTBprefix']}` WHERE `date` < :date ORDER BY `rnd` ASC LIMIT 1");
$sth->bindValue(":date", $limit);
$sth->execute();
$res = $sth->fetch(PDO::FETCH_ASSOC);
if ($res !== false) {
	deleteMessage($res["message_id"], $res["date"]);
	sendMessage($res["type"], $res["title"], $res["message"], $res["starttime"]);
}
