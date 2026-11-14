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

// Release all locks of the current run and stop, without touching existing messages.
function abortRun($msg) {
	global $run;
	echo $msg . "\n";
	writelog($msg);
	foreach ((array) $run as $type) {
		unlock($type);
	}
	exit(1);
}

// Fetch a URL; abort the run on network failure or non-2xx response
// (ignore_errors is on, so error pages would otherwise be returned as content).
function fetchUrl($url) {
	$text = @file_get_contents($url);
	$status = '';
	foreach ($http_response_header ?? [] as $header) {
		if (preg_match('#^HTTP/\S+\s+\d{3}#', $header)) {
			$status = $header; // last status line wins (after redirects)
		}
	}
	if ($text === false || !preg_match('#^HTTP/\S+\s+2\d\d#', $status)) {
		abortRun("fetch fail: " . ($status ?: 'network error') . " " . $url);
	}
	return $text;
}

// Fetch list=categorymembers; abort instead of returning an empty list on bad responses.
function fetchCategoryMembers($url) {
	$data = json_decode(fetchUrl($url), true);
	if (!isset($data['query']['categorymembers']) || !is_array($data['query']['categorymembers'])) {
		abortRun("bad api response: " . $url);
	}
	return $data['query']['categorymembers'];
}
