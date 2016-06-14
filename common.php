<?php
/**
 * This file is part of XNova:Legacies
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @see http://www.xnova-ng.org/
 *
 * Copyright (c) 2009-Present, XNova Support Team <http://www.xnova-ng.org>
 * All rights reserved.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *                                --> NOTICE <--
 *  This file is part of the core development branch, changing its contents will
 * make you unable to use the automatic updates manager. Please refer to the
 * documentation for further information about customizing XNova.
 *
 */

session_start();

if (in_array(strtolower(getenv('DEBUG')), array('1', 'on', 'true'))) {
    define('DEBUG', true);
}

!defined('DEBUG') || @ini_set('display_errors', false);
!defined('DEBUG') || @error_reporting(E_ALL | E_STRICT);

define('ROOT_PATH', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('PHPEXT', require 'extension.inc');

define('VERSION', '2009.4');

if (0 === filesize(ROOT_PATH . 'config.php')) {
    header('Location: install/');
    die();
}

$game_config   = array();
$user          = array();
$lang          = array();
$IsUserChecked = false;

define('DEFAULT_SKINPATH', 'skins/xnova/');
define('TEMPLATE_DIR', realpath(ROOT_PATH . '/templates/'));
define('TEMPLATE_NAME', 'OpenGame');
define('DEFAULT_LANG', 'fr');

include(ROOT_PATH . 'includes/debug.class.'.PHPEXT);
$debug = new Debug();

include(ROOT_PATH . 'includes/constants.' . PHPEXT);
include(ROOT_PATH . 'includes/functions.' . PHPEXT);
include(ROOT_PATH . 'includes/unlocalised.' . PHPEXT);
include(ROOT_PATH . 'includes/todofleetcontrol.' . PHPEXT);
include(ROOT_PATH . 'language/' . DEFAULT_LANG . '/lang_info.cfg');
include(ROOT_PATH . 'includes/vars.' . PHPEXT);
include(ROOT_PATH . 'includes/db.' . PHPEXT);
include(ROOT_PATH . 'includes/strings.' . PHPEXT);

$query = doquery('SELECT * FROM {{table}}', 'config');
while($row = mysql_fetch_assoc($query)) {
    $game_config[$row['config_name']] = $row['config_value'];
}

if (!defined('DISABLE_IDENTITY_CHECK')) {
    $Result        = CheckTheUser ( $IsUserChecked );
    $IsUserChecked = $Result['state'];
    $user          = $Result['record'];
} else if (!defined('DISABLE_IDENTITY_CHECK') && $game_config['game_disable'] && $user['authlevel'] == LEVEL_PLAYER) {
    message(stripslashes($game_config['close_reason']), $game_config['game_name']);
}

includeLang('system');
includeLang('tech');

if (empty($user) && !defined('DISABLE_IDENTITY_CHECK')) {
    header('Location: login.php');
    exit(0);
}

$now = time();
$sql =<<<SQL_EOF
SELECT
  fleet_start_galaxy AS galaxy,
  fleet_start_system AS system,
  fleet_start_planet AS planet,
  fleet_start_type AS planet_type
    FROM {{table}}
    WHERE `fleet_start_time` <= {$now}
UNION
SELECT
  fleet_end_galaxy AS galaxy,
  fleet_end_system AS system,
  fleet_end_planet AS planet,
  fleet_end_type AS planet_type
    FROM {{table}}
    WHERE `fleet_end_time` <= {$now}
SQL_EOF;

$_fleets = doquery($sql, 'fleets');
while ($row = mysql_fetch_array($_fleets)) {
    FlyingFleetHandler($row);
}

unset($_fleets);

include(ROOT_PATH . 'rak.'.PHPEXT);
if (!defined('IN_ADMIN')) {
    $dpath = (isset($user['dpath']) && !empty($user["dpath"])) ? $user['dpath'] : DEFAULT_SKINPATH;
} else {
    $dpath = '../' . DEFAULT_SKINPATH;
}


if (!empty($user)) {
    SetSelectedPlanet($user);

    $planetrow = doquery("SELECT * FROM {{table}} WHERE `id` = '".$user['current_planet']."';", 'planets', true);
    $galaxyrow = doquery("SELECT * FROM {{table}} WHERE `id_planet` = '".$planetrow['id']."';", 'galaxy', true);

    CheckPlanetUsedFields($planetrow);
    PlanetResourceUpdate($user, $planetrow, time());
} else {
    $planetrow = array();
    $galaxyrow = array();
}