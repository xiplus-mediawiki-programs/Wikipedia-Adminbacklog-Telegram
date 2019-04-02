<?php

function checklock($type) {
	global $C;
	$lockfile = __DIR__ . "/../tmp/" . $type . ".lock";
	$lock = @file_get_contents($lockfile);
	if ($lock === false) {
		return false;
	}
	$val = (int) $lock;
	if ($lock === 0) {
		return false;
	}
	if ($lock > time() - $C["locklimit"]) {
		return $lock;
	}
	return false;
}

function lock($type) {
	$lockfile = __DIR__ . "/../tmp/" . $type . ".lock";
	file_put_contents($lockfile, time());
	echo "locking " . $type . "\n";
}

function unlock($type) {
	$lockfile = __DIR__ . "/../tmp/" . $type . ".lock";
	file_put_contents($lockfile, "-1");
	echo "unlocking " . $type . "\n";
}
