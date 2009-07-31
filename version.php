<?php // $Id: version.php,v 1.27 2009/07/31 17:30:53 bdaloukas Exp $
/**
 * Code fragment to define the version of game
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author 
 * @version $Id: version.php,v 1.27 2009/07/31 17:30:53 bdaloukas Exp $
 * @package game
 **/

$module->version  = 2009073102;  // The current module version (Date: YYYYMMDDXX)
$module->release = 'v2'.substr( $module->version, 4);
$module->cron     = 0;           // Period for cron to check this module (secs)

?>
