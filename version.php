<?php // $Id: version.php,v 1.41 2010/09/24 13:35:46 bdaloukas Exp $
/**
 * Code fragment to define the version of game
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author 
 * @version $Id: version.php,v 1.41 2010/09/24 13:35:46 bdaloukas Exp $
 * @package game
 **/

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2010090301;  // The current module version (Date: YYYYMMDDXX)
$plugin->requires = 2010000000;  // Requires this Moodle version
$plugin->cron     = 0;           // Period for cron to check this module (secs)
$plugin->component = 'mod_game';
$plugin->release = '1.0 (Build: 2010090301)';