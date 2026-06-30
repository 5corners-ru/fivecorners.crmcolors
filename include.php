<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'fivecorners.crmcolors',
    [
        'FiveCorners\\CrmColors\\RuleTable'    => 'lib/Table/RuleTable.php',
        'FiveCorners\\CrmColors\\Manager'      => 'lib/Manager.php',
        'FiveCorners\\CrmColors\\EventHandler' => 'lib/EventHandler.php',
        'FiveCorners\\CrmColors\\AdminMenu'    => 'lib/AdminMenu.php',
        'FiveCorners\\CrmColors\\PageHeader'   => 'lib/PageHeader.php',
    ]
);
