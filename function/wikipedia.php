<?php

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
	global $C;
	echo $type."\n";
	$list = getDBList($type);
	foreach (getCategoryMember($category, $cmtype) as $page) {
		$url = mediawikiurlencode($C["baseurl"], $page["title"]);
		$message = $hashtag.' <a href="'.$url.'">'.$page["title"].'</a>';
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
	global $C;
	// $regex[] 1=>page 2=>title 3=>status
	echo $type."\n";
	$list = getDBList($type);
	$checkdup = array();
	foreach (PageMatchList($page, $regex) as $section) {
		if ($type === "cv" && $section["status"] > time()) {
			continue;
		}
		if (in_array($type, ["rfcu", "drv", "cv", "uc"])) {
			$fragment = $section["page"];
		} else if ($type === "uaa") {
			$fragment = '用户报告';
		} else if ($type === "rrd") {
			$fragment = '删除请求';
		} else {
			$fragment = "";
		}
		$url = mediawikiurlencode($C["baseurl"], $page, $fragment);
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
	global $C;
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
					$url1 = mediawikiurlencode($C["baseurl"], $page2);
					$url2 = mediawikiurlencode($C["baseurl"], $page, $page2);
					$message = '#存廢積壓 <a href="'.$url1.'">'.$page2.'</a> (<a href="'.$url2.'">'.substr($page, 41, 5).'</a>)';
					echo $message."\n";
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
					$url = mediawikiurlencode($C["baseurl"], $page, $page2);
					$message = '#存廢積壓 '.$page2.' (<a href="'.$url.'">'.substr($page, 41, 5).'</a>)';
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
	global $C;
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
				$url = mediawikiurlencode($C["baseurl"], 'Wikipedia:当前的破坏', '{{vandal|'.$user.'}}');
				$message = '#VIP <a href="'.$url.'">'.$user.'</a>';
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
	global $C;
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
				if (!preg_match("/({{RFPP\||{{y\||拒絕|拒绝|錯誤報告|(永久|臨時|临时)?(半|全|白紙)?(保護|保护)(\d+|[一二兩三四五六七八九十]+)(日|周|週|個月)|(已被|永久)(半|全|白紙)?(保護|保护)|不是(编辑战|編輯戰)|完成|Done|沒有.*使.*該頁.*被保護|没有.*使.*该页.*被保护|會關注|会关注|再提交|陳舊報告|陈旧报告)/i", $temp)) {
					if (in_array($page, $checkdup)) {
						echo $page." dup\n";
						continue;
					}
					$checkdup []= $page;
					$url = mediawikiurlencode($C["baseurl"], 'Wikipedia:请求保护页面', $page);
					$message = '#RFPP <a href="'.$url.'">'.$page.'</a>';
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