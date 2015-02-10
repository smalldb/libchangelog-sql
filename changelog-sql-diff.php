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

$database_resource = $core_cfg['changelog_sql']['database_resource'];
$changelog_dir     = $core_cfg['changelog_sql']['changelog_dir'];
$changelog_table   = $core_cfg['changelog_sql']['changelog_table'];
$pdo = $context->$database_resource;

exit(Cascade\ChangelogSql\CliMain::main($changelog_dir, $changelog_table, $pdo));

