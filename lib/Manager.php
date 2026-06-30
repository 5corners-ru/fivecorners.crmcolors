<?php

namespace FiveCorners\CrmColors;

use Bitrix\Main\Loader;

class Manager
{
    /**
     * Возвращает правила для страницы в формате для JS.
     */
    public static function getRulesForPage(
        string $entityType,
        ?int   $smartTypeId = null,
        int    $categoryId  = -1
    ): array {
        $rows = RuleTable::getForPage($entityType, $smartTypeId, $categoryId);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'             => (int)$row['ID'],
                'conditionType'  => (string)$row['CONDITION_TYPE'],
                'conditionField' => (string)$row['CONDITION_FIELD'],
                'conditionValue' => $row['CONDITION_VALUE'] !== null ? (string)$row['CONDITION_VALUE'] : null,
                'conditionDays'  => $row['CONDITION_DAYS'] !== null ? (int)$row['CONDITION_DAYS'] : null,
                'actionCardColor'  => $row['ACTION_CARD_COLOR']  ?: null,
                'actionFieldColor' => $row['ACTION_FIELD_COLOR'] ?: null,
                'actionFieldCode'  => $row['ACTION_FIELD_CODE']  ?: null,
            ];
        }

        return $result;
    }

    /**
     * Список воронок сделок для AdminMenu и форм.
     */
    public static function getDealCategories(): array
    {
        if (!Loader::includeModule('crm')) {
            return [];
        }

        try {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
            if (!$factory) {
                return [];
            }

            $result = [];
            foreach ($factory->getCategories() as $cat) {
                $result[] = [
                    'id'   => (int)$cat->getId(),
                    'name' => $cat->getName() ?: ('Воронка #' . $cat->getId()),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Все стадии/статусы по сущностям и воронкам — для JS-дропдауна на форме правила.
     * Формат: ['DEAL' => [catId => [['id'=>..,'name'=>..], ..]], 'LEAD' => [0=>[..]], 'DYNAMIC' => [typeId=>[..]]]
     */
    public static function getAllStagesData(): array
    {
        if (!Loader::includeModule('crm')) {
            return [];
        }

        $result = ['DEAL' => [], 'LEAD' => [], 'DYNAMIC' => []];

        // Сделки — стадии по воронкам
        try {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
            if ($factory && $factory->isStagesSupported()) {
                foreach ($factory->getCategories() as $cat) {
                    $catId  = (int)$cat->getId();
                    $stages = [];
                    foreach ($factory->getStages($catId) as $stage) {
                        $stages[] = ['id' => $stage->getStatusId(), 'name' => $stage->getName()];
                    }
                    $result['DEAL'][$catId] = $stages;
                }
            }
        } catch (\Throwable $e) {}

        // Лиды — статусы (isStagesSupported включает и лиды)
        try {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Lead);
            if ($factory && $factory->isStagesSupported()) {
                $stages = [];
                foreach ($factory->getStages() as $stage) {
                    $stages[] = ['id' => $stage->getStatusId(), 'name' => $stage->getName()];
                }
                $result['LEAD'][0] = $stages;
            }
        } catch (\Throwable $e) {}

        // Смарт-процессы — стадии
        try {
            foreach (self::getSmartTypes() as $type) {
                $smartTypeId = (int)$type['id'];
                $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($smartTypeId);
                if (!$factory || !$factory->isStagesSupported()) {
                    continue;
                }
                $stages = [];
                foreach ($factory->getStages() as $stage) {
                    $stages[] = ['id' => $stage->getStatusId(), 'name' => $stage->getName()];
                }
                $result['DYNAMIC'][$smartTypeId] = $stages;
            }
        } catch (\Throwable $e) {}

        return $result;
    }

    /**
     * Список типов смарт-процессов для AdminMenu и форм.
     * Возвращает ['id' => entityTypeId, 'title' => ...].
     */
    public static function getSmartTypes(): array
    {
        if (!Loader::includeModule('crm')) {
            return [];
        }

        try {
            $rows = \Bitrix\Crm\Model\Dynamic\TypeTable::getList([
                'select' => ['ID', 'ENTITY_TYPE_ID', 'TITLE'],
                'order'  => ['ID' => 'ASC'],
            ]);
            $result = [];
            while ($row = $rows->fetch()) {
                $entityTypeId = (int)($row['ENTITY_TYPE_ID'] ?: $row['ID']);
                $result[] = [
                    'id'    => $entityTypeId,
                    'title' => $row['TITLE'] ?: ('Смарт #' . $entityTypeId),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
