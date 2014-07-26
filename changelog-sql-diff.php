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

// TODO: The core should provide command line interface
list($context, $core_cfg) = require dirname(dirname(dirname(__FILE__))).'/core/init.php';

// Log errors and show them as plain-text
ini_set('log_errors', TRUE);
ini_set('display_errors', TRUE);
ini_set('html_errors', FALSE);
ini_set('error_reporting', E_ALL | E_STRICT);

header('Content-Type: text/plain; encoding=utf-8');

echo "/*\n\n== Database upgrade check ==\n\n";

$database_resource = $core_cfg['changelog_sql']['database_resource'];
$changelog_dir     = $core_cfg['changelog_sql']['changelog_dir'];
$changelog_table   = $core_cfg['changelog_sql']['changelog_table'];

$diff = new Cascade\ChangelogSql\ChangelogSqlDiff($context->$database_resource);
$db_info = $diff->getDatabaseInfo();

echo
	"  Database:             ", $db_info['database'], "\n",
        "  Changelog SQL table:  ", $changelog_table, "\n",
	"  Changelog directory:  ", $changelog_dir, "\n",
        "\n\n";

// Make sure there is no buffering.
@ ob_end_flush();


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
try {
	echo "Loading changelog.sql directory ... ";
	flush();
	$n = $diff->loadChangelogDir($changelog_dir, $git_describe !== null);
	printf("%4d files loaded.\n", $n);
}
catch (\Exception $ex) {
	printf("Failed: %s\n\n", $ex->getMessage());
	die();
}

// Load database
try {
	echo "Loading about_changelog table ... ";
	flush();
	$n = $diff->loadChangelogTable($changelog_table);
	printf("%6d records loaded.\n", $n);
}
catch (\Exception $ex) {
	printf("Failed: %s\n\n", $ex->getMessage());
	if (!preg_match("/Table '.*.about_changelog' doesn't exist/", $ex->getMessage())) {
		die();
	}
}


// Find changes
flush();
$diff->compare();


// Show result
$need_update = $diff->needsUpdate();
echo "\n\n",
	"These files in changelog have been updated since their execution. Please\n",
	"examine changes manualy:\n\n";
if (empty($git_version)) {
	echo "\t(no git, no check)\n";
} else if (empty($need_update)) {
	echo "\t(nothing updated)\n";
} else foreach ($need_update as $f) {
	echo "\t", $f, "\n";	// TODO: show how bad the change is
}

// Show result
$need_exec = $diff->needsExec();
echo "\n\n",
	"Execute these files in your database:\n\n";
if (empty($need_exec)) {
	echo "\t(nothing new)\n";
} else foreach ($need_exec as $f) {
	echo "\t", $f, "\n";
}

if (!empty($need_update) || !empty($need_exec)) {
	echo "\n\n",
		"If you had problems with files listed above and solved that manualy,\n",
		"there are SQL queries to mark them as processed (refresh page before use):\n";

	if (!empty($need_update)) {
		echo "\n\t-- Updated:\n";
		foreach ($need_update as $f) {
			echo "\t", $diff->getInsertQuery($f), "\n";
		}
	}
	if (!empty($need_exec)) {
		echo "\n\t-- New:\n";
		foreach ($need_exec as $f) {
			echo "\t", $diff->getInsertQuery($f), "\n";
		}
	}
}

// If everything looks good, show query
if (empty($need_update) && !empty($need_exec)) {
	echo "\n\n",
		"If everything looks fine, just copy following SQL code to your database. This text\n",
		"and everything above is commented out, so 'Ctrl+A, Ctrl+C' will do the job.\n*/\n\n";
	foreach ($need_exec as $f) {
		echo "\n-- === Begin: ", $f, " ", str_repeat("=", 65 - strlen($f)), "\n\n";
		echo $diff->getScriptSql($f);
		echo "\n-- === End: ", $f, " ", str_repeat("=", 67 - strlen($f)), "\n\n";
	}
} else {
	echo "\n*/\n\n";
}

