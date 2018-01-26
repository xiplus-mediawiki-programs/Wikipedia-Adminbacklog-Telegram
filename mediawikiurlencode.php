<?php

function mediawikiurlencode($baseurl, $page, $section="") {
	$url = $baseurl.str_replace([" ", "“", "”", "\xE2\x80\x8E"], ["_", "%E2%80%9C", "%E2%80%9D", ""], $page);
	$section = trim($section);
	if ($section !== "") {
		$url .= '#'.str_replace([" ", "“", "”", "\xE2\x80\x8E"], ["_", "%E2%80%9C", "%E2%80%9D", ""], $section);
	}
	return $url;
}
