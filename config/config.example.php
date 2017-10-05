<?php

$C['token'] = 'token';
$C['chat_id'] = 'chat_id';

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
	"drv" => ["drv"],
	"ep" => ["epfull", "epsemi", "epnone"],
	"epfull" => ["epfull"],
	"epnone" => ["epnone"],
	"epsemi" => ["epsemi"],
	"rfcu" => ["rfcu"],
	"rfpp" => ["rfpp"],
	"rm" => ["rm"],
	"uaa" => ["uaa"],
	"uc" => ["uc"],
	"unblock" => ["unblock"],
	"vip" => ["vip"],
	"sum" => ["afdb", "affp", "csd", "drv", "epfull", "epnone", "epsemi", "rfcu", "rfpp", "rm", "uaa", "uc", "unblock", "vip"]
];
$C["ChatDescription"] = "中文維基百科管理員積壓工作直播 （CSD以外15分鐘更新一次）
目前有%sum%項積壓工作
#速刪(%csd%) #hangon
#VIP(%vip%) | #RFPP(%rfpp%) | #封禁申訴(%unblock%) | #UAA(%uaa%)
#存廢積壓(%afdb%) | #存廢覆核(%drv%) #OH #新申請
#編輯請求(%ep%) #EFP(%epfull%) #ESP(%epsemi%) #ENP(%epnone%)
#AFFP(%affp%) | #移動請求(%rm%) | #更名(%uc%) | #用戶查核(%rfcu%)

討論完的留言會被刪除，重要內容避免在此討論或自行備份，以利保持積壓乾淨。";
