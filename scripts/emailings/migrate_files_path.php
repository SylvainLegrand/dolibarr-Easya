#!/usr/bin/env php
<?php
/*
 * Copyright (C) 2007-2016 Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2015 Jean Heimburger <http://tiaris.eu>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file scripts/emailings/migrate_files_path.php
 * \ingroup scripts
 * \brief Migrate files from old system prior to 23 to new path for 23+
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__.'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

@set_time_limit(0); // No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1); // Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Include and load Dolibarr environment variables
require_once $path."../../htdocs/master.inc.php";
require_once DOL_DOCUMENT_ROOT.'/comm/mailing/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

// $langs->setDefaultLang('en_US'); // To change default language of $langs
$langs->load("main"); // To load language file for default language

// Global variables
$version = DOL_VERSION;
$error = 0;
$forcecommit = 0;

print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." *****\n";
dol_syslog($script_file." launched with arg ".join(',', $argv));

if (!isset($argv[1]) || $argv[1] != 'mailing') {
	print "Usage:  $script_file mailing\n";
	exit(-1);
}

print '--- start'."\n";

// Case to migrate mailing path
if ($argv[1] == 'mailing') {
	$mailing = new Mailing($db);

	$sql = "SELECT rowid as mid from ".MAIN_DB_PREFIX."mailing"; // Get list of all mailing
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$mailing->fetch($obj->mid);
			print " migrating mailing id=".$mailing->id." ref=".$mailing->ref."\n";
			migrate_mailing_filespath($mailing);
		}

		// Delete old mailing directory and move the new in mailing
		if (dol_is_dir($conf->mailing->dir_output . '_temp')) {
			dol_delete_dir_recursive($conf->mailing->dir_output);
			dol_move_dir($conf->mailing->dir_output . '_temp', $conf->mailing->dir_output);
		}
	} else {
		print "\n sql error ".$sql;
		exit();
	}
}

$db->close(); // Close $db database opened handler

exit($error);

/**
 * Migrate file from old path to new one for mailing $mailing
 *
 * @param 	Mailing $mailing		Object mailing
 * @return 	void
 */
function migrate_mailing_filespath($mailing)
{
	global $conf;

	$dir = $conf->mailing->dir_output;
	$origin = $dir.'/'.get_exdir($mailing->id, 2, 0, 1, $mailing, 'mailing');
	$destin = $dir.'_temp/'.get_exdir($mailing->id, 0, 0, 1, $mailing, 'mailing');

	$error = 0;

	$origin_osencoded = dol_osencode($origin);
	$destin_osencoded = dol_osencode($destin);
	dol_mkdir($destin);

	if (dol_is_dir($origin)) {
		$handle = opendir($origin_osencoded);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				if ($file != '.' && $file != '..' && is_dir($origin_osencoded.'/'.$file)) {
					$thumbs = opendir($origin_osencoded.'/'.$file);
					if (is_resource($thumbs)) {
						dol_mkdir($destin.'/'.$file);
						while (($thumb = readdir($thumbs)) !== false) {
							dol_move($origin.'/'.$file.'/'.$thumb, $destin.'/'.$file.'/'.$thumb);
						}
						// dol_delete_dir($origin.'/'.$file);
					}
				} else {
					if (dol_is_file($origin.'/'.$file)) {
						dol_move($origin.'/'.$file, $destin.'/'.$file);
					}
				}
			}
		}
	}
}

/**
 * Move a directory into another name.
 *
 * @param	string	$srcdir 			Source directory
 * @param	string 	$destdir			Destination directory
 * @param	int		$overwriteifexists	Overwrite directory if it already exists (1 by default)
 * @param	int		$indexdatabase		Index new name of files into database.
 * @param	int		$renamedircontent	Also rename contents inside srcdir after the move to match new destination name.
 * @return  boolean 					True if OK, false if KO
 */
function dol_move_dir($srcdir, $destdir, $overwriteifexists = 1, $indexdatabase = 1, $renamedircontent = 1)
{
	$result = false;

	dol_syslog("files.lib.php::dol_move_dir srcdir=".$srcdir." destdir=".$destdir." overwritifexists=".$overwriteifexists." indexdatabase=".$indexdatabase." renamedircontent=".$renamedircontent);
	$srcexists = dol_is_dir($srcdir);
	$srcbasename = basename($srcdir);
	$destexists = dol_is_dir($destdir);

	if (!$srcexists) {
		dol_syslog("files.lib.php::dol_move_dir srcdir does not exists. Move fails");
		return false;
	}

	if ($overwriteifexists || !$destexists) {
		$newpathofsrcdir = dol_osencode($srcdir);
		$newpathofdestdir = dol_osencode($destdir);

		// On windows, if destination directory exists and is empty, command fails. So if overwrite is on, we first remove destination directory.
		// On linux, if destination directory exists and is empty, command succeed. So no need to delete di destination directory first.
		// Note: If dir exists and is not empty, it will and must fail on both linux and windows even, if option $overwriteifexists is on.
		if ($overwriteifexists) {
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				if (is_dir($newpathofdestdir)) {
					@rmdir($newpathofdestdir);
				}
			}
		}

		$result = @rename($newpathofsrcdir, $newpathofdestdir);

		// Now rename contents in the directory after the move to match the new destination
		if ($result && $renamedircontent) {
			if (file_exists($newpathofdestdir)) {
				$destbasename = basename($newpathofdestdir);
				$files = dol_dir_list($newpathofdestdir);
				if (!empty($files) && is_array($files)) {
					foreach ($files as $key => $file) {
						if (!file_exists($file["fullname"])) {
							continue;
						}
						$filepath = $file["path"];
						$oldname = $file["name"];

						$newname = str_replace($srcbasename, $destbasename, $oldname);
						if (!empty($newname) && $newname !== $oldname) {
							if ($file["type"] == "dir") {
								$res = dol_move_dir($filepath.'/'.$oldname, $filepath.'/'.$newname, $overwriteifexists, $indexdatabase, $renamedircontent);
							} else {
								$moreinfo = array('gen_or_uploaded' => 'unknown');
								$res = dol_move($filepath.'/'.$oldname, $filepath.'/'.$newname, '0', $overwriteifexists, 0, $indexdatabase, $moreinfo);
							}
							if (!$res) {
								return $result;
							}
						}
					}
					$result = true;
				}
			}
		}
	}
	return $result;
}
