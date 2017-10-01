<?php
require_once(__DIR__.'/config/config.php');
date_default_timezone_set('UTC');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

$time = time();
echo "The time now is ".date("Y-m-d H:i:s", $time)." (UTC)\n";

function getDBList($type){
	global $C, $G;
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}` WHERE `type` = :type");
	$sth->bindValue(":type", $type);
	$sth->execute();
	$row = $sth->fetchAll(PDO::FETCH_ASSOC);
	$list = array();
	foreach ($row as $page) {
		$list[$page["title"]] = $page;
	}
	return $list;
}
function sendMessage($type, $title, $message){
	global $C, $G;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/sendMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	$tg = file_get_contents($url);
	$tg = json_decode($tg, true);
	$message_id = $tg["result"]["message_id"];
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}` (`type`, `title`, `starttime`, `message_id`, `message`) VALUES (:type, :title, :starttime, :message_id, :message)");
	$sth->bindValue(":type", $type);
	$sth->bindValue(":title", $title);
	$sth->bindValue(":starttime", date("Y-m-d H:i:s"));
	$sth->bindValue(":message_id", $message_id);
	$sth->bindValue(":message", $message);
	$res = $sth->execute();
	if ($res === false) {
		echo $sth->errorInfo()[2];
	}
}
function editMessage($message_id, $message){
	global $C, $G;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/editMessageText?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id,
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	file_get_contents($url);
	$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}` SET `message` = :message WHERE `message_id` = :message_id");
	$sth->bindValue(":message", $message);
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
}
function deleteMessage($message_id){
	global $C, $G;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/deleteMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id
	));
	file_get_contents($url);
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
}
function getCategoryMember($category, $cmtype){
	global $C, $G;
	$url = 'https://zh.wikipedia.org/w/api.php?'.http_build_query(array(
		"action" => "query",
		"format" => "json",
		"list" => "categorymembers",
		"cmtype" => $cmtype,
		"cmtitle" => $category
	));
	$list = file_get_contents($url);
	$list = json_decode($list, true);
	return $list["query"]["categorymembers"];
}
function CategoryMemberHandler($type, $hashtag, $category, $cmtype = "page|subcat|file"){
	echo $type."\n";
	$list = getDBList($type);
	foreach (getCategoryMember($category, $cmtype) as $page) {
		$message = $hashtag.' <a href="https://zh.wikipedia.org/wiki/'.$page["title"].'">'.$page["title"].'</a>';
		if ($type === "csd") {
			$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
				"title" => $page["title"],
				"action" => "raw"
			));
			$text = file_get_contents($url);
			if (preg_match("/{{(?:d|delete|速删)\|(.+?)}}/i", $text, $m)) {
				$message .= " (".htmlentities($m[1]).")";
			}
			if (preg_match("/{{(db-.+?)}}/i", $text, $m)) {
				$message .= " (".htmlentities($m[1]).")";
			}
			if (preg_match("/{{User:Liangent-bot\/template\/ntvc-mixed-move/i", $text, $m)) {
				$message .= " (繁簡混用需移動)";
			}
			if (preg_match("/{{hang ?on/i", $text, $m)) {
				$message .= " (hangon)";
			}
		}
		if (isset($list[$page["title"]])) {
			if ($list[$page["title"]]["message"] !== $message) {
				editMessage($list[$page["title"]]["message_id"], $message);
				echo "editMessage: ".$page["title"]."\n";
			}
			unset($list[$page["title"]]);
		} else {
			sendMessage($type, $page["title"], $message);
			echo "sendMessage: ".$page["title"]."\n";
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function PageMatchList($page, $regex){
	$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
		"title" => $page,
		"action" => "raw"
	));
	$text = file_get_contents($url);
	preg_match_all($regex[0], $text, $m);
	$list = array();
	foreach ($m[0] as $key => $page) {
		if ($m[$regex[1]][$key] === "") {
			$list []= ["page"=>"無標題", "title"=>$m[$regex[2]][$key], "status"=>$m[$regex[3]][$key]];
		} else {
			$list []= ["page"=>$m[$regex[1]][$key], "title"=>$m[$regex[2]][$key], "status"=>$m[$regex[3]][$key]];
		}
	}
	return $list;
}
function PageStatusHandler($type, $hashtag, $page, $regex){
	echo $type."\n";
	$list = getDBList($type);
	foreach (PageMatchList($page, $regex) as $section) {
		$message = $hashtag.' <a href="https://zh.wikipedia.org/wiki/'.$page.'">'.$section["page"].'</a>';
		if ($type === "drv") {
			$message .= " (".$section["status"].")";
		}
		if (isset($list[$section["title"]])) {
			if ($list[$section["title"]]["message"] !== $message) {
				editMessage($list[$section["title"]]["message_id"], $message);
				echo "editMessage: ".$section["title"]."\n";
			}
			unset($list[$section["title"]]);
		} else {
			sendMessage($type, $section["title"], $message);
			echo "sendMessage: ".$section["title"]."\n";
		}
	}
	foreach ($list as $section) {
		deleteMessage($section["message_id"]);
		echo "deleteMessage: ".$section["title"]."\n";
	}
}

if ($time/60%1 == 0) CategoryMemberHandler("csd", "#速刪", "Category:快速删除候选");
if ($time/60%60 == 0) CategoryMemberHandler("epfull", "#編輯請求_全", "Category:維基百科編輯全保護頁面請求");
if ($time/60%60 == 0) CategoryMemberHandler("epsemi", "#編輯請求_半", "Category:維基百科編輯半保護頁面請求");
if ($time/60%60 == 0) CategoryMemberHandler("epnone", "#編輯請求_無", "Category:維基百科編輯無保護頁面請求");
if ($time/60%60 == 0) CategoryMemberHandler("rm", "#移動請求", "Category:移動請求", "page");
if ($time/60%60 == 0) CategoryMemberHandler("unblock", "#封禁申訴", "Category:封禁申诉", "page");
if ($time/60%60 == 0) PageStatusHandler("affp", "#過濾器錯誤", "Wikipedia:防滥用过滤器/错误报告", ["/===((?:\[\[(.*?)]])?.+)\n{{bugstatus\|status=new\|/", 2, 1, 0]);
if ($time/60%10 == 0) PageStatusHandler("drv", "#存廢覆核", "Wikipedia:存廢覆核請求", ["/== .*\[\[:?(.+?)]] ==\n(?:{.+\n)?\*{{Status2\|(新申請|OH)/", 1, 1, 2]);
