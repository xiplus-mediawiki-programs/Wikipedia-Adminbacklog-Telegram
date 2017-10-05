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
	echo "sendMessage: ".$title." / ".$message;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/sendMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	$tg = file_get_contents($url);
	if ($tg === false) {
		echo "\tsend fail\n";
		return;
	}
	$tg = json_decode($tg, true);
	$message_id = $tg["result"]["message_id"];
	echo "\t".$message_id;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}` (`type`, `title`, `starttime`, `message_id`, `message`) VALUES (:type, :title, :starttime, :message_id, :message)");
	$sth->bindValue(":type", $type);
	$sth->bindValue(":title", $title);
	$sth->bindValue(":starttime", date("Y-m-d H:i:s"));
	$sth->bindValue(":message_id", $message_id);
	$sth->bindValue(":message", $message);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: ".$sth->errorInfo()[2]."\n";
		return;
	}
	echo "\n";
	$G["modify"] = true;
}
function editMessage($message_id, $message){
	global $C, $G;
	echo "editMessage: ".$message_id." / ".$message;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/editMessageText?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id,
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	$tg = file_get_contents($url);
	if ($tg === false) {
		echo "\tedit fail\n";
		return;
	}
	$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}` SET `message` = :message WHERE `message_id` = :message_id");
	$sth->bindValue(":message", $message);
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: ".$sth->errorInfo()[2]."\n";
		return;
	}
	echo "\n";
	$G["modify"] = true;
}
function deleteMessage($message_id){
	global $C, $G;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/deleteMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id
	));
	$tg = file_get_contents($url);
	if ($tg === false) {
		echo "\tdelete fail\n";
		return;
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: ".$sth->errorInfo()[2]."\n";
		return;
	}
	echo "\n";
	$G["modify"] = true;
}
function getCategoryMember($category, $cmtype){
	global $C, $G;
	$url = 'https://zh.wikipedia.org/w/api.php?'.http_build_query(array(
		"action" => "query",
		"format" => "json",
		"list" => "categorymembers",
		"cmtype" => $cmtype,
		"cmtitle" => $category,
		"cmlimit" => "max"
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
			if (preg_match("/{{d\|bot=Jimmy-bot\|([^|}]+)/", $text, $m)) {
				$message .= " (".htmlentities($m[1]).")";
			} else if (preg_match("/{{(?:d|delete|csd|速删)\|(.+?)}}/i", $text, $m)) {
				$message .= " (".htmlentities($m[1]).")";
			} else if (preg_match("/{{(db-.+?)}}/i", $text, $m)) {
				$message .= " (".htmlentities($m[1]).")";
			} else if (strpos($text, "User:Liangent-bot/template/ntvc-mixed-move/")) {
				$message .= " (繁簡混用需移動)";
			} else if (preg_match("/{{(Notchinese|Notmandarin)\|/i", $text)) {
				$message .= " (G14)";
			}
			if (preg_match("/{{hang ?on/i", $text, $m)) {
				$message .= " (#hangon)";
			}
		}
		if (isset($list[$page["title"]])) {
			if ($list[$page["title"]]["message"] !== $message) {
				editMessage($list[$page["title"]]["message_id"], $message);
				echo "editMessage: ".$page["title"]."\n";
			} else {
				echo "oldMessage: ".$page["title"]."\n";
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
			$message .= " (#".$section["status"].")";
		}
		if (isset($list[$section["title"]])) {
			if ($list[$section["title"]]["message"] !== $message) {
				editMessage($list[$section["title"]]["message_id"], $message);
				echo "editMessage: ".$section["title"]."\n";
			} else {
				echo "oldMessage: ".$section["title"]."\n";
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
function AFDBHandler(){
	echo "afdb\n";
	$list = getDBList("afdb");
	$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
		"title" => "Wikipedia:頁面存廢討論/積壓投票",
		"action" => "raw"
	));
	$text = file_get_contents($url);
	preg_match_all("/{{#lst:(.+?)\|backlog}}/", $text, $m);
	foreach ($m[1] as $page) {
		echo $page."\n";
		$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
			"title" => $page,
			"action" => "raw"
		));
		$text = file_get_contents($url);
		$text = explode("<section begin=backlog />", $text);
		unset($text[0]);
		foreach ($text as $temp) {
			$end = strpos($temp, "<section end=backlog />");
			$temp = substr($temp, 0, $end);
			if (preg_match_all("/^===?.*\[\[:?(.+?)]]===?$/m", $temp, $m2)) {
				foreach ($m2[1] as $page2) {
					$message = '#存廢積壓 <a href="https://zh.wikipedia.org/wiki/'.$page2.'">'.$page2.'</a> (<a href="https://zh.wikipedia.org/wiki/'.$page.'">'.substr($page, 41, 5).'</a>)';
					if (isset($list[$page2])) {
						if ($list[$page2]["message"] !== $message) {
							editMessage($list[$page2]["message_id"], $message);
							echo "editMessage: ".$page2."\n";
						} else {
							echo "oldMessage: ".$page2."\n";
						}
						unset($list[$page2]);
					} else {
						sendMessage("afdb", $page2, $message);
						echo "sendMessage: ".$page2."\n";
					}
				}
			} else if (preg_match_all("/^===? *{{al\|(.+?)}} *===?$/m", $temp, $m2)) {
				foreach ($m2[1] as $page2) {
					$message = '#存廢積壓 '.$page2.' (<a href="https://zh.wikipedia.org/wiki/'.$page.'">'.substr($page, 41, 5).'</a>)';
					if (isset($list[$page2])) {
						if ($list[$page2]["message"] !== $message) {
							editMessage($list[$page2]["message_id"], $message);
							echo "editMessage: ".$page2."\n";
						} else {
							echo "oldMessage: ".$page2."\n";
						}
						unset($list[$page2]);
					} else {
						sendMessage("afdb", $page2, $message);
						echo "sendMessage: ".$page2."\n";
					}
				}
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function VIPHandler(){
	echo "vip\n";
	$list = getDBList("vip");
	$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
		"title" => "Wikipedia:当前的破坏",
		"action" => "raw"
	));
	$text = file_get_contents($url);
	$hash = md5(time());
	$text = preg_replace("/^(=== {{(?:vandal|IPvandal)\|.+}} ===)$/m", $hash."$1", $text);
	$text = explode($hash, $text);
	unset($text[0]);
	$checkdup = array();
	foreach ($text as $temp) {
		if (preg_match("/{{(?:vandal|IPvandal)\|(.+?)}}/", $temp, $m)) {
			$user = $m[1];
			if (preg_match("/^\* 处理：$/m", $temp) || preg_match("/^\* 处理：<!-- 非管理員僅可標記已執行的封禁，針對提報的意見請放在下一行 -->/m", $temp)) {
				if (in_array($user, $checkdup)) {
					echo $user." dup\n";
					continue;
				}
				$checkdup []= $user;
				$message = '#VIP <a href="https://zh.wikipedia.org/wiki/Wikipedia:当前的破坏">'.$user.'</a>';
				if (isset($list[$user])) {
					if ($list[$user]["message"] !== $message) {
						editMessage($list[$user]["message_id"], $message);
						echo "editMessage: ".$user."\n";
					} else {
						echo "oldMessage: ".$user."\n";
					}
					unset($list[$user]);
				} else {
					sendMessage("vip", $user, $message);
					echo "sendMessage: ".$user."\n";
				}
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function RFPPHandler(){
	$type = "rfpp";
	echo $type."\n";
	$list = getDBList($type);
	$url = 'https://zh.wikipedia.org/w/index.php?'.http_build_query(array(
		"title" => "Wikipedia:请求保护页面",
		"action" => "raw"
	));
	$text = file_get_contents($url);
	$hash = md5(time());
	$text = preg_replace("/^(===)/m", $hash."$1", $text);
	$text = explode($hash, $text);
	unset($text[0]);
	echo count($text);
	$checkdup = array();
	foreach ($text as $temp) {
		if (preg_match("/===(.+?)===/", $temp, $m)) {
			$page = $m[1];
			$page = trim($page);
			$page = preg_replace("/\[\[:?(.+?)?]]/", "$1", $page);
			if (!preg_match("/({{RFPP\||錯誤報告|(?<!请求|請求)(半|全|白紙)(保護|保护)\d+(日|周|週|個月)|不是(编辑战|編輯戰))/", $temp)) {
				if (in_array($page, $checkdup)) {
					echo $page." dup\n";
					continue;
				}
				$checkdup []= $page;
				$message = '#RFPP <a href="https://zh.wikipedia.org/wiki/Wikipedia:请求保护页面">'.$page.'</a>';
				if (isset($list[$page])) {
					if ($list[$page]["message"] !== $message) {
						editMessage($list[$page]["message_id"], $message);
						echo "editMessage: ".$page."\n";
					} else {
						echo "oldMessage: ".$page."\n";
					}
					unset($list[$page]);
				} else {
					sendMessage($type, $page, $message);
					echo "sendMessage: ".$page."\n";
				}
			} else {
				echo "done: ".$page."\n";
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function setChatDescription(){
	global $C, $G;
	if (!isset($G["modify"]) || !$G["modify"]) {
		return;
	}
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
		$description = str_replace("%".$key."%", $count, $description);
	}
	$url = 'https://api.telegram.org/bot'.$C['token'].'/setChatDescription?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"description" => $description
	));
	$tg = file_get_contents($url);
}

if ($time/60%1 == 0) CategoryMemberHandler("csd", "#速刪", "Category:快速删除候选");
if ($time/60%15 == 1) CategoryMemberHandler("epfull", "#編輯請求 #EFP", "Category:維基百科編輯全保護頁面請求");
if ($time/60%15 == 2) CategoryMemberHandler("epsemi", "#編輯請求 #ESP", "Category:維基百科編輯半保護頁面請求");
if ($time/60%15 == 3) CategoryMemberHandler("epnone", "#編輯請求 #ENP", "Category:維基百科編輯無保護頁面請求");
if ($time/60%15 == 4) CategoryMemberHandler("rm", "#移動請求", "Category:移動請求", "page");
if ($time/60%15 == 5) CategoryMemberHandler("unblock", "#封禁申訴", "Category:封禁申诉", "page");
if ($time/60%15 == 6) PageStatusHandler("affp", "#AFFP", "Wikipedia:防滥用过滤器/错误报告", ["/===((?:\[\[(.*?)]])?.+)\n{{bugstatus\|status=new\|/", 2, 1, 0]);
if ($time/60%15 == 7) PageStatusHandler("drv", "#存廢覆核", "Wikipedia:存廢覆核請求", ["/== .*\[\[:?(.+?)]] ==\n(?:{.+\n)?\*{{Status2\|(新申請|OH)/", 1, 1, 2]);
if ($time/60%15 == 8) PageStatusHandler("uc", "#更名", "Wikipedia:更改用户名", ["/=== (.+?) ===\n\*{{status2}}/", 1, 1, 0]);
if ($time/60%15 == 9) AFDBHandler();
if ($time/60%15 == 10) VIPHandler();
if ($time/60%15 == 11) PageStatusHandler("uaa", "#UAA", "Wikipedia:需要管理員注意的用戶名", ["/{{user-uaa\|(.+?)}}/", 1, 1, 0]);
if ($time/60%15 == 12) RFPPHandler();
setChatDescription();
