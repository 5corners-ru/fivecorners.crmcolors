<?php
defined('B_PROLOG_INCLUDED') || define('B_PROLOG_INCLUDED', true);
define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NEED_AUTH', true);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use FiveCorners\CrmColors\Manager;
use FiveCorners\CrmColors\PageHeader;
use FiveCorners\CrmColors\RuleTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);
Loc::loadMessages(__DIR__ . '/fivecorners_crmcolors_rules.php');

/** @var CUser $USER */
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('');
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

if (!Loader::includeModule('fivecorners.crmcolors')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

$ruleId = (int)($_REQUEST['id'] ?? 0);
$isNew  = ($ruleId === 0);
$errors  = [];
$success = '';

$dealCategories = Manager::getDealCategories();
$smartTypes     = Manager::getSmartTypes();
$allStagesData  = Manager::getAllStagesData();

$entityTypes = [
    'DEAL'    => Loc::getMessage('FCO_CC_ENTITY_DEAL'),
    'LEAD'    => Loc::getMessage('FCO_CC_ENTITY_LEAD'),
    'CONTACT' => Loc::getMessage('FCO_CC_ENTITY_CONTACT'),
    'COMPANY' => Loc::getMessage('FCO_CC_ENTITY_COMPANY'),
    'DYNAMIC' => Loc::getMessage('FCO_CC_ENTITY_DYNAMIC'),
];

$conditionTypes = [
    'FIELD_EQUALS'     => Loc::getMessage('FCO_CC_COND_FIELD_EQUALS'),
    'FIELD_NOT_EMPTY'  => Loc::getMessage('FCO_CC_COND_FIELD_NOT_EMPTY'),
    'FIELD_EMPTY'      => Loc::getMessage('FCO_CC_COND_FIELD_EMPTY'),
    'DATE_APPROACHING' => Loc::getMessage('FCO_CC_COND_DATE_APPROACHING'),
    'STAGE_EQUALS'     => Loc::getMessage('FCO_CC_COND_STAGE_EQUALS'),
];

$fields = [
    'ACTIVE'             => 'Y',
    'SORT'               => 100,
    'NAME'               => '',
    'ENTITY_TYPE'        => 'DEAL',
    'CATEGORY_ID'        => -1,
    'SMART_TYPE_ID'      => null,
    'CONDITION_TYPE'     => 'FIELD_NOT_EMPTY',
    'CONDITION_FIELD'    => '',
    'CONDITION_VALUE'    => '',
    'CONDITION_DAYS'     => 3,
    'ACTION_CARD_COLOR'      => '',
    'ACTION_CARD_COLOR_MODE' => 'FILL',
    'ACTION_FIELD_CODE'      => '',
    'ACTION_FIELD_COLOR'     => '',
];

if (!$isNew) {
    $existing = RuleTable::getById($ruleId)->fetch();
    if (!$existing) {
        LocalRedirect('/local/admin/fivecorners_crmcolors_rules.php?lang=' . LANGUAGE_ID);
    }
    foreach ($fields as $k => $default) {
        $fields[$k] = $existing[$k] ?? $default;
    }
}

// ——— Сохранение ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $fields['ACTIVE']             = (string)($_POST['ACTIVE'] ?? 'N');
    $fields['SORT']               = max(0, (int)($_POST['SORT'] ?? 100));
    $fields['NAME']               = trim((string)($_POST['NAME'] ?? ''));
    $fields['ENTITY_TYPE']        = (string)($_POST['ENTITY_TYPE'] ?? '');
    $fields['CATEGORY_ID']        = (int)($_POST['CATEGORY_ID'] ?? -1);
    $fields['SMART_TYPE_ID']      = ((int)($_POST['SMART_TYPE_ID'] ?? 0)) ?: null;
    $fields['CONDITION_TYPE']     = (string)($_POST['CONDITION_TYPE'] ?? '');
    $fields['CONDITION_FIELD']    = trim((string)($_POST['CONDITION_FIELD'] ?? ''));
    $fields['CONDITION_VALUE']    = trim((string)($_POST['CONDITION_VALUE'] ?? ''));

    // Для STAGE_EQUALS CONDITION_FIELD задаётся автоматически
    if ($fields['CONDITION_TYPE'] === 'STAGE_EQUALS') {
        $fields['CONDITION_FIELD'] = ($fields['ENTITY_TYPE'] === 'LEAD') ? 'STATUS_ID' : 'STAGE_ID';
    }
    $fields['CONDITION_DAYS']     = ((int)($_POST['CONDITION_DAYS'] ?? 0)) ?: null;
    $fields['ACTION_CARD_COLOR']      = trim((string)($_POST['ACTION_CARD_COLOR'] ?? ''));
    $fields['ACTION_CARD_COLOR_MODE'] = ((string)($_POST['ACTION_CARD_COLOR_MODE'] ?? 'FILL')) === 'BORDER' ? 'BORDER' : 'FILL';
    $fields['ACTION_FIELD_CODE']      = trim((string)($_POST['ACTION_FIELD_CODE'] ?? ''));
    $fields['ACTION_FIELD_COLOR']     = trim((string)($_POST['ACTION_FIELD_COLOR'] ?? ''));

    // ——— Нормализация (до валидации) ———
    if (empty($_POST['ENABLE_CARD_COLOR']) || $fields['ACTION_CARD_COLOR'] === '') {
        $fields['ACTION_CARD_COLOR'] = null;
    }
    if (empty($_POST['ENABLE_FIELD_COLOR']) || $fields['ACTION_FIELD_COLOR'] === '') {
        $fields['ACTION_FIELD_COLOR'] = null;
    }
    if ($fields['ACTION_FIELD_CODE'] === '') {
        $fields['ACTION_FIELD_CODE'] = null;
    }
    if ($fields['CONDITION_VALUE'] === '') {
        $fields['CONDITION_VALUE'] = null;
    }

    // ——— Валидация ———
    if ($fields['NAME'] === '') {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_NAME');
    }
    if (!array_key_exists($fields['ENTITY_TYPE'], $entityTypes)) {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_ENTITY');
    }
    if (!array_key_exists($fields['CONDITION_TYPE'], $conditionTypes)) {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_COND_TYPE');
    }
    if ($fields['CONDITION_FIELD'] === '' && $fields['CONDITION_TYPE'] !== 'STAGE_EQUALS') {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_COND_FIELD');
    }
    if ($fields['ACTION_CARD_COLOR'] === null && $fields['ACTION_FIELD_COLOR'] === null) {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_NO_ACTION');
    }
    if ($fields['ENTITY_TYPE'] === 'DYNAMIC' && $fields['SMART_TYPE_ID'] === null) {
        $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_SMART_TYPE');
    }

    if (empty($errors)) {
        try {
            if ($isNew) {
                $result = RuleTable::add($fields);
                if ($result->isSuccess()) {
                    $ruleId = $result->getId();
                    $isNew  = false;
                    $success = Loc::getMessage('FCO_CC_EDIT_SAVED');
                } else {
                    $errors = $result->getErrorMessages();
                }
            } else {
                $result = RuleTable::update($ruleId, $fields);
                if ($result->isSuccess()) {
                    $success = Loc::getMessage('FCO_CC_EDIT_SAVED');
                } else {
                    $errors = $result->getErrorMessages();
                }
            }
        } catch (\Exception $e) {
            $errors[] = Loc::getMessage('FCO_CC_EDIT_ERR_SAVE') . $e->getMessage();
        }
    }
}

$selfPage      = htmlspecialcharsbx($APPLICATION->GetCurPage());
$listPage      = '/local/admin/fivecorners_crmcolors_rules.php';
$pageTitle     = $isNew ? Loc::getMessage('FCO_CC_EDIT_TITLE_ADD') : Loc::getMessage('FCO_CC_EDIT_TITLE_EDIT');
$moduleVersion = ModuleManager::getVersion('fivecorners.crmcolors');

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle($pageTitle);
PageHeader::addStyles($APPLICATION);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'tab_main',      'TAB' => Loc::getMessage('FCO_CC_EDIT_SECTION_MAIN')],
    ['DIV' => 'tab_condition', 'TAB' => Loc::getMessage('FCO_CC_EDIT_SECTION_CONDITION')],
    ['DIV' => 'tab_action',    'TAB' => Loc::getMessage('FCO_CC_EDIT_SECTION_ACTION')],
]);

PageHeader::renderOpen($moduleVersion, 'rules');
?>

<style>
.fc-cc-hint { font-size:11px; color:#888; margin-top:3px; }
.fc-cc-color-row { display:flex; align-items:center; gap:8px; }
</style>

<?php if ($errors): ?>
<div class="adm-info-message-wrap adm-info-message-red" style="margin-bottom:16px;">
    <div class="adm-info-message">
        <ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialcharsbx($e) ?></li><?php endforeach; ?></ul>
    </div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="adm-info-message-wrap adm-info-message-green" style="margin-bottom:16px;">
    <div class="adm-info-message"><?= htmlspecialcharsbx($success) ?></div>
</div>
<?php endif; ?>

<form method="post" action="<?= $selfPage ?>?id=<?= $ruleId ?>&lang=<?= LANGUAGE_ID ?>">
<?= bitrix_sessid_post() ?>

<?php $tabControl->Begin(); ?>

<?php $tabControl->BeginNextTab(); // tab_main ?>
<tr>
    <td width="40%"><?= Loc::getMessage('FCO_CC_EDIT_FIELD_NAME') ?></td>
    <td><input type="text" name="NAME" value="<?= htmlspecialcharsbx($fields['NAME']) ?>" size="50" class="adm-input"></td>
</tr>
<tr>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_ACTIVE') ?></td>
    <td><input type="checkbox" name="ACTIVE" value="Y" <?= $fields['ACTIVE'] === 'Y' ? 'checked' : '' ?>></td>
</tr>
<tr>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_SORT') ?></td>
    <td><input type="number" name="SORT" value="<?= (int)$fields['SORT'] ?>" size="10" class="adm-input"></td>
</tr>
<tr>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_ENTITY_TYPE') ?></td>
    <td>
        <select name="ENTITY_TYPE" id="fc_cc_entity_type" class="adm-input" onchange="fcCcToggleEntityFields()">
            <?php foreach ($entityTypes as $val => $label): ?>
            <option value="<?= htmlspecialcharsbx($val) ?>" <?= $fields['ENTITY_TYPE'] === $val ? 'selected' : '' ?>>
                <?= htmlspecialcharsbx($label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr id="fc_cc_row_category" <?= $fields['ENTITY_TYPE'] !== 'DEAL' ? 'style="display:none"' : '' ?>>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_CATEGORY_ID') ?></td>
    <td>
        <select name="CATEGORY_ID" class="adm-input">
            <option value="-1" <?= (int)$fields['CATEGORY_ID'] === -1 ? 'selected' : '' ?>>
                <?= Loc::getMessage('FCO_CC_EDIT_CATEGORY_ALL') ?>
            </option>
            <option value="0" <?= (int)$fields['CATEGORY_ID'] === 0 ? 'selected' : '' ?>>
                <?= Loc::getMessage('FCO_CC_EDIT_CATEGORY_DEFAULT') ?>
            </option>
            <?php foreach ($dealCategories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= (int)$fields['CATEGORY_ID'] === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialcharsbx($cat['name']) ?> (<?= (int)$cat['id'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr id="fc_cc_row_smart" <?= $fields['ENTITY_TYPE'] !== 'DYNAMIC' ? 'style="display:none"' : '' ?>>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_SMART_TYPE') ?></td>
    <td>
        <select name="SMART_TYPE_ID" class="adm-input">
            <option value=""><?= Loc::getMessage('FCO_CC_EDIT_SMART_SELECT') ?></option>
            <?php foreach ($smartTypes as $type): ?>
            <option value="<?= (int)$type['id'] ?>" <?= (int)$fields['SMART_TYPE_ID'] === (int)$type['id'] ? 'selected' : '' ?>>
                <?= htmlspecialcharsbx($type['title']) ?> (<?= (int)$type['id'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>

<?php $tabControl->BeginNextTab(); // tab_condition ?>
<tr>
    <td width="40%"><?= Loc::getMessage('FCO_CC_EDIT_FIELD_COND_TYPE') ?></td>
    <td>
        <select name="CONDITION_TYPE" id="fc_cc_cond_type" class="adm-input" onchange="fcCcToggleConditionFields()">
            <?php foreach ($conditionTypes as $val => $label): ?>
            <option value="<?= htmlspecialcharsbx($val) ?>" <?= $fields['CONDITION_TYPE'] === $val ? 'selected' : '' ?>>
                <?= htmlspecialcharsbx($label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_COND_FIELD') ?></td>
    <td>
        <div id="fc_cc_cond_field_manual">
            <input type="text" name="CONDITION_FIELD" id="fc_cc_cond_field_input" value="<?= htmlspecialcharsbx($fields['CONDITION_FIELD']) ?>" size="40" class="adm-input" placeholder="UF_CRM_1234567890">
            <div class="fc-cc-hint"><?= Loc::getMessage('FCO_CC_EDIT_HINT_COND_FIELD') ?></div>
        </div>
        <div id="fc_cc_cond_field_auto" style="display:none">
            <span class="fc-cc-hint"><?= Loc::getMessage('FCO_CC_EDIT_HINT_STAGE_AUTO') ?></span>
        </div>
    </td>
</tr>
<tr id="fc_cc_row_cond_value">
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_COND_VALUE') ?></td>
    <td>
        <input type="hidden" name="CONDITION_VALUE" id="fc_cc_cond_value_final"
               value="<?= htmlspecialcharsbx((string)$fields['CONDITION_VALUE']) ?>">
        <input type="text" id="fc_cc_cond_value_text"
               value="<?= htmlspecialcharsbx((string)$fields['CONDITION_VALUE']) ?>"
               size="40" class="adm-input"
               oninput="document.getElementById('fc_cc_cond_value_final').value=this.value">
        <select id="fc_cc_stage_select" class="adm-input" style="display:none"
                onchange="document.getElementById('fc_cc_cond_value_final').value=this.value">
            <option value=""><?= Loc::getMessage('FCO_CC_EDIT_STAGE_SELECT') ?></option>
        </select>
        <div class="fc-cc-hint" id="fc_cc_hint_cond_value"><?= Loc::getMessage('FCO_CC_EDIT_HINT_COND_VALUE') ?></div>
        <div class="fc-cc-hint" id="fc_cc_hint_stage_value" style="display:none"><?= Loc::getMessage('FCO_CC_EDIT_HINT_STAGE_VALUE') ?></div>
    </td>
</tr>
<tr id="fc_cc_row_cond_days" style="display:none">
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_COND_DAYS') ?></td>
    <td>
        <input type="number" name="CONDITION_DAYS" value="<?= (int)($fields['CONDITION_DAYS'] ?? 3) ?>" size="10" min="0" class="adm-input">
        <div class="fc-cc-hint"><?= Loc::getMessage('FCO_CC_EDIT_HINT_COND_DAYS') ?></div>
    </td>
</tr>

<?php $tabControl->BeginNextTab(); // tab_action ?>
<tr>
    <td colspan="2" style="padding-bottom:8px;">
        <em><?= Loc::getMessage('FCO_CC_EDIT_HINT_CARD_COLOR') ?></em>
    </td>
</tr>
<tr>
    <td width="40%"><?= Loc::getMessage('FCO_CC_EDIT_FIELD_CARD_COLOR') ?></td>
    <td>
        <div class="fc-cc-color-row">
            <input type="checkbox" name="ENABLE_CARD_COLOR" id="fc_cc_enable_card_color" value="Y"
                <?= $fields['ACTION_CARD_COLOR'] ? 'checked' : '' ?>
                onchange="fcCcToggleColorPicker('fc_cc_card_color_wrap', this.checked)">
            <div id="fc_cc_card_color_wrap" <?= $fields['ACTION_CARD_COLOR'] ? '' : 'style="display:none"' ?>>
                <div class="fc-cc-color-row">
                    <input type="color" name="ACTION_CARD_COLOR" id="fc_cc_card_color"
                        value="<?= htmlspecialcharsbx($fields['ACTION_CARD_COLOR'] ?: '#ffff99') ?>">
                </div>
                <div class="fc-cc-color-row" style="margin-top:8px;">
                    <label>
                        <input type="radio" name="ACTION_CARD_COLOR_MODE" value="FILL"
                            <?= $fields['ACTION_CARD_COLOR_MODE'] !== 'BORDER' ? 'checked' : '' ?>>
                        <?= Loc::getMessage('FCO_CC_EDIT_CARD_MODE_FILL') ?>
                    </label>
                    <label>
                        <input type="radio" name="ACTION_CARD_COLOR_MODE" value="BORDER"
                            <?= $fields['ACTION_CARD_COLOR_MODE'] === 'BORDER' ? 'checked' : '' ?>>
                        <?= Loc::getMessage('FCO_CC_EDIT_CARD_MODE_BORDER') ?>
                    </label>
                </div>
            </div>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2" style="padding-top:16px;padding-bottom:8px;border-top:1px solid #eee;">
        <em><?= Loc::getMessage('FCO_CC_EDIT_HINT_FIELD_COLOR') ?></em>
    </td>
</tr>
<tr>
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_FIELD_COLOR') ?></td>
    <td>
        <div class="fc-cc-color-row">
            <input type="checkbox" name="ENABLE_FIELD_COLOR" id="fc_cc_enable_field_color" value="Y"
                <?= $fields['ACTION_FIELD_COLOR'] ? 'checked' : '' ?>
                onchange="fcCcToggleColorPicker('fc_cc_field_color_wrap', this.checked)">
            <div id="fc_cc_field_color_wrap" <?= $fields['ACTION_FIELD_COLOR'] ? '' : 'style="display:none"' ?>>
                <input type="color" name="ACTION_FIELD_COLOR" id="fc_cc_field_color"
                    value="<?= htmlspecialcharsbx($fields['ACTION_FIELD_COLOR'] ?: '#fff3cd') ?>">
            </div>
        </div>
    </td>
</tr>
<tr id="fc_cc_row_field_code">
    <td><?= Loc::getMessage('FCO_CC_EDIT_FIELD_FIELD_CODE') ?></td>
    <td>
        <input type="text" name="ACTION_FIELD_CODE" value="<?= htmlspecialcharsbx((string)$fields['ACTION_FIELD_CODE']) ?>" size="40" class="adm-input" placeholder="UF_CRM_1234567890">
        <div class="fc-cc-hint"><?= Loc::getMessage('FCO_CC_EDIT_HINT_FIELD_CODE') ?></div>
    </td>
</tr>

<?php $tabControl->Buttons(['btnSave' => true, 'btnCancel' => true, 'back_url' => $listPage . '?lang=' . LANGUAGE_ID]); ?>

<?php $tabControl->End(); ?>
</form>

<script>
var fcCcStagesData = <?= json_encode($allStagesData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var fcCcStageSelectPlaceholder = <?= json_encode(Loc::getMessage('FCO_CC_EDIT_STAGE_SELECT'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function fcCcToggleEntityFields() {
    var et = document.getElementById('fc_cc_entity_type').value;
    document.getElementById('fc_cc_row_category').style.display = (et === 'DEAL')    ? '' : 'none';
    document.getElementById('fc_cc_row_smart').style.display    = (et === 'DYNAMIC') ? '' : 'none';
    var ct = document.getElementById('fc_cc_cond_type').value;
    if (ct === 'STAGE_EQUALS') fcCcUpdateStageOptions();
}

function fcCcToggleConditionFields() {
    var ct = document.getElementById('fc_cc_cond_type').value;
    var isStage   = (ct === 'STAGE_EQUALS');
    var isFieldEq = (ct === 'FIELD_EQUALS');

    document.getElementById('fc_cc_row_cond_value').style.display = (isFieldEq || isStage) ? '' : 'none';
    document.getElementById('fc_cc_row_cond_days').style.display  = (ct === 'DATE_APPROACHING') ? '' : 'none';

    // text ↔ select для значения
    document.getElementById('fc_cc_cond_value_text').style.display  = isStage ? 'none' : '';
    document.getElementById('fc_cc_stage_select').style.display     = isStage ? '' : 'none';
    document.getElementById('fc_cc_hint_cond_value').style.display  = isStage ? 'none' : '';
    document.getElementById('fc_cc_hint_stage_value').style.display = isStage ? '' : 'none';

    // поле условия: ручной ввод ↔ авто-подпись
    document.getElementById('fc_cc_cond_field_manual').style.display = isStage ? 'none' : '';
    document.getElementById('fc_cc_cond_field_auto').style.display   = isStage ? '' : 'none';

    if (isStage) fcCcUpdateStageOptions();
}

function fcCcUpdateStageOptions() {
    var et      = document.getElementById('fc_cc_entity_type').value;
    var catSel  = document.querySelector('select[name="CATEGORY_ID"]');
    var smartSel = document.querySelector('select[name="SMART_TYPE_ID"]');
    var stages  = [];

    if (et === 'DEAL') {
        var catId = catSel ? parseInt(catSel.value, 10) : 0;
        if (catId < 0) catId = 0;
        var dealMap = fcCcStagesData['DEAL'] || {};
        stages = dealMap[catId] || dealMap[Object.keys(dealMap)[0]] || [];
    } else if (et === 'LEAD') {
        stages = ((fcCcStagesData['LEAD'] || {})[0]) || [];
    } else if (et === 'DYNAMIC') {
        var smartId = smartSel ? parseInt(smartSel.value, 10) : 0;
        stages = ((fcCcStagesData['DYNAMIC'] || {})[smartId]) || [];
    }

    var sel      = document.getElementById('fc_cc_stage_select');
    var finalInp = document.getElementById('fc_cc_cond_value_final');
    var curVal   = finalInp.value;

    sel.innerHTML = '';
    var ph = document.createElement('option');
    ph.value = '';
    ph.textContent = fcCcStageSelectPlaceholder;
    sel.appendChild(ph);

    stages.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name + ' (' + s.id + ')';
        if (s.id === curVal) opt.selected = true;
        sel.appendChild(opt);
    });

    finalInp.value = sel.value;
}

function fcCcToggleColorPicker(wrapperId, show) {
    document.getElementById(wrapperId).style.display = show ? '' : 'none';
}

BX.ready(function() {
    // Подписка на смену воронки и смарта — обновляем список стадий
    var catSel = document.querySelector('select[name="CATEGORY_ID"]');
    if (catSel) catSel.addEventListener('change', function() {
        if (document.getElementById('fc_cc_cond_type').value === 'STAGE_EQUALS') fcCcUpdateStageOptions();
    });
    var smartSel = document.querySelector('select[name="SMART_TYPE_ID"]');
    if (smartSel) smartSel.addEventListener('change', function() {
        if (document.getElementById('fc_cc_cond_type').value === 'STAGE_EQUALS') fcCcUpdateStageOptions();
    });

    fcCcToggleEntityFields();
    fcCcToggleConditionFields();
});
</script>

<?php
PageHeader::renderClose();
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
