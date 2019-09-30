<?php

class WikipediaAdminbacklogBasepage {
	public function __construct($type) {
		$this->type = $type;
		$this->list = getDBList($type);
		$this->checkdup = [];
	}

	protected function send_message($title, $message) {
		if (in_array($title, $this->checkdup)) {
			return;
		}
		$this->checkdup[] = $title;
		if (isset($this->list[$title])) {
			if ($this->list[$title]['message'] !== $message) {
				editMessage($this->list[$title]['message_id'], $message, $this->list[$title]['starttime']);
				echo "editMessage: " . $title . "\n";
			} else {
				echo "oldMessage: " . $title . "\n";
			}
			unset($this->list[$title]);
		} else {
			sendMessage($this->type, $title, $message);
			echo "sendMessage: " . $title . "\n";
		}
	}

	protected function delete_message() {
		foreach ($this->list as $section) {
			deleteMessage($section['message_id'], $section['date']);
			echo "deleteMessage:" . $section['title'] . "\n";
		}
	}

	protected function get_page_text($page) {
		$url = 'https://zh.wikipedia.org/w/index.php?' . http_build_query(array(
			'title' => $page,
			'action' => 'raw',
		));
		$text = file_get_contents($url);
		if ($text === false) {
			unlock();
			exit('network error!\n');
		}
		return $text;
	}

	protected function get_category_member($category, $cmtype) {
		$url = 'https://zh.wikipedia.org/w/api.php?' . http_build_query(array(
			'action' => 'query',
			'format' => 'json',
			'list' => 'categorymembers',
			'cmtype' => $cmtype,
			'cmtitle' => $category,
			'cmlimit' => 'max',
		));
		$list = file_get_contents($url);
		if ($list === false) {
			unlock();
			exit('network error!\n');
		}
		$list = json_decode($list, true);
		return $list['query']['categorymembers'];
	}

	protected function split_text($text, $regexs = [], $skipsection = []) {
		$hash = md5(uniqid(rand(), true));
		foreach ($regexs as $regex) {
			$text = preg_replace($regex[0], sprintf($regex[1], $hash), $text);
		}
		$text = explode($hash, $text);
		foreach ($skipsection as $section) {
			unset($text[$section]);
		}
		return $text;
	}

	protected function match_text($text, $regex) {
		if (preg_match($regex, $text, $m)) {
			return $m[1];
		}
		return null;
	}
}

class RRDHandler extends WikipediaAdminbacklogBasepage {
	private $hashtag = '#RRD';
	private $page = 'Wikipedia:修订版本删除请求';
	private $splitregex = [
		['/({{Revdel)/', '%s$1'],
	];
	private $statusregex = '/\|status\s*=\s*(|OH|新申請.*?|<!--不要修改本参数-->)\n/';
	private $titleregex = '/\|article = *(.+?) *\n/';
	private $requesterregex = '/{{Revdel[\s\S]*?}}\n.*?\[\[(?:(?:User(?:[ _]talk)?|U|UT|用户|用戶|使用者):|Special:(?:(?:Contributions|Contribs)|(?:用户|用戶|使用者)?(?:贡献|貢獻))\/)([^\/|\]]*)/';

	public function __construct() {
		parent::__construct('rrd');
	}

	public function run() {
		global $C;

		$text = $this->get_page_text($this->page);
		$text = $this->split_text($text, $this->splitregex, [0]);
		$checkdup = [];
		foreach ($text as $section) {
			$status = $this->match_text($section, $this->statusregex);
			if (is_null($status)) {
				continue;
			}

			$requester = $this->match_text($section, $this->requesterregex);

			if (in_array($requester, $C['BadRequester'])) {
				continue;
			}

			$title = $this->match_text($section, $this->titleregex);
			echo $title . "\n";
			echo $requester . "\n";

			$url = mediawikiurlencode($C["baseurl"], $this->page, $title);
			$message = $this->hashtag . ' <a href="' . $url . '">' . $title . '</a>';
			if (strtolower($status) === "oh") {
				$message .= " (#OH)";
			}

			$this->send_message($title, $message);
		}
		$this->delete_message();
	}
}

class RFPPHandler extends WikipediaAdminbacklogBasepage {
	private $hashtag = '#RFPP';
	private $page = 'Wikipedia:请求保护页面';
	private $splitregex = [
		['/^(===)/m', '%s$1'],
	];
	private $unprotecttext = '== 请求解除保护 ==';
	private $statusregex = '/({{RFPP\||{{(撤回|Withdrawn)}}|{{y\||拒絕|拒绝|錯誤報告|(永久|臨時|临时)?(半|全|白紙)?(保護|保护)了?(\d+|[一二兩三四五六七八九十]+)(日|周|週|個月|个月)|(已被|已經|永久)(半|全|白紙|解除)?(保護|保护)|不是(编辑战|編輯戰)|完成|Done|沒有.*使.*該頁.*被保護|没有.*使.*该页.*被保护|會關注|会关注|再提交|陳舊報告|陈旧报告|毋須保護|(保护|保護).*解除|解除保護)/i';
	private $titleregex = '/===\s*(?:\[\[)?:?(.+?)(?:]])?\s*===/';
	private $requesterregex = '/===.+===\s*\n.+\[\[(?:(?:User(?:[ _]talk)?|U|UT|用户|用戶|使用者):|Special:(?:(?:Contributions|Contribs)|(?:用户|用戶|使用者)?(?:贡献|貢獻))\/)([^\/|\]]*)/i';

	public function __construct() {
		parent::__construct('rfpp');
	}

	public function run() {
		global $C;

		$text = $this->get_page_text($this->page);
		$text = explode($this->unprotecttext, $text);
		if (count($text) !== 2) {
			echo "split rfpp fail\n";
			return;
		}
		$alltext = [
			'protect' => $this->split_text($text[0], $this->splitregex, [0]),
			'unprotect' => $this->split_text($text[1], $this->splitregex, [0]),
		];
		$checkdup = [];
		foreach ($alltext as $reqtype => $text) {
			foreach ($text as $section) {
				$status = $this->match_text(preg_replace('/===.+?=== *\n.+\n/', '', $section), $this->statusregex);
				if (!is_null($status)) {
					continue;
				}

				$requester = $this->match_text($section, $this->requesterregex);
				$title = $this->match_text($section, $this->titleregex);
				echo "$title $requester\n";

				if (in_array($requester, $C['BadRequester'])) {
					continue;
				}

				$url = mediawikiurlencode($C["baseurl"], $this->page, $title);
				$message = $this->hashtag . ' <a href="' . $url . '">' . $title . '</a>';
				if ($reqtype === 'unprotect') {
					$message .= " #解除";
				}
				$this->send_message($title, $message);
			}
		}
		$this->delete_message();
	}
}

class VIPBaseHandler extends WikipediaAdminbacklogBasepage {
	private $splitregex = [
		['/^(=== {{(?:vandal|IPvandal)\|.+}} ===)$/m', '%s$1'],
	];
	private $statusregex = '/^\* 处理：(<!-- 非管理員僅可標記已執行的封禁，針對提報的意見請放在下一行 -->|)$/m';
	private $titleregex = '/{{(?:vandal|IPvandal)\|(?:1=)?([^|]+?)(?:\|.+)?}}/';
	private $requesterregex = '/发现人：.*?\[\[(?:(?:User(?:[ _]talk)?|U|UT|用户|用戶|使用者):|Special:(?:(?:Contributions|Contribs)|(?:用户|用戶|使用者)?(?:贡献|貢獻))\/)([^\/|\]]*)/';

	public function __construct($type, $hashtag, $page) {
		parent::__construct($type);
		$this->hashtag = $hashtag;
		$this->page = $page;
	}

	public function run() {
		global $C;

		$text = $this->get_page_text($this->page);
		$text = $this->split_text($text, $this->splitregex, [0]);
		$checkdup = [];
		foreach ($text as $section) {
			$status = $this->match_text($section, $this->statusregex);
			if (is_null($status)) {
				continue;
			}

			$requester = $this->match_text($section, $this->requesterregex);

			if (in_array($requester, $C['BadRequester'])) {
				continue;
			}

			$user = $this->match_text($section, $this->titleregex);

			$url = mediawikiurlencode($C["baseurl"], $this->page, $user);
			$message = $this->hashtag . ' <a href="' . $url . '">' . $user . '</a>';

			$this->send_message($user, $message);
		}
		$this->delete_message();
	}
}

class VIPHandler extends VIPBaseHandler {
	public function __construct() {
		parent::__construct('vip', '#VIP', 'Wikipedia:当前的破坏');
	}
}

class EWIPHandler extends VIPBaseHandler {
	public function __construct() {
		parent::__construct('ewip', '#EWIP', 'Wikipedia:管理员通告板/3RR');
	}
}

function getCategoryMember($category, $cmtype) {
	global $C, $G;
	$url = 'https://zh.wikipedia.org/w/api.php?' . http_build_query(array(
		"action" => "query",
		"format" => "json",
		"list" => "categorymembers",
		"cmtype" => $cmtype,
		"cmtitle" => $category,
		"cmlimit" => "max",
	));
	$list = file_get_contents($url);
	if ($list === false) {
		unlock();
		exit("network error!\n");
	}
	$list = json_decode($list, true);
	return $list["query"]["categorymembers"];
}

function CategoryMemberHandler($type, $hashtag, $category, $cmtype = "page|subcat|file") {
	global $C;
	echo $type . "\n";
	$list = getDBList($type);
	if ($type === "csd") {
		$csdbotlimitcnt = [];
	}
	foreach (getCategoryMember($category, $cmtype) as $page) {
		$section = '';
		if (in_array($type, ['epfull', 'epsemi', 'epnonoe', 'unblock'])) {
			$section = 'footer';
		}
		$url = mediawikiurlencode($C["baseurl"], $page["title"], $section);
		$message = $hashtag . ' <a href="' . $url . '">' . $page["title"] . '</a>';
		$skipsend = false;
		if ($type === "csd") {
			if (preg_match('/^User talk:(.+?)\/存档$/', $page['title'])) {
				echo "Skip {$page['title']}\n";
				continue;
			}
			$url = 'https://zh.wikipedia.org/w/index.php?' . http_build_query(array(
				"title" => $page["title"],
				"action" => "raw",
			));
			$text = file_get_contents($url);
			if ($text === false) {
				unlock();
				exit("network error!\n");
			}
			if (preg_match("/{{d\|bot=(.*?)\|([^|}]+)/", $text, $m)) {
				$message .= " (" . htmlentities(substr($m[2], 0, 100)) . ")";
				$bot = $m[1];
				@$csdbotlimitcnt[$bot]++;
				if ($csdbotlimitcnt[$bot] > $C['csdbotlimit']) {
					$skipsend = true;
				}
			} else if (preg_match("/{{(?:d|delete|csd|速删|速刪)\|(.+?)}}/i", $text, $m)) {
				$message .= " (" . htmlentities(substr($m[1], 0, 100)) . ")";
			} else if (preg_match("/{{(db-.+?)}}/i", $text, $m)) {
				$message .= " (" . htmlentities(substr($m[1], 0, 100)) . ")";
			} else if (strpos($text, "User:Liangent-bot/template/ntvc-mixed-move")) {
				$message .= " (#繁簡)";
			} else if (preg_match("/{{(Notchinese|Notmandarin)\|/i", $text)) {
				$message .= " (G14)";
			} else if (preg_match("/{{(7D draft|七日草稿)\|/i", $text)) {
				$message .= " (G1|七日草稿)";
			} else if (preg_match("/{{(Now[ _]?Commons|Ncd|F7)\|/i", $text)) {
				$message .= " (F7)";
			}
			if (preg_match("/{{hang ?on(?:\|([^}]+))?}}/i", $text, $m)) {
				$message .= " (#hangon";
				if (isset($m[1])) {
					$message .= ": " . htmlentities(substr($m[1], 0, 100));
				}
				$message .= ")";
			}
		}
		if ($type === 'epfull' && $C['EPFULL']['ignore_pattern'] && preg_match($C['EPFULL']['ignore_pattern'], $page['title'])) {
			echo "blacklist: " . $page['title'] . "\n";
			continue;
		}

		if (isset($list[$page["title"]])) {
			if ($list[$page["title"]]["message"] !== $message) {
				editMessage($list[$page["title"]]["message_id"], $message, $list[$page["title"]]["starttime"]);
				echo "editMessage: " . $page["title"] . "\n";
			} else {
				echo "oldMessage: " . $page["title"] . "\n";
			}
			unset($list[$page["title"]]);
		} else {
			if ($skipsend) {
				sendMessage($type, $page["title"], $message, null, true);
				echo "sendMessage: " . $page["title"] . " skiped.\n";
			} else {
				sendMessage($type, $page["title"], $message);
				echo "sendMessage: " . $page["title"] . "\n";
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"], $page["date"]);
		echo "deleteMessage: " . $page["title"] . "\n";
	}
}

function PageMatchList($page, $regex) {
	// $regex[] 1=>page 2=>title 3=>status
	$url = 'https://zh.wikipedia.org/w/index.php?' . http_build_query(array(
		"title" => $page,
		"action" => "raw",
	));
	$text = file_get_contents($url);
	if ($text === false) {
		unlock();
		exit("network error!\n");
	}
	preg_match_all($regex[0], $text, $m);
	$list = array();
	foreach ($m[0] as $key => $page) {
		if ($m[$regex[1]][$key] === "") {
			$list[] = ["page" => "無標題", "title" => $m[$regex[2]][$key], "status" => $m[$regex[3]][$key]];
		} else {
			$list[] = ["page" => $m[$regex[1]][$key], "title" => $m[$regex[2]][$key], "status" => $m[$regex[3]][$key]];
		}
	}
	return $list;
}

function PageStatusHandler($type, $hashtag, $page, $regex) {
	global $C;
	// $regex[] 1=>page 2=>title 3=>status
	echo $type . "\n";
	$list = getDBList($type);
	$checkdup = array();
	foreach (PageMatchList($page, $regex) as $section) {
		if ($type === "cv" && $section["status"] > time()) {
			continue;
		}
		if (in_array($type, ["rfcuham", "cv", "uc"])) {
			$fragment = $section["page"];
		} else if (in_array($type, ["rfrpatrol", "rfrrollback", "rfripbe", "rfrautoreview", "rfrconfirm", "rfrmms", "rfrawb", "rfrflood"])) {
			$fragment = 'User:' . $section["page"];
		} else if ($type === "uaa") {
			$fragment = '用户报告';
		} else if ($type === "affp") {
			$fragment = ($section['page'] === '無標題' ? '' : $section['page']) . '（过滤器日志）';
		} else if ($type === "drv") {
			if (preg_match("/^\[\[:?([^\]]+?)]]$/", $section["page"], $m)) {
				$fragment = $m[1];
				$section["title"] = $fragment;
				$section["page"] = $fragment;
			} else if (preg_match("/^{{al\|(.+?)}}$/", $section["page"], $m)) {
				$fragment = str_replace("|", "、", $m[1]);
				$section["title"] = $fragment;
				$section["page"] = $fragment;
			} else if (preg_match("/^[^\[\]{}]+$/", $section["page"], $m)) {
				$fragment = $section["page"];
			} else {
				echo "Cat't parse " . $section["page"] . "\n";
				continue;
			}
		} else {
			$fragment = "";
		}
		$url = mediawikiurlencode($C["baseurl"], $page, $fragment);
		$message = $hashtag . ' <a href="' . $url . '">' . $section["page"] . '</a>';
		if ($type === "drv") {
			$message .= " (#" . $section["status"] . ")";
		}
		if (in_array($section["title"], $checkdup)) {
			echo $section["title"] . " dup\n";
			continue;
		}
		$checkdup[] = $section["title"];
		if (isset($list[$section["title"]])) {
			if ($list[$section["title"]]["message"] !== $message) {
				editMessage($list[$section["title"]]["message_id"], $message, $list[$section["title"]]["starttime"]);
				echo "editMessage: " . $section["title"] . "\n";
			} else {
				echo "oldMessage: " . $section["title"] . "\n";
			}
			unset($list[$section["title"]]);
		} else {
			sendMessage($type, $section["title"], $message);
			echo "sendMessage: " . $section["title"] . "\n";
		}
	}
	foreach ($list as $section) {
		deleteMessage($section["message_id"], $section["date"]);
		echo "deleteMessage: " . $section["title"] . "\n";
	}
}

function AFDBHandler() {
	global $C;
	echo "afdb\n";
	$list = getDBList("afdb");
	$url = 'https://zh.wikipedia.org/w/index.php?' . http_build_query(array(
		"title" => "Wikipedia:頁面存廢討論/積壓討論",
		"action" => "raw",
	));
	$text = file_get_contents($url);
	if ($text === false) {
		unlock();
		exit("network error!\n");
	}
	preg_match_all("/{{#lst:(.+?)\|backlog}}/", $text, $m);
	$checkdup = array();
	foreach ($m[1] as $page) {
		echo $page . "\n";
		$url = 'https://zh.wikipedia.org/w/index.php?' . http_build_query(array(
			"title" => $page,
			"action" => "raw",
		));
		$text = file_get_contents($url);
		if ($text === false) {
			unlock();
			exit("network error!\n");
		}
		$text = explode("<section begin=backlog />", $text);
		unset($text[0]);
		foreach ($text as $temp) {
			$end = strpos($temp, "<section end=backlog />");
			$temp = substr($temp, 0, $end);
			if (preg_match_all("/^===?.*\[\[:?(.+?)]]===?$/m", $temp, $m2)) {
				foreach ($m2[1] as $page2) {
					if (in_array($page2, $checkdup)) {
						continue;
					}
					$checkdup[] = $page2;
					$url1 = mediawikiurlencode($C["baseurl"], $page2);
					$url2 = mediawikiurlencode($C["baseurl"], $page, $page2);
					$message = '#存廢積壓 <a href="' . $url1 . '">' . $page2 . '</a> (<a href="' . $url2 . '">' . substr($page, 41, 5) . '</a>)';
					echo $message . "\n";
					if (isset($list[$page2])) {
						if ($list[$page2]["message"] !== $message) {
							editMessage($list[$page2]["message_id"], $message, $list[$page2]["starttime"]);
							echo "editMessage: " . $page2 . "\n";
						} else {
							echo "oldMessage: " . $page2 . "\n";
						}
						unset($list[$page2]);
					} else {
						sendMessage("afdb", $page2, $message);
						echo "sendMessage: " . $page2 . "\n";
					}
				}
			} else if (preg_match_all("/^===? *{{al\|(.+?)}} *===?$/m", $temp, $m2)) {
				foreach ($m2[1] as $page2) {
					$page2 = str_replace("|", "、", $page2);
					if (in_array($page2, $checkdup)) {
						continue;
					}
					$checkdup[] = $page2;
					$url = mediawikiurlencode($C["baseurl"], $page, $page2);
					$message = '#存廢積壓 ' . $page2 . ' (<a href="' . $url . '">' . substr($page, 41, 5) . '</a>)';
					if (isset($list[$page2])) {
						if ($list[$page2]["message"] !== $message) {
							editMessage($list[$page2]["message_id"], $message, $list[$page2]["starttime"]);
							echo "editMessage: " . $page2 . "\n";
						} else {
							echo "oldMessage: " . $page2 . "\n";
						}
						unset($list[$page2]);
					} else {
						sendMessage("afdb", $page2, $message);
						echo "sendMessage: " . $page2 . "\n";
					}
				}
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"], $page["date"]);
		echo "deleteMessage: " . $page["title"] . "\n";
	}
}

function RFCUHandler() {
	global $C;
	$type = "rfcu";
	echo $type . "\n";
	$list = getDBList($type);
	$url = 'https://meta.wikimedia.org/w/index.php?' . http_build_query(array(
		"title" => "Steward requests/Checkuser",
		"action" => "raw",
	));
	$text = file_get_contents($url);
	if ($text === false) {
		unlock();
		exit("network error!\n");
	}
	$hash = md5(time());
	$text = preg_replace("/^(===.+?@.+?===)$/m", $hash . "$1", $text);
	$text = explode($hash, $text);
	unset($text[0]);
	$checkdup = array();
	foreach ($text as $temp) {
		if (preg_match("/===\s*([^@]+)@(zh\.wikipedia|zhwiki)\s*===/i", $temp, $m)) {
			$user = $m[1];
			$project = $m[2];
			if (preg_match("/^\s*\|\s*status\s*=\s*(<!--don't change this line-->)?\s*$/m", $temp)) {
				if (in_array($user, $checkdup)) {
					echo $user . " dup\n";
					continue;
				}
				$checkdup[] = $user;
				$url = mediawikiurlencode('https://meta.wikimedia.org/wiki/', 'Steward_requests/Checkuser', $user . '@' . $project);
				$message = '#RFCU <a href="' . $url . '">' . $user . '</a>';
				if (isset($list[$user])) {
					if ($list[$user]["message"] !== $message) {
						editMessage($list[$user]["message_id"], $message, $list[$user]["starttime"]);
						echo "editMessage: " . $user . "\n";
					} else {
						echo "oldMessage: " . $user . "\n";
					}
					unset($list[$user]);
				} else {
					sendMessage($type, $user, $message);
					echo "sendMessage: " . $user . "\n";
				}
			}
		}
	}
	foreach ($list as $page) {
		deleteMessage($page["message_id"], $page["date"]);
		echo "deleteMessage: " . $page["title"] . "\n";
	}
}
