<?php

// Atomically take the lock of $type. The lock is taken when it is free, older than
// locklimit, or $force is set. Returns true on success, otherwise the unix time the
// current holder locked it. Times are computed by MySQL to avoid timezone mismatches.
function lock($type, $force = false) {
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}task_type` (`type`, `locked_at`) VALUES (:type, NOW())
		ON DUPLICATE KEY UPDATE `locked_at` = IF(:force OR `locked_at` IS NULL OR `locked_at` <= NOW() - INTERVAL :limit SECOND, NOW(), `locked_at`)");
	$sth->bindValue(":type", $type);
	$sth->bindValue(":force", $force ? 1 : 0, PDO::PARAM_INT);
	$sth->bindValue(":limit", $C["locklimit"], PDO::PARAM_INT);
	$sth->execute();
	// 1 = inserted, 2 = updated, 0 = unchanged (still held by someone else)
	if ($sth->rowCount() > 0 || $force) {
		echo "locking " . $type . "\n";
		return true;
	}
	$sth = $G["db"]->prepare("SELECT UNIX_TIMESTAMP(`locked_at`) FROM `{$C['DBTBprefix']}task_type` WHERE `type` = :type");
	$sth->bindValue(":type", $type);
	$sth->execute();
	return (int) $sth->fetchColumn();
}

// Release the lock of $type and record the end of the run.
function unlock($type) {
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}task_type` (`type`, `last_run`) VALUES (:type, NOW())
		ON DUPLICATE KEY UPDATE `locked_at` = NULL, `last_run` = NOW()");
	$sth->bindValue(":type", $type);
	$sth->execute();
	echo "unlocking " . $type . "\n";
}

// Pick the enabled type that has gone longest without a run, if that was more than
// stalelimit ago (or it has never run). Returns null when every type is fresh.
function pickStaleType() {
	global $C, $G;
	$types = [];
	foreach ($C["typelist"] as $name => $list) {
		if ($name !== "sum") {
			$types = array_merge($types, $list);
		}
	}
	$types = array_values(array_diff(array_unique($types), $C["disabletype"]));
	$sth = $G["db"]->prepare("SELECT `type`, UNIX_TIMESTAMP(`last_run`) AS `last_run`,
		`last_run` > NOW() - INTERVAL :limit SECOND AS `fresh` FROM `{$C['DBTBprefix']}task_type`");
	$sth->bindValue(":limit", $C["stalelimit"], PDO::PARAM_INT);
	$sth->execute();
	$status = [];
	foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$status[$row["type"]] = $row;
	}
	$pick = null;
	$pickTime = PHP_INT_MAX;
	foreach ($types as $type) {
		if (!empty($status[$type]["fresh"])) {
			continue;
		}
		$lastRun = isset($status[$type]["last_run"]) ? (int) $status[$type]["last_run"] : 0;
		if ($lastRun < $pickTime) {
			$pick = $type;
			$pickTime = $lastRun;
		}
	}
	return $pick;
}

// Record that a new task of $type was detected.
function markFound($type) {
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}task_type` (`type`, `last_found`) VALUES (:type, NOW())
		ON DUPLICATE KEY UPDATE `last_found` = NOW()");
	$sth->bindValue(":type", $type);
	$sth->execute();
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
