<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/function.php');
require_once(__DIR__.'/function/database.php');
require_once(__DIR__.'/function/wikipedia.php');
require_once(__DIR__.'/function/telegram.php');
require_once($C['module']['mediawikiurlencode']);
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

$options = getopt("dr:f", ["del:"]);

$lockfile = __DIR__."/executing.lock";
$lock = @file_get_contents($lockfile);
if ($lock !== false && strlen($lock) > 0 && !isset($options["f"])) {
	exit("Another process is running.\n");
}
file_put_contents($lockfile, "Yes");

$run = [];
if (isset($options["r"])) {
	if (is_array($options["r"])) {
		$run = $options["r"];
	} else {
		$run = [$options["r"]];
	}
}
if (isset($options["d"])) {
	$run []= "description";
	echo "force setChatDescription\n";
}
if (isset($options["del"])) {
	$res = getMessageFromDB($options["del"]);
	if ($res !== false) {
		deleteMessage($res["message_id"], $res["starttime"]);
		echo "deleteMessage: ".$res["type"]." ".$res["title"]."\n";
	} else {
		echo "deleteMessage: message_id not found\n";
	}
	$run = ["none"];
}

if (count($run) === 0) {
	if ($time/60%1 == 0) $run []= "csd";
	if ($time/60%20 == 1) $run []= "epfull";
	if ($time/60%20 == 2) $run []= "epsemi";
	if ($time/60%20 == 3) $run []= "epnone";
	if ($time/60%20 == 4) $run []= "rm";
	if ($time/60%20 == 5) $run []= "unblock";
	if ($time/60%20 == 6) $run []= "affp";
	if ($time/60%20 == 7) $run []= "drv";
	if ($time/60%20 == 8) $run []= "uc";
	if ($time/60%20 == 9) $run []= "afdb";
	if ($time/60%20 == 10) $run []= "vip";
	if ($time/60%20 == 11) $run []= "uaa";
	if ($time/60%20 == 12) $run []= "rfpp";
	if ($time/60%20 == 13) $run []= "rfcuham";
	if ($time/60%20 == 13) $run []= "rfcu";
	if ($time/60%20 == 14) $run []= "revoke";
	if ($time/60%20 == 14) $run []= "revoke";
	if ($time/60%20 == 15) $run []= "rrd";
	if ($time/60%60 == 1) $run []= "rfrpatrol";
	if ($time/60%60 == 2) $run []= "rfrrollback";
	if ($time/60%60 == 3) $run []= "rfripbe";
	if ($time/60%60 == 4) $run []= "rfrautoreview";
	if ($time/60%60 == 5) $run []= "rfrconfirm";
	if ($time/60%60 == 6) $run []= "rfrmms";
	if ($time/60%60 == 7) $run []= "rfrawb";
	if ($time/60%60 == 8) $run []= "rfrflood";
	if ($time/60%60 == 9) $run []= "cv";
}

echo "run: ".implode(", ", $run)."\n";
if (in_array("csd", $run)) CategoryMemberHandler("csd", "#速刪", "Category:快速删除候选");
if (in_array("epfull", $run)) CategoryMemberHandler("epfull", "#編輯請求 #EFP", "Category:維基百科編輯全保護頁面請求");
if (in_array("epsemi", $run)) CategoryMemberHandler("epsemi", "#編輯請求 #ESP", "Category:維基百科編輯半保護頁面請求");
if (in_array("epnone", $run)) CategoryMemberHandler("epnone", "#編輯請求 #ENP", "Category:維基百科編輯無保護頁面請求");
if (in_array("rm", $run)) CategoryMemberHandler("rm", "#移動請求", "Category:移動請求", "page");
if (in_array("unblock", $run)) CategoryMemberHandler("unblock", "#封禁申訴", "Category:封禁申诉", "page");
if (in_array("affp", $run)) PageStatusHandler("affp", "#AFFP", "Wikipedia:防滥用过滤器/错误报告", ["/===((?:\[\[(.*?)]])?.+)\n{{bugstatus\|status=new\|/", 2, 1, 0]);
if (in_array("drv", $run)) PageStatusHandler("drv", "#存廢覆核", "Wikipedia:存廢覆核請求", ["/==.*\[\[:?(.+?)]] ==\n(?:{.+\n)?\*{{Status2\|(新申請|OH)/", 1, 1, 2]);
if (in_array("uc", $run)) PageStatusHandler("uc", "#更名", "Wikipedia:更改用户名", ["/=== *(.+?) *===\n\*{{status2}}/", 1, 1, 0]);
if (in_array("afdb", $run)) AFDBHandler();
if (in_array("vip", $run)) VIPHandler();
if (in_array("uaa", $run)) PageStatusHandler("uaa", "#UAA", "Wikipedia:需要管理員注意的用戶名", ["/{{user-uaa\|(?:1=)?(.+?)}}/", 1, 1, 0]);
if (in_array("rfpp", $run)) RFPPHandler();
if (in_array("rfcuham", $run)) PageStatusHandler("rfcuham", "#RFCUHAM", "Wikipedia:元維基用戶查核協助請求", ["/=== *(.+?) *===\n{{status2(}}|\|OH)/i", 1, 1, 0]);
if (in_array("rfcu", $run)) RFCUHandler();
if (in_array("revoke", $run)) PageStatusHandler("revoke", "#除權", "Wikipedia:申请解除权限", ["/\*{{User\|(?!提报的用户名)(.+?)}}\n\*:{{status2\|新提案}}/", 1, 1, 0]);
if (in_array("rfrpatrol", $run)) PageStatusHandler("rfrpatrol", "#RFR", "Wikipedia:權限申請/申請巡查權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrrollback", $run)) PageStatusHandler("rfrrollback", "#RFR", "Wikipedia:權限申請/申請回退權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfripbe", $run)) PageStatusHandler("rfripbe", "#RFR", "Wikipedia:權限申請/申請IP封禁例外權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrautoreview", $run)) PageStatusHandler("rfrautoreview", "#RFR", "Wikipedia:權限申請/申請巡查豁免權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrconfirm", $run)) PageStatusHandler("rfrconfirm", "#RFR", "Wikipedia:權限申請/申請確認用戶權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrmms", $run)) PageStatusHandler("rfrmms", "#RFR", "Wikipedia:權限申請/申請大量訊息發送權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrawb", $run)) PageStatusHandler("rfrawb", "#RFR", "Wikipedia_talk:AutoWikiBrowser/CheckPage", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrflood", $run)) PageStatusHandler("rfrflood", "#RFR", "Wikipedia:机器用户/申请", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rrd", $run)) PageStatusHandler("rrd", "#RRD", "Wikipedia:修订版本删除请求", ["/{{Revdel\n\|status = (OH|新申請.*?)\n\|article = (.+?) *\n/i", 2, 2, 1]);
if (in_array("cv", $run)) PageStatusHandler("cv", "#侵權", "Wikipedia:頁面存廢討論/疑似侵權", ["/{{CopyvioEntry\|1=(.+?)\|time=(\d+)/i", 1, 1, 2]);
if (in_array("description", $run)) setChatDescription();

unlock();
