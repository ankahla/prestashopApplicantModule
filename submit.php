<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
Tools::displayFileAsDeprecated();

require_once('applicant.php');

$module = new applicant();

if (!Module::isInstalled($module->name)) {
	exit;
}

require_once(dirname(__FILE__).'/../header.php');

require_once(dirname(__FILE__).'/../footer.php');
