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

namespace Smalldb\ChangelogSql;

/**
 * Read changelog.sql directory and compare it with database.
 */
class ChangelogSqlDiff
{
	protected $pdo;				///< PDO database driver

	protected $scripts = array();		///< SQL scripts from changelog.
	protected $changelog = array();		///< List of completed scripts (loaded from database).
	protected $init_scripts = array();	///< SQL scripts from init directory.

	protected $needs_update = null;		///< List of scripts which needs to be manualy updated.
	protected $needs_exec = null;		///< List of scripts which needs to be executed.


	/**
	 * Constructor.
	 *
	 * @param $pdo is PDO instance connected to database.
	 */
	public function __construct($pdo)
	{
		$this->pdo = $pdo;
	}


	/**
	 * Returns info about currently used database (it should be short string)
	 */
	public function getDatabaseInfo()
	{
		$r = $this->pdo->query('SELECT DATABASE()');
		$name = $r->fetchColumn();
		$r->closeCursor();
		return array(
			'database' => $name,
		);
	}


	/**
	 * Load SQL scripts from changelog directory.
	 *
	 * @returns Total count of loaded scripts.
	 */
	public function loadChangelogDir($dir, $use_git)
	{
		if ($d = opendir($dir)) {
			while (false !== ($f = readdir($d))) {
				if (preg_match('/[^.].+\.sql$/', $f)) {
					$out = null;
					$filename = "$dir/$f";
					$modified = 0;
					if ($use_git != null) {
						exec("git log -n 1 --pretty=format:%at -- \"".escapeshellcmd($filename)."\"", $out, $ret);
						if ($ret == 0) {
							$modified = (int) @ $out[0];
						}
					}
					$this->scripts[$f] = array(
						'file' => $filename,
						'modified' => $modified,
						'sql' => file_get_contents($filename),
					);
				}
			}
			closedir($d);
			ksort($this->scripts);
			//debug_dump($this->scripts);
		} else {
			throw new \RuntimeException(sprintf('Can\'t open changelog directory: '.$dir));
		}

		return count($this->scripts);
	}


	/**
	 * Load records from changelog table.
	 *
	 * @returns Total count of loaded records.
	 */
	public function loadChangelogTable($table)
	{
		$result = $this->pdo->query('
			SELECT `filename`, UNIX_TIMESTAMP(MAX(`update_time`)) AS `update_time`
			FROM `'.str_replace('`', '``', $table).'`
			GROUP BY `filename`
			ORDER BY `filename`, MAX(`id`)
		');
		$result->setFetchMode(\PDO::FETCH_ASSOC);
		$changelog = array();
		foreach ($result as $row) {
			$this->changelog[$row['filename']] = $row;
		}
		$result->closeCursor();
		//debug_dump($this->changelog);

		return count($this->changelog);
	}


	/**
	 * Compare loaded data.
	 */
	public function compare()
	{
		// Check if nothing has changed since last time
		$this->needs_update = array();
		foreach ($this->scripts as $filename => $script) {
			if (array_key_exists($filename, $this->changelog) && $this->changelog[$filename]['update_time'] < $script['modified']) {
				$this->needs_update[] = (string) $filename;
			}
		}

		// Find new files to process
		$this->needs_exec = array_keys(array_diff_key($this->scripts, $this->changelog));
	}


	/**
	 * Get list of updates to perform.
	 */
	public function needsUpdate()
	{
		return $this->needs_update;
	}


	/**
	 * Get list of scripts to execute.
	 */
	public function needsExec()
	{
		return $this->needs_exec;
	}


	/**
	 * Generate SQL query suitable for inserting new record into changelog.
	 */
	public function getInsertQuery($script)
	{
		return "INSERT INTO `about_changelog` SET `filename` = ".$this->pdo->quote($script, \PDO::PARAM_STR).";";
	}


	/**
	 * Get content of the SQL script.
	 */
	public function getScriptSql($script)
	{
		return $this->scripts[$script]['sql'];
	}


	/**
	 * Load scripts with init files
	 */
	public function loadInitScriptsDir($dir)
	{
		if ($d = opendir($dir)) {
			while (false !== ($f = readdir($d))) {
				if (preg_match('/[^.].+\.sql$/', $f)) {
					$filename = "$dir/$f";
					$this->init_scripts[$f] = array(
						'file' => $filename,
						'sql' => file_get_contents($filename),
					);
				}
			}
			closedir($d);
			ksort($this->init_scripts);
			//debug_dump($this->scripts);
		} else {
			throw new \RuntimeException(sprintf('Can\'t open init scripts directory: '.$dir));
		}

		return count($this->scripts);
	}

	/**
	 * Get list of init scripts to execute.
	 */
	public function initScripts()
	{
		return array_keys($this->init_scripts);
	}


	/**
	 * Get content of the SQL init script.
	 */
	public function getInitScriptSql($script)
	{
		return $this->init_scripts[$script]['sql'];
	}
}

