#!/usr/bin/env php
<?php
/**
 * Changelog.SQL usage example
 *
 * Modify and use as you wish.
 */

// Log errors and show them as plain-text
ini_set('log_errors', TRUE);
ini_set('display_errors', TRUE);
ini_set('html_errors', FALSE);
ini_set('error_reporting', E_ALL | E_STRICT);

$changelog_dir     = dirname(__FILE__).'/app/database/changelog.sql'; // FIXME
$changelog_table   = 'about_changelog';
$pdo = new PDO(/* some initialization */);

exit(Cascade\ChangelogSql\CliMain::main($changelog_dir, $changelog_table, $pdo));

