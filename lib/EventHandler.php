<?php

namespace FiveCorners\CrmColors;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;

class EventHandler
{
    private const MODULE_ID = 'fivecorners.crmcolors';

    /**
     * main::OnProlog — инжектирует JS с правилами на CRM-страницы.
     */
    public static function onProlog(): void
    {
        if (!Loader::includeModule(self::MODULE_ID)) {
            return;
        }

        $uri  = Application::getInstance()->getContext()->getRequest()->getRequestUri();
        $path = strtok($uri, '?');

        $entityType  = null;
        $smartTypeId = null;
        $categoryId  = -1;
        $entityId    = 0;
        $pageType    = 'DETAIL';

        // Детальная карточка сделки/лида/контакта/компании
        if (preg_match('#^/crm/(deal|lead|contact|company)/details/(\d+)/?#i', $path, $m)) {
            $entityType = strtoupper($m[1]);
            $entityId   = (int)$m[2];
            $pageType   = 'DETAIL';
        }
        // Детальная карточка смарт-процесса
        elseif (preg_match('#^/crm/type/(\d+)/details/(\d+)/?#i', $path, $m)) {
            $entityType  = 'DYNAMIC';
            $smartTypeId = (int)$m[1];
            $entityId    = (int)$m[2];
            $pageType    = 'DETAIL';
        }
        // Канбан сделок
        elseif (preg_match('#^/crm/deal/kanban(?:/category/(\d+))?/?#i', $path, $m)) {
            $entityType = 'DEAL';
            $categoryId = isset($m[1]) ? (int)$m[1] : 0;
            $pageType   = 'KANBAN';
        }
        // Канбан смарт-процесса
        elseif (preg_match('#^/crm/type/(\d+)/kanban/?#i', $path, $m)) {
            $entityType  = 'DYNAMIC';
            $smartTypeId = (int)$m[1];
            $pageType    = 'KANBAN';
        }
        // Список лидов (может быть Канбан)
        elseif (preg_match('#^/crm/lead/?$#i', $path) || preg_match('#^/crm/lead/kanban/?#i', $path)) {
            $entityType = 'LEAD';
            $pageType   = 'KANBAN';
        }
        // Список смартов
        elseif (preg_match('#^/crm/type/(\d+)/(?:list|kanban)?/?#i', $path, $m)) {
            $entityType  = 'DYNAMIC';
            $smartTypeId = (int)$m[1];
            $pageType    = 'KANBAN';
        }

        if ($entityType === null) {
            return;
        }

        // Для детальной карточки сделки — определяем воронку из самой сделки
        if ($entityType === 'DEAL' && $entityId > 0 && $pageType === 'DETAIL') {
            try {
                if (Loader::includeModule('crm')
                    && self::checkCrmReadPermission(\CCrmOwnerType::Deal, $entityId)
                ) {
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
                    $item    = $factory ? $factory->getItem($entityId) : null;
                    if ($item) {
                        $categoryId = (int)$item->getCategoryId();
                    }
                }
            } catch (\Throwable $e) {
                $categoryId = 0;
            }
        }

        $rules = Manager::getRulesForPage($entityType, $smartTypeId, $categoryId);
        if (empty($rules)) {
            return;
        }

        // Для детальной карточки передаём текущие значения полей с сервера
        $currentValues = [];
        if ($pageType === 'DETAIL' && $entityId > 0) {
            $ownerTypeIdForCheck = self::getOwnerTypeId($entityType, $smartTypeId);
            if ($ownerTypeIdForCheck !== null
                && Loader::includeModule('crm')
                && self::checkCrmReadPermission($ownerTypeIdForCheck, $entityId)
            ) {
                $currentValues = self::fetchCurrentValues($entityType, $entityId, $smartTypeId, $rules);
            }
        }

        $jsonFlags     = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $config = [
            'rules'        => $rules,
            'pageType'     => $pageType,
            'entityType'   => $entityType,
            'categoryId'   => $categoryId,
            'smartTypeId'  => $smartTypeId,
            'entityId'     => $entityId,
            'currentValues' => $currentValues,
        ];

        $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
        $moduleDir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
        $jsRelPath = '/' . ltrim(str_replace($docRoot, '', $moduleDir), '/') . '/js/crmcolors.js';
        $mtime     = is_file($docRoot . $jsRelPath) ? @filemtime($docRoot . $jsRelPath) : 0;
        $jsUrl     = htmlspecialchars($jsRelPath . '?v=' . $mtime, ENT_QUOTES, 'UTF-8');

        $configJson = json_encode($config, $jsonFlags);

        Asset::getInstance()->addString(
            '<script>window.FcCrmColorsConfig=' . $configJson . ';</script>' .
            '<script src="' . $jsUrl . '"></script>',
            false,
            \Bitrix\Main\Page\AssetLocation::AFTER_JS
        );
    }

    /**
     * Читает текущие значения полей для детальной карточки через CRM Factory API.
     */
    private static function fetchCurrentValues(
        string $entityType,
        int    $entityId,
        ?int   $smartTypeId,
        array  $rules
    ): array {
        if (!Loader::includeModule('crm')) {
            return [];
        }

        try {
            $ownerTypeId = self::getOwnerTypeId($entityType, $smartTypeId);
            if ($ownerTypeId === null) {
                return [];
            }

            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($ownerTypeId);
            if (!$factory) {
                return [];
            }

            $item = $factory->getItem($entityId);
            if (!$item) {
                return [];
            }

            $values = [];
            foreach ($rules as $rule) {
                $field = $rule['conditionField'] ?? '';
                if (!$field || isset($values[$field])) {
                    continue;
                }
                try {
                    $val = $item->get($field);
                    if ($val !== null && $val !== '') {
                        $values[$field] = is_array($val)
                            ? array_values(array_map('strval', $val))
                            : (string)$val;
                    }
                } catch (\Throwable $e) {
                    // поле отсутствует — пропускаем
                }
            }

            return $values;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function checkCrmReadPermission(int $ownerTypeId, int $entityId): bool
    {
        try {
            $userPermissions = \Bitrix\Crm\Service\Container::getInstance()->getUserPermissions();
            return $userPermissions->checkReadPermissions($ownerTypeId, $entityId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function getOwnerTypeId(string $entityType, ?int $smartTypeId): ?int
    {
        switch ($entityType) {
            case 'DEAL':    return \CCrmOwnerType::Deal;
            case 'LEAD':    return \CCrmOwnerType::Lead;
            case 'CONTACT': return \CCrmOwnerType::Contact;
            case 'COMPANY': return \CCrmOwnerType::Company;
            case 'DYNAMIC': return $smartTypeId;
            default:        return null;
        }
    }
}
