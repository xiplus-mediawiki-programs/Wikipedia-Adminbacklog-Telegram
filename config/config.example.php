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
