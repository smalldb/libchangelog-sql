#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2011-2014, Josef Kufner  <jk@frozen-doe.net>
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

/*
 * Configuration
 */

$initial_chdir = '..';
$changelog_dir = './database/changelog.sql';
$timezone      = 'Europe/Prague';

// This creates database connection. Must return instance of PDO driver.
function create_pdo_database_connection() {
	// Load nette (use constant to stop it before launching application)
	define('EMBEDED_BOOTSTRAP', true);
	include './www/index.php';

	// Get database connection from Nette's DI container
	return $container->getService('database');
}

// Returns info about currently used database (it should be short string)
function get_database_info($db) {
	$r = $db->query('SELECT DATABASE()');
	$name = $r->fetchColumn();
	$r->closeCursor();
	return sprintf("using database \"%s\"", $name);
}


/******************************************************************************/

ob_start();

// Log errors and show them as plain-text
ini_set('log_errors', TRUE);
ini_set('display_errors', TRUE);
ini_set('html_errors', FALSE);
ini_set('error_reporting', E_ALL | E_STRICT);
date_default_timezone_set($timezone);

echo "/*\n\n== Database upgrade check ==\n\n\n";

chdir($initial_chdir);

// Show progress...
header('Content-Type: text/plain; encoding=utf-8');

// Connect to database
echo "Connecting to database ... ";
$db = create_pdo_database_connection();
if ($db) {
	echo "ok, ", get_database_info($db), "\n\n";
} else {
	echo "failed!\n\n";
	die();
}

// This is first flush since Nette sucks
ob_end_flush();

// Show current version
echo "Checking for git ... ";
flush();
exec("git --version 2>/dev/null", $out, $ret);
if ($ret == 0) {
	$git_version = str_replace('git version ', '', $out[0]);
	echo "ok (", $git_version, ")\n\n";
} else {
	$git_version = false;
	echo "not available.\n\n";
}

// Show application version
$git_describe = null;
if (!empty($git_version)) {
	$out = null;
	exec('git describe 2>/dev/null', $out, $ret);
	if ($ret == 0) {
		$git_describe = @ $out[0];
		echo "Current application version: ", $out[0], "\n\n";
	}
}


// Load files

echo "Loading changelog.sql directory ... ";
flush();

$files = array();
if ($d = opendir($changelog_dir)) {
	while (false !== ($f = readdir($d))) {
		if (preg_match('/[^.].+\.sql$/', $f)) {
			$out = null;
			if ($git_describe != null) {
				exec("git log -n 1 --pretty=format:%at -- \"".escapeshellcmd($changelog_dir.'/'.$f)."\"", $out, $ret);
				if ($ret == 0) {
					$files[$f] = (int) @ $out[0];
				} else {
					$files[$f] = 0;
				}
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

try {
	$result = $db->query('
			SELECT `filename`, UNIX_TIMESTAMP(MAX(`update_time`)) AS `update_time`
			FROM `about_changelog`
			GROUP BY `filename`
			ORDER BY `filename`, MAX(`id`)
		');
	$changelog = array();
	foreach ($result as $row) {
		$changelog[$row['filename']] = $row['update_time'];
	}
	$result->closeCursor();
	printf("%6d records loaded.\n", count($changelog));
	//print_r($changelog);
}
catch (Exception $ex) {
	printf("Failed: %s\n\n", $ex->getMessage());
	if (preg_match("/Table '.*.about_changelog' doesn't exist/", $ex->getMessage())) {
		printf("Here is how table 'about_changelog' should look like:\n*/\n\n");
		readfile($changelog_dir.'/0000-00-00-about_changelog.sql');
	}
	die();
}


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
	echo "\t", $f, str_repeat(' ', 50 - strlen($f)), " # by ", ($files[$f] - $changelog[$f]), " seconds\n";
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
		"there are SQL queries to mark them processed (refresh page before use):\n";

	if (!empty($need_update)) {
		echo "\n\t-- Updated:\n";
		foreach ($need_update as $f) {
			echo "\tINSERT INTO `about_changelog` SET `filename` = ",
				$db->quote($f, PDO::PARAM_STR), ";\n";
		}
	}
	if (!empty($need_exec)) {
		echo "\n\t-- New:\n";
		foreach ($need_exec as $f => $mtime) {
			echo "\tINSERT INTO `about_changelog` SET `filename` = ", $db->quote($f, PDO::PARAM_STR), ";\n";
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

