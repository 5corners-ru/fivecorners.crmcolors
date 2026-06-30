<?php

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class fivecorners_crmcolors extends CModule
{
    const MODULE_ID = 'fivecorners.crmcolors';

    public $MODULE_ID          = 'fivecorners.crmcolors';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('FCO_CC_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('FCO_CC_MODULE_DESCRIPTION');
        $this->PARTNER_NAME        = Loc::getMessage('FCO_CC_PARTNER_NAME');
        $this->PARTNER_URI         = Loc::getMessage('FCO_CC_PARTNER_URI');
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        try {
            $this->InstallDB();
            Loader::includeModule(self::MODULE_ID);
            $this->InstallFiles();
            $this->InstallEvents();

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('FCO_CC_INSTALL_TITLE'),
                __DIR__ . '/step.php'
            );
        } catch (\Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION;

        Loader::includeModule(self::MODULE_ID);

        $step = (int)($_REQUEST['step'] ?? 1);

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('FCO_CC_UNINSTALL_TITLE'),
                __DIR__ . '/unstep1.php'
            );
            return true;
        }

        $saveData   = (($_REQUEST['save_data'] ?? '') === 'Y');
        $deleteData = !$saveData;

        try {
            $this->UnInstallEvents();
            $this->UnInstallFiles();
            if ($deleteData) {
                $this->dropTable();
            }
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('FCO_CC_UNINSTALL_TITLE'),
                __DIR__ . '/unstep.php'
            );
        } catch (\Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
        } finally {
            ModuleManager::unRegisterModule(self::MODULE_ID);
        }

        return true;
    }

    public function InstallDB(): void
    {
        ModuleManager::registerModule(self::MODULE_ID);
        Loader::includeModule(self::MODULE_ID);

        $connection = Application::getConnection();
        $tableName  = \FiveCorners\CrmColors\RuleTable::getTableName();

        if (!$connection->isTableExists($tableName)) {
            \FiveCorners\CrmColors\RuleTable::getEntity()->createDbTable();

            $sqlHelper = $connection->getSqlHelper();
            try {
                $connection->queryExecute(
                    'CREATE INDEX IDX_FC_CC_ENTITY ON ' . $sqlHelper->quote($tableName)
                    . ' ('
                    . $sqlHelper->quoteIdentifier('ENTITY_TYPE')   . ', '
                    . $sqlHelper->quoteIdentifier('CATEGORY_ID')   . ', '
                    . $sqlHelper->quoteIdentifier('SMART_TYPE_ID') . ', '
                    . $sqlHelper->quoteIdentifier('ACTIVE')
                    . ')'
                );
            } catch (\Throwable $e) {
                // индекс уже есть — не критично
            }
        }
    }

    public function UnInstallDB(bool $deleteData = false): void
    {
        if ($deleteData) {
            $this->dropTable();
        }
        ModuleManager::unRegisterModule(self::MODULE_ID);
    }

    private function dropTable(): void
    {
        $connection = Application::getConnection();
        $tableName  = class_exists('\\FiveCorners\\CrmColors\\RuleTable')
            ? \FiveCorners\CrmColors\RuleTable::getTableName()
            : 'b_fc_crmcolors_rule';

        if ($connection->isTableExists($tableName)) {
            $connection->dropTable($tableName);
        }
    }

    public function InstallFiles(): void
    {
        $docRoot = rtrim((string)Application::getDocumentRoot(), '/\\');

        // Admin-страницы: install/admin/ → /local/admin/
        $adminSrc = __DIR__ . '/admin';
        $adminDst = $docRoot . '/local/admin';
        if (!is_dir($adminDst)) {
            @mkdir($adminDst, 0755, true);
        }
        if (is_dir($adminSrc)) {
            CopyDirFiles($adminSrc, $adminDst, true, true);
        }

        // Иконка модуля: install/images/admin_module_icon.png → /local/images/<MODULE_ID>/
        $moduleImgDir = $docRoot . '/local/images/' . self::MODULE_ID;
        if (!is_dir($moduleImgDir)) {
            @mkdir($moduleImgDir, 0755, true);
        }
        $adminIconSrc = __DIR__ . '/images/admin_module_icon.png';
        if (is_file($adminIconSrc)) {
            @copy($adminIconSrc, $moduleImgDir . '/admin_module_icon.png');
        }

        // Иконка раздела «5 УГЛОВ»: shared /local/images/fivecorners/logo.svg
        $sharedImgDir   = $docRoot . '/local/images/fivecorners';
        $sectionIconSrc = __DIR__ . '/images/section_icon.svg';
        $sectionIconDst = $sharedImgDir . '/logo.svg';
        if (!is_dir($sharedImgDir)) {
            @mkdir($sharedImgDir, 0755, true);
        }
        if (is_file($sectionIconSrc) && !is_file($sectionIconDst)) {
            @copy($sectionIconSrc, $sectionIconDst);
        }
    }

    public function UnInstallFiles(): void
    {
        $docRoot = rtrim((string)Application::getDocumentRoot(), '/\\');

        // Удаляем скопированные admin-страницы
        $adminSrc = __DIR__ . '/admin';
        $adminDst = $docRoot . '/local/admin';
        if (is_dir($adminSrc)) {
            foreach (glob($adminSrc . '/*.php') ?: [] as $srcFile) {
                $deployed = $adminDst . '/' . basename($srcFile);
                if (is_file($deployed)) {
                    @unlink($deployed);
                }
            }
            foreach (['ru', 'en'] as $lang) {
                $langSrc = $adminSrc . '/lang/' . $lang;
                $langDst = $adminDst . '/lang/' . $lang;
                if (is_dir($langSrc) && is_dir($langDst)) {
                    foreach (glob($langSrc . '/*.php') ?: [] as $srcLangFile) {
                        $deployed = $langDst . '/' . basename($srcLangFile);
                        if (is_file($deployed)) {
                            @unlink($deployed);
                        }
                    }
                }
                @rmdir($langDst);
            }
            @rmdir($adminDst . '/lang');
        }

        // Иконка модуля (вся директория принадлежит модулю)
        $moduleImgDir = $docRoot . '/local/images/' . self::MODULE_ID;
        if (is_dir($moduleImgDir)) {
            $this->removeDir($moduleImgDir);
        }

        // Иконка раздела: удаляем только если md5 совпадает
        $sectionIconDst = $docRoot . '/local/images/fivecorners/logo.svg';
        $sectionIconSrc = __DIR__ . '/images/section_icon.svg';
        if (
            is_file($sectionIconDst)
            && is_file($sectionIconSrc)
            && md5_file($sectionIconDst) === md5_file($sectionIconSrc)
        ) {
            @unlink($sectionIconDst);
        }

        $sharedImgDir = $docRoot . '/local/images/fivecorners';
        if (is_dir($sharedImgDir) && count(array_diff((array)scandir($sharedImgDir), ['.', '..'])) === 0) {
            @rmdir($sharedImgDir);
        }
    }

    public function InstallEvents(): void
    {
        $this->UnInstallEvents();

        $em = EventManager::getInstance();

        $em->registerEventHandler(
            'main', 'OnProlog', self::MODULE_ID,
            'FiveCorners\\CrmColors\\EventHandler', 'onProlog'
        );
        $em->registerEventHandler(
            'main', 'OnBuildGlobalMenu', self::MODULE_ID,
            'FiveCorners\\CrmColors\\AdminMenu', 'onBuildGlobalMenu'
        );
        $em->registerEventHandler(
            'main', 'OnProlog', self::MODULE_ID,
            'FiveCorners\\CrmColors\\AdminMenu', 'onProlog'
        );

        $em->clearLoadedHandlers();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff((array)scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function UnInstallEvents(): void
    {
        $em = EventManager::getInstance();

        $em->unRegisterEventHandler('main', 'OnProlog',          self::MODULE_ID, 'FiveCorners\\CrmColors\\EventHandler', 'onProlog');
        $em->unRegisterEventHandler('main', 'OnBuildGlobalMenu', self::MODULE_ID, 'FiveCorners\\CrmColors\\AdminMenu',    'onBuildGlobalMenu');
        $em->unRegisterEventHandler('main', 'OnProlog',          self::MODULE_ID, 'FiveCorners\\CrmColors\\AdminMenu',    'onProlog');

        $em->clearLoadedHandlers();
    }
}
