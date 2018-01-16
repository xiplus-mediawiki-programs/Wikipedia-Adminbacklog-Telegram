<?php

function mediawikiurlencode($baseurl, $page, $section="") {
	$url = $baseurl.str_replace([" ", "“", "”"], ["_", "%E2%80%9C", "%E2%80%9D"], $page);
	if (trim($section) !== "") {
		$url .= '#'.str_replace([" ", "“", "”"], ["_", "%E2%80%9C", "%E2%80%9D"], $section);
	}
	return $url;
}
