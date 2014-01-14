<?php
/*
 * Copyright (c) 2011, Josef Kufner  <jk@frozen-doe.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

ob_start();
echo "/*\n\n== Database upgrade check ==\n\n";

// Go to document root
chdir(dirname(__FILE__).'/..');

// Do not use development environment
define('DEVELOPMENT_ENVIRONMENT', NULL);

// Call core init file
require('core/init.php');

// Log errors and show them as plain-text
ini_set('log_errors', TRUE);
ini_set('display_errors', TRUE);	// disabled when loading app/init.php
ini_set('html_errors', FALSE);
ini_set('error_reporting', E_ALL | E_STRICT);

// Show progress...
header('Content-Type: text/plain; encoding=utf-8');

echo "\n";
ob_end_flush();

// Show current version
echo "Checking for git ... ";
flush();
exec("git --version", $out, $ret);
if ($ret == 0) {
	$git_version = str_replace('git version ', '', $out[0]);
	echo "ok (", $git_version, ")\n";
} else {
	$git_version = false;
	echo "not available.\n";
}

// Show application version
if (!empty($git_version)) {
	$out = null;
	exec('git describe', $out, $ret);
	if ($ret == 0) {
		echo "Current application version: ", $out[0], "\n\n";
	}
}


// Load files

echo "Loading changelog.sql directory ... ";
flush();

$files = array();
$changelog_dir = 'install/changelog.sql';

if ($d = opendir($changelog_dir)) {
	while (false !== ($f = readdir($d))) {
		if ($f[0] != '.') {
			$out = null;
			exec("git log -n 1 --pretty=format:%at -- \"".escapeshellcmd($changelog_dir.'/'.$f)."\"", $out, $ret);
			if ($ret == 0) {
				$files[$f] = (int) @ $out[0];
			} else {
				$files[$f] = 0;
			}
		}
	}
	closedir($d);
	ksort($files);
	printf("%4d files loaded.\n", count($files));
} else {
	echo "failed! Can't open ", $changelog_dir, ".\n";
	die();
}
//print_r($files);


// Load database

echo "Loading about_changelog table ... ";
flush();

$changelog = dibi::select('`filename`, UNIX_TIMESTAMP(MAX(`update_time`)) AS `update_time`')
		->from('about_changelog')
		->groupBy('`filename`')
		->orderBy('`filename`, MAX(`id`)')
		->fetchPairs('filename', 'update_time');
printf("%6d records loaded.\n", count($changelog));
//print_r($changelog);


// Find updates

flush();
$need_update = array();

foreach ($files as $file => $mtime) {
	if (array_key_exists($file, $changelog) && $changelog[$file] < $mtime) {
		$need_update[] = (string) $file;
	}
}

echo "\n\n",
	"These files in changelog have been updated since their execution. Please\n",
	"examine changes manualy:\n\n";
if (empty($git_version)) {
	echo "\t(no git, no check)\n";
} else if (empty($need_update)) {
	echo "\t(nothing updated)\n";
} else foreach ($need_update as $f) {
	echo "\t", $f, str_repeat(' ', 45 - strlen($f)), " # by ", ($files[$f] - $changelog[$f]), " seconds\n";
}


// Find new files to process

flush();
$need_exec = array_diff_key($files, $changelog);

echo "\n\n",
	"Execute these files in your database:\n\n";
if (empty($need_exec)) {
	echo "\t(nothing new)\n";
} else foreach ($need_exec as $f => $mtime) {
	echo "\t", $f, "\n";
}

if (!empty($need_update) || !empty($need_exec)) {
	echo "\n\n",
		"If you had problems with files listed above and solved that manualy,\n",
		"there are SQL queries to mark them processed (refresh page before use):\n\n";

	if (!empty($need_update)) {
		echo "\t-- Updated:\n";
		foreach ($need_update as $f) {
			echo "\tINSERT INTO `about_changelog` SET `filename` = ",
				dibi::getConnection()->getDriver()->escape($f, dibi::TEXT), ";\n";
		}
	}
	if (!empty($need_exec)) {
		echo "\t-- New:\n";
		foreach ($need_exec as $f => $mtime) {
			echo "\tINSERT INTO `about_changelog` SET `filename` = ", dibi::getConnection()->getDriver()->escape($f, dibi::TEXT), ";\n";
		}
	}
}

// If everything looks good, show query
if (empty($need_update) && !empty($need_exec)) {
	echo "\n\n",
		"If everything looks fine, just copy following SQL code to your database. This text\n",
		"and everything above is commented out, so 'Ctrl+A, Ctrl+C' will do the job.\n*/\n\n";
	foreach ($need_exec as $f => $mtime) {
		echo "\n-- === Begin: ", $f, " ", str_repeat("=", 65 - strlen($f)), "\n\n";
		readfile($changelog_dir.'/'.$f);
		echo "\n-- === End: ", $f, " ", str_repeat("=", 67 - strlen($f)), "\n\n";
	}
} else {
	echo "\n*/\n\n";
}

