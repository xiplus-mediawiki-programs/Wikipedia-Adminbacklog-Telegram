<?php

function unlock() {
	$lockfile = __DIR__."/../executing.lock";
	file_put_contents($lockfile, "");
}
