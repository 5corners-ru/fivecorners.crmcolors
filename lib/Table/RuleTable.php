<?php

namespace FiveCorners\CrmColors;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * ORM-таблица правил раскраски CRM.
 * Таблица: b_fc_crmcolors_rule
 */
class RuleTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_fc_crmcolors_rule';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary'      => true,
                'autocomplete' => true,
            ]),
            new BooleanField('ACTIVE', [
                'values'        => ['N', 'Y'],
                'default_value' => 'Y',
            ]),
            new IntegerField('SORT', [
                'default_value' => 100,
            ]),
            // Название правила (для отображения в админке)
            new StringField('NAME', [
                'default_value' => '',
            ]),
            // Тип сущности: DEAL | LEAD | CONTACT | COMPANY | DYNAMIC
            new StringField('ENTITY_TYPE', [
                'required' => true,
            ]),
            // ID воронки для DEAL: -1 = все воронки, 0 = стандартная, N = конкретная
            new IntegerField('CATEGORY_ID', [
                'default_value' => -1,
            ]),
            // ID типа смарт-процесса (только для DYNAMIC)
            new IntegerField('SMART_TYPE_ID', [
                'nullable' => true,
            ]),
            // Тип условия: FIELD_EQUALS | FIELD_NOT_EMPTY | FIELD_EMPTY | DATE_APPROACHING | STAGE_EQUALS
            new StringField('CONDITION_TYPE', [
                'required'      => true,
                'default_value' => 'FIELD_NOT_EMPTY',
            ]),
            // Код поля, по которому проверяется условие
            new StringField('CONDITION_FIELD', [
                'default_value' => '',
            ]),
            // Значение для сравнения (для FIELD_EQUALS и STAGE_EQUALS)
            new TextField('CONDITION_VALUE', [
                'nullable' => true,
            ]),
            // Количество дней (для DATE_APPROACHING)
            new IntegerField('CONDITION_DAYS', [
                'nullable' => true,
            ]),
            // Цвет всей карточки (hex, например #B5FFA2), пусто = не красить карточку
            new StringField('ACTION_CARD_COLOR', [
                'nullable' => true,
            ]),
            // Цвет поля (hex), пусто = не красить поле
            new StringField('ACTION_FIELD_COLOR', [
                'nullable' => true,
            ]),
            // Код поля, которое красить. Если пусто — красится поле из CONDITION_FIELD
            new StringField('ACTION_FIELD_CODE', [
                'nullable' => true,
            ]),
        ];
    }

    /**
     * Возвращает активные правила для конкретной страницы CRM.
     * Для сделок CATEGORY_ID=-1 означает «все воронки» — такие правила тоже попадают.
     */
    public static function getForPage(
        string $entityType,
        ?int   $smartTypeId = null,
        int    $categoryId  = -1
    ): array {
        $filter = [
            '=ENTITY_TYPE' => $entityType,
            '=ACTIVE'      => 'Y',
        ];

        if ($entityType === 'DYNAMIC') {
            $filter['=SMART_TYPE_ID'] = $smartTypeId;
        }

        $rows = static::getList([
            'filter' => $filter,
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ])->fetchAll();

        if ($entityType !== 'DEAL') {
            return $rows;
        }

        // Для сделок оставляем только правила: «все воронки» (CATEGORY_ID=-1) ИЛИ точное совпадение
        return array_values(array_filter($rows, static function (array $row) use ($categoryId): bool {
            $cat = (int)$row['CATEGORY_ID'];
            return $cat === -1 || $cat === $categoryId;
        }));
    }
}
