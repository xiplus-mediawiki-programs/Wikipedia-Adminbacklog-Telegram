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
	"ep" => ["epfull", "eptemp", "epext", "epsemi", "epnone"],
	"epfull" => ["epfull"],
	"eptemp" => ["eptemp"],
	"epext" => ["epext"],
	"epsemi" => ["epsemi"],
	"epnone" => ["epnone"],
	"ewip" => ["ewip"],
	"revoke" => ["revoke"],
	"rfcuham" => ["rfcuham"],
	"rfcu" => ["rfcu"],
	"rfpp" => ["rfpp"],
	"rfr" => ["rfrflood", "rfrautoreview", "rfrawb", "rfrconfirm", "rfripbe", "rfrmms", "rfrtranswiki", "rfrpatrol", "rfrrollback"],
	"rm" => ["rm"],
	"rrd" => ["rrd"],
	"uaa" => ["uaa"],
	"uc" => ["uc"],
	"unblock" => ["unblock"],
	"vip" => ["vip"],
	"sum" => ["afdb", "affp", "csd", "cv", "drv", "epfull", "eptemp", "epext", "epsemi", "epnone", "ewip", "revoke", "rfcuham", "rfpp", "rfr", "rm", "rrd", "uaa", "uc", "unblock", "vip"],
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
$C["logkeep"] = 86400 * 30;

$C["locklimit"] = 60 * 5;

$C["csdbotlimit"] = 10;

$C['module']['mediawikiurlencode'] = __DIR__ . '/function/Mediawiki-urlencode/mediawikiurlencode.php';

$C['EPFULL']['ignore_pattern'] = "";

$C['BadRequester'] = [];

stream_context_set_default(
	array('http' => array(
		'ignore_errors' => true),
	)
);

@include __DIR__ . '/config.php';

$G["db"] = new PDO('mysql:host=' . $C["DBhost"] . ';dbname=' . $C["DBname"] . ';charset=utf8mb4', $C["DBuser"], $C["DBpass"]);
