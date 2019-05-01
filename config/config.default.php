<?php

$C['token'] = 'token';
$C['chat_id'] = 'chat_id';

$C['baseurl'] = 'https://zh.wikipedia.org/wiki/';

$C["allowsapi"] = array("cli");

$C["DBhost"] = 'localhost';
$C['DBname'] = '';
$C['DBuser'] = '';
$C['DBpass'] = '';
$C['DBTBprefix'] = 'Adminbacklog';

$C["typelist"] = [
	"afdb" => ["afdb"],
	"affp" => ["affp"],
	"csd" => ["csd"],
	"cv" => ["cv"],
	"drv" => ["drv"],
	"ep" => ["epfull", "epsemi", "epnone"],
	"epfull" => ["epfull"],
	"epnone" => ["epnone"],
	"epsemi" => ["epsemi"],
	"ewip" => ["ewip"],
	"revoke" => ["revoke"],
	"rfcuham" => ["rfcuham"],
	"rfcu" => ["rfcu"],
	"rfpp" => ["rfpp"],
	"rfr" => ["rfrflood", "rfrautoreview", "rfrawb", "rfrconfirm", "rfripbe", "rfrmms", "rfrpatrol", "rfrrollback"],
	"rm" => ["rm"],
	"rrd" => ["rrd"],
	"uaa" => ["uaa"],
	"uc" => ["uc"],
	"unblock" => ["unblock"],
	"vip" => ["vip"],
	"sum" => ["afdb", "affp", "csd", "cv", "drv", "epfull", "epnone", "epsemi", "ewip", "revoke", "rfcuham", "rfpp", "rfr", "rm", "rrd", "uaa", "uc", "unblock", "vip"],
];
$C["ChatDescription"] = "中文維基百科管理員積壓工作直播，共有%sum%項
#速刪(%csd%) | #RRD(%rrd%) | #侵權(%cv%)
#VIP(%vip%) #EWIP(%ewip%) | #RFPP(%rfpp%) | #封禁申訴(%unblock%) | #UAA(%uaa%)
#存廢積壓(%afdb%) | #存廢覆核(%drv%) | #除權(%revoke%)
#編輯請求(%ep%) | #RFR(%rfr%) | #AFFP(%affp%) | #更名(%uc%)
#移動請求(%rm%) | #RFCU(%rfcu%) | #RFCUHAM(%rfcuham%)";

$C["hiddentype"] = [];
$C["disabletype"] = [];

$C["autodellimit"] = [
	["text", "/.*/", 60 * 60 * 24],
	["sticker", "/.*/", 60 * 60 * 24],
	["document", "/.*/", 60 * 60 * 24],
	["photo", "/.*/", 60 * 60 * 24],
	["unknown", "/.*/", 60 * 60 * 24],
];

$C["renewlimit"] = 3600 * 47;

$C["locklimit"] = 60 * 5;

$C["csdbotlimit"] = 10;

$C['module']['mediawikiurlencode'] = __DIR__ . '/function/Mediawiki-urlencode/mediawikiurlencode.php';

$C['RFPP']['done_pattern'] = "/({{RFPP\||{{(撤回|Withdrawn)}}|{{y\||拒絕|拒绝|錯誤報告|(永久|臨時|临时)?(半|全|白紙)?(保護|保护)了?(\d+|[一二兩三四五六七八九十]+)(日|周|週|個月|个月)|(已被|已經|永久)(半|全|白紙|解除)?(保護|保护)|不是(编辑战|編輯戰)|完成|Done|沒有.*使.*該頁.*被保護|没有.*使.*该页.*被保护|會關注|会关注|再提交|陳舊報告|陈旧报告|毋須保護|(保护|保護).*解除)/i";
$C['RFPP']['blacklist_pattern'] = "";

$C['EPFULL']['ignore_pattern'] = "";

$C['BadRequester'] = [];

stream_context_set_default(
	array('http' => array(
		'ignore_errors' => true),
	)
);

@include __DIR__ . '/config.php';

$G["db"] = new PDO('mysql:host=' . $C["DBhost"] . ';dbname=' . $C["DBname"] . ';charset=utf8mb4', $C["DBuser"], $C["DBpass"]);
