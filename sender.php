<?php
require_once(__DIR__.'/config/config.php');
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

$options = getopt("dr:", ["del:"]);
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
function writelog($msg=""){
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}_log` (`msg`) VALUES (:msg)");
	$sth->bindValue(":msg", $msg);
	$res = $sth->execute();
}
function getMessageFromDB($message_id){
	global $C, $G;
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$sth->execute();
	$row = $sth->fetch(PDO::FETCH_ASSOC);
	return $row;
}
function sendMessage($type, $title, $message){
	global $C, $G, $run;
	echo "sendMessage: ".$title." / ".$message;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/sendMessage?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	$tgs = @file_get_contents($url);
	if ($tgs === false) {
		echo "network fail\n";
		writelog("network fail send: ".$title." / ".$message);
		return;
	}
	$tg = json_decode($tgs, true);
	if (!$tg["ok"]) {
		echo "\tsend fail\n";
		echo $url."\n";
		var_dump($tg);
		writelog("send fail: ".$message." ".$tgs);
		return;
	}
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
	$run []= "description";
}
function editMessage($message_id, $message){
	global $C, $G, $run;
	echo "editMessage: ".$message_id." / ".$message;
	$url = 'https://api.telegram.org/bot'.$C['token'].'/editMessageText?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"message_id" => $message_id,
		"parse_mode" => "HTML",
		"disable_web_page_preview" => true,
		"text" => $message
	));
	$tgs = @file_get_contents($url);
	if ($tgs === false) {
		echo "network fail\n";
		writelog("network fail edit: ".$message_id." / ".$message);
		return;
	}
	$tg = json_decode($tgs, true);
	if (!$tg["ok"]) {
		echo "\tedit fail\n";
		echo $url."\n";
		var_dump($tg);
		writelog("edit fail: ".$message_id." / ".$message." / ".$tgs);
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
	$run []= "description";
}
function deleteMessage($message_id, $starttime){
	global $C, $G, $run;
	if (strtotime($starttime) < time()-86400*2) {
		editMessage($message_id, "#已完成工作 ■■■■■");
	} else {
		$url = 'https://api.telegram.org/bot'.$C['token'].'/deleteMessage?'.http_build_query(array(
			"chat_id" => $C["chat_id"],
			"message_id" => $message_id
		));
		$tgs = @file_get_contents($url);
		if ($tgs === false) {
			echo "network fail\n";
			writelog("network fail delete: ".$message_id." / ".$message);
			return;
		}
		$tg = json_decode($tgs, true);
		if (!$tg["ok"]) {
			echo "\tdelete fail\n";
			echo $url."\n";
			var_dump($tg);
			if (!in_array($tg["description"], ["Bad Request: message to delete not found"])) {
				return;
			}
			writelog("delete: ".$message_id." / ".$tgs." / ".json_encode(getMessageFromDB($message_id)), JSON_UNESCAPED_UNICODE);
		}
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}` WHERE `message_id` = :message_id");
	$sth->bindValue(":message_id", $message_id);
	$res = $sth->execute();
	if ($res === false) {
		echo "\tdb fail: ".$sth->errorInfo()[2]."\n";
		return;
	}
	echo "\n";
	$run []= "description";
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
		$message = $hashtag.' <a href="https://zh.wikipedia.org/wiki/'.rawurlencode($page["title"]).'">'.$page["title"].'</a>';
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
			} else if (strpos($text, "User:Liangent-bot/template/ntvc-mixed-move")) {
				$message .= " (#繁簡)";
			} else if (preg_match("/{{(Notchinese|Notmandarin)\|/i", $text)) {
				$message .= " (G14)";
			} else if (preg_match("/{{(7D draft|七日草稿)\|/i", $text)) {
				$message .= " (G1|七日草稿)";
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
		deleteMessage($page["message_id"], $page["starttime"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function PageMatchList($page, $regex){
	// $regex[] 1=>page 2=>title 3=>status
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
	// $regex[] 1=>page 2=>title 3=>status
	echo $type."\n";
	$list = getDBList($type);
	$checkdup = array();
	foreach (PageMatchList($page, $regex) as $section) {
		$url = 'https://zh.wikipedia.org/wiki/'.rawurlencode($page);
		if (in_array($type, ["rfcu", "drv"])) {
			$url .= '#'.str_replace(" ", "_", $section["page"]);
		}
		if ($type === "uaa") {
			$url .= '#用户报告';
		}
		if ($type === "rrd") {
			$url .= '#删除请求';
		}
		$message = $hashtag.' <a href="'.$url.'">'.$section["page"].'</a>';
		if ($type === "drv") {
			$message .= " (#".$section["status"].")";
		}
		if ($type === "rrd" && strtolower($section["status"]) === "oh") {
			$message .= " (#OH)";
		}
		if (in_array($section["title"], $checkdup)) {
			echo $section["title"]." dup\n";
			continue;
		}
		$checkdup []= $section["title"];
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
		deleteMessage($section["message_id"], $section["starttime"]);
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
	$checkdup = array();
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
					if(in_array($page2, $checkdup)) {
						continue;
					}
					$checkdup []= $page2;
					$message = '#存廢積壓 <a href="https://zh.wikipedia.org/wiki/'.rawurlencode($page2).'">'.$page2.'</a> (<a href="https://zh.wikipedia.org/wiki/'.rawurlencode($page).'#'.$page2.'">'.substr($page, 41, 5).'</a>)';
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
					$message = '#存廢積壓 '.$page2.' (<a href="https://zh.wikipedia.org/wiki/'.rawurlencode($page).'#'.$page2.'">'.substr($page, 41, 5).'</a>)';
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
		deleteMessage($page["message_id"], $page["starttime"]);
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
			if (preg_match("/^\* 处理：$/m", $temp) || preg_match("/^\* 处理：<!-- 非管理員僅可標記已執行的封禁，針對提報的意見請放在下一行 -->$/m", $temp)) {
				if (in_array($user, $checkdup)) {
					echo $user." dup\n";
					continue;
				}
				$checkdup []= $user;
				$message = '#VIP <a href="https://zh.wikipedia.org/wiki/Wikipedia:当前的破坏#{{vandal|'.str_replace(" ", "_", $user).'}}">'.$user.'</a>';
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
		deleteMessage($page["message_id"], $page["starttime"]);
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
	$text = explode("== 请求解除保护 ==", $text);
	if (count($text) !== 2) {
		echo "split rfpp fail\n";
		return;
	}
	$text[0] = explode($hash, $text[0]);
	$text[1] = explode($hash, $text[1]);
	unset($text[0][0]);
	unset($text[1][0]);
	echo count($text[0])."\n";
	echo count($text[1])."\n";
	$checkdup = array();
	foreach ($text as $key => $section) {
		foreach ($section as $temp) {
			if (preg_match("/===(.+?)===/", $temp, $m)) {
				$temp = preg_replace("/===.+?=== *\n.+\n/", "", $temp);
				$page = $m[1];
				$page = trim($page);
				$page = preg_replace("/\[\[:?(.+?)?]]/", "$1", $page);
				if (!preg_match("/({{RFPP\||{{y\||拒絕|拒绝|錯誤報告|(永久|臨時|临时)?(半|全|白紙)?(保護|保护)\d+(日|周|週|個月)|(已被|永久)(半|全|白紙)?(保護|保护)|不是(编辑战|編輯戰)|完成|Done|沒有.*使.*該頁.*被保護|没有.*使.*该页.*被保护|會關注|会关注|再提交|陳舊報告|陈旧报告)/i", $temp)) {
					if (in_array($page, $checkdup)) {
						echo $page." dup\n";
						continue;
					}
					$checkdup []= $page;
					$message = '#RFPP <a href="https://zh.wikipedia.org/wiki/Wikipedia:请求保护页面#'.str_replace(" ", "_", $page).'">'.$page.'</a>';
					if ($key === 1) {
						$message .= " #解除";
					}
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
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"], $page["starttime"]);
		echo "deleteMessage: ".$page["title"]."\n";
	}
}
function setChatDescription(){
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
		$description = str_replace("%".$key."%", $count, $description);
	}
	echo $description."\n";
	$url = 'https://api.telegram.org/bot'.$C['token'].'/setChatDescription?'.http_build_query(array(
		"chat_id" => $C["chat_id"],
		"description" => $description
	));
	$tg = @file_get_contents($url);
	$tg = json_decode($tg, true);
	var_dump($tg);
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
	if ($time/60%20 == 13) $run []= "rfcu";
	if ($time/60%20 == 14) $run []= "revoke";
	if ($time/60%20 == 14) $run []= "revoke";
	if ($time/60%20 == 15) $run []= "rrd";
	if ($time/60%60 == 1) $run []= "rfrpatrol";
	if ($time/60%60 == 2) $run []= "rfrrollback";
	if ($time/60%60 == 3) $run []= "rfripbe";
	if ($time/60%60 == 4) $run []= "rfrautoreview";
	if ($time/60%60 == 5) $run []= "rfrcomfirm";
	if ($time/60%60 == 6) $run []= "rfrmms";
	if ($time/60%60 == 7) $run []= "rfrawb";
	if ($time/60%60 == 8) $run []= "rfrflood";
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
if (in_array("uc", $run)) PageStatusHandler("uc", "#更名", "Wikipedia:更改用户名", ["/=== *([^ ]+?) *===\n\*{{status2}}/", 1, 1, 0]);
if (in_array("afdb", $run)) AFDBHandler();
if (in_array("vip", $run)) VIPHandler();
if (in_array("uaa", $run)) PageStatusHandler("uaa", "#UAA", "Wikipedia:需要管理員注意的用戶名", ["/{{user-uaa\|(?:1=)?(.+?)}}/", 1, 1, 0]);
if (in_array("rfpp", $run)) RFPPHandler();
if (in_array("rfcu", $run)) PageStatusHandler("rfcu", "#RFCU", "Wikipedia:用戶查核請求", ["/=== *([^ ]+?) *===\n{{status2}}/", 1, 1, 0]);
if (in_array("revoke", $run)) PageStatusHandler("revoke", "#除權", "Wikipedia:申请解除权限", ["/\*{{User\|(?!提报的用户名)(.+?)}}\n\*:{{status2\|新提案}}/", 1, 1, 0]);
if (in_array("rfrpatrol", $run)) PageStatusHandler("rfrpatrol", "#RFR", "Wikipedia:權限申請/申請巡查權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrrollback", $run)) PageStatusHandler("rfrrollback", "#RFR", "Wikipedia:權限申請/申請回退權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfripbe", $run)) PageStatusHandler("rfripbe", "#RFR", "Wikipedia:權限申請/申請IP封禁例外權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrautoreview", $run)) PageStatusHandler("rfrautoreview", "#RFR", "Wikipedia:權限申請/申請巡查豁免權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrcomfirm", $run)) PageStatusHandler("rfrcomfirm", "#RFR", "Wikipedia:權限申請/申請確認用戶權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrmms", $run)) PageStatusHandler("rfrmms", "#RFR", "Wikipedia:權限申請/申請大量訊息發送權", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrawb", $run)) PageStatusHandler("rfrawb", "#RFR", "Wikipedia_talk:AutoWikiBrowser/CheckPage", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rfrflood", $run)) PageStatusHandler("rfrflood", "#RFR", "Wikipedia:机器用户/申请", ["/====\[\[User:(.+?)]]====\n:{{rfp\/status\|(?:新申請|OH)}}/", 1, 1, 0]);
if (in_array("rrd", $run)) PageStatusHandler("rrd", "#RRD", "Wikipedia:修订版本删除请求", ["/{{Revdel\n\|status = (OH|<!--不要修改本参数-->)\n\|article = (.+?) *\n/i", 2, 2, 1]);
if (in_array("description", $run)) setChatDescription();
