#!/usr/bin/env php
<?php
/* Copyright (C) 2022 Open-Dsi <support@open-dsi.fr>
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
 * \file scripts/user/migrate_to_centralised_users.php
 * \ingroup scripts
 * \brief Migrate all users in distinct entity to the centralised configuration of user
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
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 0); // Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Include and load Dolibarr environment variables
require_once $path."../../htdocs/master.inc.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
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

if (version_compare($version, "14.0.0") < 0) {
	print "Dolibarr version inferior to 14.0.0 is not supported\n";
	exit(-1);
}

if (empty($conf->multicompany->enabled)) {
	print "Module multi company not enabled\n";
	exit(-1);
}

if (empty($argv[1])) {
	print "Usage:    $script_file  [run|test]\n";
	print "Example:  $script_file  test\n";
	exit(-1);
}

$is_run = $argv[1] == 'run';

print "- Start in mode: " . ($is_run ? 'run' : 'test') . "\n";

// Get all entities
print "-- Get all entities\n";
$entities = array();
$sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "entity";
$resql = $db->query($sql);
if (!$resql) {
	print "Error when get all entities: " . $db->lasterror() . "\n";
	exit(-1);
}
while ($obj = $db->fetch_object($resql)) {
	$entities[$obj->rowid] = $obj->label;
}
$nb_entities = count($entities);
if ($nb_entities <= 1) {
	print "Don't have entities to migrate. (Nb entities : $nb_entities)\n";
	exit(-1);
}

// Get user groups
print "-- Get all user groups\n";
$user_groups = array();
$sql = "SELECT rowid, entity, nom FROM " . MAIN_DB_PREFIX . "usergroup";
$resql = $db->query($sql);
if (!$resql) {
	print "Error when get all user groups: " . $db->lasterror() . "\n";
	exit(-1);
}
while ($obj = $db->fetch_object($resql)) {
	$user_groups[$obj->nom][$obj->entity] = $obj->rowid;
	if (!isset($entities[$obj->entity])) {
		print "Error user group entity not found (ID: {$obj->rowid}; Name: {$obj->nom}; Entity: {$obj->entity})\n";
		exit(-1);
	}
}

// Get users
print "-- Get all users\n";
$users = array();
$sql = "SELECT rowid, entity, login FROM " . MAIN_DB_PREFIX . "user";
$resql = $db->query($sql);
if (!$resql) {
	print "Error when get all users: " . $db->lasterror() . "\n";
	exit(-1);
}
while ($obj = $db->fetch_object($resql)) {
	$users[$obj->login][$obj->entity] = $obj->rowid;
	if (!isset($entities[$obj->entity])) {
		print "Error user entity not found (ID: {$obj->rowid}; Login: {$obj->login}; Entity: {$obj->entity})\n";
		exit(-1);
	}
}

// Process the migration of user groups







print "- Finished\n";

$db->close(); // Close $db database opened handler

exit($error);
