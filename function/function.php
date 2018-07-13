<?php

function checklock($type) {
	$lockfile = __DIR__."/../tmp/".$type.".lock";
	$lock = @file_get_contents($lockfile);
	if ($lock !== false && strlen($lock) > 0) {
		return true;
	}
	return false;
}

function lock($type) {
	$lockfile = __DIR__."/../tmp/".$type.".lock";
	file_put_contents($lockfile, "Yes");
	echo "locking ".$type."\n";
}

function unlock($type) {
	$lockfile = __DIR__."/../tmp/".$type.".lock";
	file_put_contents($lockfile, "");
	echo "unlocking ".$type."\n";
}
