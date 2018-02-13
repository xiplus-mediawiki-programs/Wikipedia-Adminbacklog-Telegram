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
$G["db"] = new PDO ('mysql:host='.$C["DBhost"].';dbname='.$C["DBname"].';charset=utf8mb4', $C["DBuser"], $C["DBpass"]);

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
	"revoke" => ["revoke"],
	"rfcu" => ["rfcu"],
	"rfpp" => ["rfpp"],
	"rfr" => ["rfrflood", "rfrautoreview", "rfrawb", "rfrconfirm", "rfripbe", "rfrmms", "rfrpatrol", "rfrrollback"],
	"rm" => ["rm"],
	"rrd" => ["rrd"],
	"uaa" => ["uaa"],
	"uc" => ["uc"],
	"unblock" => ["unblock"],
	"vip" => ["vip"],
	"sum" => ["afdb", "affp", "csd", "cv", "drv", "epfull", "epnone", "epsemi", "revoke", "rfcu", "rfpp", "rfr", "rm", "rrd", "uaa", "uc", "unblock", "vip"]
];
$C["ChatDescription"] = "中文維基百科管理員積壓工作直播
目前有%sum%項積壓工作
#速刪(%csd%) #hangon #繁簡 | #RRD(%rrd%) | #侵權(%cv%)
#VIP(%vip%) | #RFPP(%rfpp%) | #封禁申訴(%unblock%) | #UAA(%uaa%)
#存廢積壓(%afdb%) | #存廢覆核(%drv%) #OH #新申請 | #除權(%revoke%)
#編輯請求(%ep%) #EFP(%epfull%) #ESP(%epsemi%) #ENP(%epnone%) | #RFR(%rfr%)
#AFFP(%affp%) | #移動請求(%rm%) | #更名(%uc%) | #RFCU(%rfcu%)

定時刪討論，重要內容自行備份，以保持乾淨";

$C["autodellimit"] = [
	["text", "/.*/", 60*60*24],
	["sticker", "/.*/", 60*60*24],
	["document", "/.*/", 60*60*24],
	["photo", "/.*/", 60*60*24],
	["unknown", "/.*/", 60*60*24]
];

$C['module']['mediawikiurlencode'] = __DIR__.'/function/Mediawiki-urlencode/mediawikiurlencode.php';
