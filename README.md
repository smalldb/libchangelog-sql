Changelog.SQL
=============


Changelog.SQL is simple tool to maintain changes in database. It is based on
directory of SQL scripts and simple SQL table where are recorded already
executed scripts.


Configuration
-------------

See core.json.php shipped with this plugin.


Usage
-----

When database is modified, place SQL script which performs this modification
into `app/database/changelog.sql` directory and commit it. This script will
remain there forever.

After deploy on a server run `changelog-sql-diff.php` script and you will see
SQL code required to reproduce all changes on the server.

@warning You must set your client to stop on errors. Otherwise things go bad.

@note **The Trick:** Each file has simple insert at its end. This insert
records successful execution of given script into database. Filename specified
in this insert must match filename of the script.

### Example of the Script

~~~~~
ALTER ... whatever you need;
UPDATE ... data that needs to be modified;

--
INSERT INTO `about_changelog`
SET `filename` = '0000-00-00-about_changelog.sql';
~~~~~

@warning Do not forget final semicolon (;).


Init scripts
------------

In `app/database/changelog.sql/init` directory can be placed scripts which
should be executed every time something is changed in database. Place custom
functions here.



License
-------

The most of the code is published under Apache 2.0 license. See [LICENSE](doc/license.md) file for details.



Contribution guidelines
-----------------------

There is no bug tracker yet, so send me an e-mail and we will figure it out.

If you wish to send me a patch, please create a Git pull request or send a Git formatted patch via email.

