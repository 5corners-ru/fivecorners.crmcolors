<?php

namespace FiveCorners\CrmColors;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

class AdminMenu
{
    public const MODULE_ID = 'fivecorners.crmcolors';

    private const SECTION_ID       = 'global_menu_fivecorners';
    private const SECTION_MENU_ID  = 'fivecorners';
    private const SECTION_SORT     = 510;
    private const SECTION_ICON_URL = '/local/images/fivecorners/logo.svg';
    private const MODULE_ICON_URL  = '/local/images/fivecorners.crmcolors/admin_module_icon.png';

    private const UMBRELLA_ID  = 'fco_crmcolors';
    private const RULES_URL    = '/local/admin/fivecorners_crmcolors_rules.php';

    public static function onBuildGlobalMenu(array &$globalMenu, array &$moduleMenu): void
    {
        Loc::loadMessages(__DIR__ . '/../install/admin/crmcolors_common.php');

        if (!isset($globalMenu[self::SECTION_ID])) {
            $globalMenu[self::SECTION_ID] = [
                'menu_id'  => self::SECTION_MENU_ID,
                'text'     => '5 УГЛОВ',
                'title'    => '5 УГЛОВ — корпоративные модули',
                'sort'     => self::SECTION_SORT,
                'items_id' => self::SECTION_ID,
                'icon'     => 'fco-global-menu-icon',
                'items'    => [],
            ];
        }

        $lang = defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru';

        $moduleMenu[] = [
            'parent_menu' => self::SECTION_ID,
            'sort'        => 300,
            'text'        => Loc::getMessage('FCO_CC_MENU_UMBRELLA') ?: 'Цветные акценты в CRM',
            'title'       => Loc::getMessage('FCO_CC_MENU_UMBRELLA_TITLE') ?: 'Настройка правил раскраски CRM',
            'url'         => self::RULES_URL . '?lang=' . $lang,
            'icon'        => 'fco-cc-menu-icon',
            'page_icon'   => 'fco-cc-page-icon',
            'items_id'    => self::UMBRELLA_ID,
            'items'       => [],
        ];
    }

    public static function onProlog(): void
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        global $APPLICATION;

        $docRoot     = rtrim((string)\Bitrix\Main\Application::getDocumentRoot(), '/\\');
        $sectionIcon = self::SECTION_ICON_URL . self::iconVer($docRoot . self::SECTION_ICON_URL);
        $moduleIcon  = self::MODULE_ICON_URL . self::iconVer($docRoot . self::MODULE_ICON_URL);

        $APPLICATION->AddHeadString('<style>
.adm-fivecorners .adm-main-menu-item-icon {
    background-image: url(' . $sectionIcon . ') !important;
    background-size: 22px 22px !important;
}
.fco-cc-menu-icon,
.fco-cc-page-icon {
    background-image: url(' . $moduleIcon . ') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
}
</style>');

        $APPLICATION->AddHeadString('<script>
(function() {
    var icon = document.getElementById("' . self::SECTION_ID . '");
    if (icon) {
        var el = icon.querySelector(".adm-main-menu-item-icon");
        if (el) {
            el.style.backgroundImage = "url(' . $sectionIcon . ')";
            el.style.backgroundSize  = "22px 22px";
        }
    }
})();
</script>');
    }

    private static function iconVer(string $absPath): string
    {
        $mt = @filemtime($absPath);
        return $mt ? '?v=' . $mt : '';
    }
}
