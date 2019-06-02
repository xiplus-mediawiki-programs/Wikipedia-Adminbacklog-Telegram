<?php
require_once __DIR__ . '/config/config.default.php';
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

$limit = date('Y-m-d H:i:s', time() - $C["logkeep"]);
echo "delete log before $limit\n";

$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}_log` WHERE `time` < :time");
$sth->bindValue(":time", $limit);
$sth->execute();
