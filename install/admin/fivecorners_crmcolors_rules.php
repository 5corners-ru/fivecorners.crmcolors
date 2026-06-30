<?php
defined('B_PROLOG_INCLUDED') || define('B_PROLOG_INCLUDED', true);
define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NEED_AUTH', true);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use FiveCorners\CrmColors\PageHeader;
use FiveCorners\CrmColors\RuleTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

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

// ——— POST-действия ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            RuleTable::delete($deleteId);
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID);
    }

    if ($action === 'toggle') {
        $toggleId = (int)($_POST['id'] ?? 0);
        if ($toggleId > 0) {
            $existing = RuleTable::getById($toggleId)->fetch();
            if ($existing) {
                RuleTable::update($toggleId, ['ACTIVE' => $existing['ACTIVE'] === 'Y' ? 'N' : 'Y']);
            }
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID);
    }
}

// ——— Сортировка ———
$allowedFields = ['ID', 'ACTIVE', 'SORT', 'NAME'];
$by  = strtoupper((string)($_REQUEST['by']    ?? 'SORT'));
$ord = strtoupper((string)($_REQUEST['order'] ?? 'ASC'));
$by  = in_array($by,  $allowedFields,    true) ? $by  : 'SORT';
$ord = in_array($ord, ['ASC', 'DESC'],   true) ? $ord : 'ASC';

// ——— Фильтр ———
$filter = [];
if (($_REQUEST['set_filter'] ?? '') === 'Y') {
    if (!empty($_REQUEST['ENTITY_TYPE'])) {
        $filter['=ENTITY_TYPE'] = (string)$_REQUEST['ENTITY_TYPE'];
    }
    if (isset($_REQUEST['ACTIVE']) && in_array($_REQUEST['ACTIVE'], ['Y', 'N'], true)) {
        $filter['=ACTIVE'] = $_REQUEST['ACTIVE'];
    }
} elseif (($_REQUEST['del_filter'] ?? '') !== 'Y') {
    $filter['=ACTIVE'] = 'Y';
}

$rows = RuleTable::getList(['order' => [$by => $ord], 'filter' => $filter])->fetchAll();

$moduleVersion = ModuleManager::getVersion('fivecorners.crmcolors');
$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle(Loc::getMessage('FCO_CC_RULES_TITLE'));
PageHeader::addStyles($APPLICATION);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$entityLabels = [
    'DEAL'    => Loc::getMessage('FCO_CC_ENTITY_DEAL'),
    'LEAD'    => Loc::getMessage('FCO_CC_ENTITY_LEAD'),
    'CONTACT' => Loc::getMessage('FCO_CC_ENTITY_CONTACT'),
    'COMPANY' => Loc::getMessage('FCO_CC_ENTITY_COMPANY'),
    'DYNAMIC' => Loc::getMessage('FCO_CC_ENTITY_DYNAMIC'),
];

$conditionLabels = [
    'FIELD_EQUALS'     => Loc::getMessage('FCO_CC_COND_FIELD_EQUALS'),
    'FIELD_NOT_EMPTY'  => Loc::getMessage('FCO_CC_COND_FIELD_NOT_EMPTY'),
    'FIELD_EMPTY'      => Loc::getMessage('FCO_CC_COND_FIELD_EMPTY'),
    'DATE_APPROACHING' => Loc::getMessage('FCO_CC_COND_DATE_APPROACHING'),
    'STAGE_EQUALS'     => Loc::getMessage('FCO_CC_COND_STAGE_EQUALS'),
];

$selfPage = htmlspecialcharsbx($APPLICATION->GetCurPage());
$editPage = '/local/admin/fivecorners_crmcolors_rule_edit.php';
$nextOrd  = $ord === 'ASC' ? 'desc' : 'asc';

PageHeader::renderOpen($moduleVersion, 'rules');
?>

<style>
.fc-cc-filter-form { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; background:#f8fafc; border:1px solid #e2e8ef; border-radius:8px; padding:12px 16px; margin-bottom:16px; }
.fc-cc-filter-form label { font-size:12px; color:#5a6b7b; display:flex; flex-direction:column; gap:4px; }
.fc-cc-filter-form select { padding:6px 8px; border:1px solid #c5d1df; border-radius:4px; font-size:13px; min-width:140px; }
.fc-cc-filter-btns { display:flex; gap:6px; align-self:flex-end; }
.fc-cc-table-wrap { overflow-x:auto; }
.fc-cc-table { width:100%; border-collapse:collapse; }
.fc-cc-table th { background:#f4f7fb; padding:10px 12px; text-align:left; font-size:13px; font-weight:600; color:#3d4d5d; border-bottom:2px solid #dce4ec; white-space:nowrap; }
.fc-cc-table th a { color:#3d4d5d; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
.fc-cc-table th a:hover { color:#1058d0; }
.fc-cc-table td { padding:9px 12px; font-size:13px; color:#3d4d5d; border-bottom:1px solid #edf1f5; vertical-align:middle; }
.fc-cc-table tr:last-child td { border-bottom:none; }
.fc-cc-table tbody tr:hover td { background:#f8fafc; }
.fc-cc-table tbody tr.fc-cc-inactive td { opacity:.55; }
.fc-cc-badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:11px; font-weight:700; }
.fc-cc-badge--on  { background:#e3f3ea; color:#1e9e54; }
.fc-cc-badge--off { background:#eef1f4; color:#aab4be; }
.fc-cc-dot { display:inline-block; width:18px; height:18px; border-radius:4px; border:1px solid rgba(0,0,0,.12); vertical-align:middle; }
.fc-cc-dot--round { border-radius:50%; }
.fc-cc-actions { display:flex; gap:4px; flex-wrap:nowrap; }
.fc-cc-act-btn { display:inline-flex; align-items:center; padding:4px 10px; border-radius:4px; font-size:12px; text-decoration:none; border:1px solid #c5d1df; background:#f5f7fa; color:#3d4d5d; cursor:pointer; white-space:nowrap; font-family:inherit; line-height:1.4; }
.fc-cc-act-btn:hover { background:#e8edf4; color:#1058d0; border-color:#a8b8cc; }
.fc-cc-act-btn--danger:hover { background:#fef0ee; color:#c0392b; border-color:#f5b7b1; }
.fc-cc-empty { padding:48px 24px; text-align:center; color:#8a9ab0; font-size:14px; background:#f8fafc; border:1px dashed #d8e2ec; border-radius:8px; }
.fc-cc-sort-arrow { font-size:10px; opacity:.45; }
.fc-cc-sort-arrow--active { opacity:1; color:#1058d0; }
.fc-cc-add-row { margin-bottom:16px; }
</style>

<div class="fc-cc-add-row">
    <a href="<?= $editPage ?>?lang=<?= LANGUAGE_ID ?>" class="adm-btn">
        <?= Loc::getMessage('FCO_CC_RULES_ADD') ?>
    </a>
</div>

<form method="get" action="<?= $selfPage ?>" class="fc-cc-filter-form">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="set_filter" value="Y">

    <label><?= Loc::getMessage('FCO_CC_RULES_COL_ENTITY') ?>
        <select name="ENTITY_TYPE">
            <option value=""><?= Loc::getMessage('FCO_CC_FILTER_ALL') ?></option>
            <?php foreach ($entityLabels as $val => $label): ?>
            <option value="<?= htmlspecialcharsbx($val) ?>" <?= (($_REQUEST['ENTITY_TYPE'] ?? '') === $val) ? 'selected' : '' ?>>
                <?= htmlspecialcharsbx($label) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label><?= Loc::getMessage('FCO_CC_RULES_COL_ACTIVE') ?>
        <select name="ACTIVE">
            <option value=""><?= Loc::getMessage('FCO_CC_FILTER_ALL') ?></option>
            <option value="Y" <?= (($_REQUEST['ACTIVE'] ?? '') === 'Y') ? 'selected' : '' ?>><?= Loc::getMessage('FCO_CC_YES') ?></option>
            <option value="N" <?= (($_REQUEST['ACTIVE'] ?? '') === 'N') ? 'selected' : '' ?>><?= Loc::getMessage('FCO_CC_NO') ?></option>
        </select>
    </label>

    <div class="fc-cc-filter-btns">
        <button type="submit" class="adm-btn-save"><?= Loc::getMessage('FCO_CC_FILTER_APPLY') ?></button>
        <a href="<?= $selfPage ?>?lang=<?= LANGUAGE_ID ?>&del_filter=Y" class="adm-btn"><?= Loc::getMessage('FCO_CC_FILTER_RESET') ?></a>
    </div>
</form>

<?php if (empty($rows)): ?>
<div class="fc-cc-empty"><?= Loc::getMessage('FCO_CC_RULES_EMPTY') ?></div>
<?php else: ?>
<div class="fc-cc-table-wrap">
<table class="fc-cc-table">
<thead>
<tr>
    <?php
    $sortCols = [
        'ID'   => Loc::getMessage('FCO_CC_RULES_COL_ID'),
        'SORT' => Loc::getMessage('FCO_CC_RULES_COL_SORT'),
        'NAME' => Loc::getMessage('FCO_CC_RULES_COL_NAME'),
    ];
    foreach ($sortCols as $field => $label):
        $isActive  = ($by === $field);
        $linkOrd   = ($isActive && $ord === 'ASC') ? 'desc' : 'asc';
        $arrow     = $isActive ? ($ord === 'ASC' ? '&#9650;' : '&#9660;') : '&#9651;';
    ?>
    <th>
        <a href="<?= $selfPage ?>?lang=<?= LANGUAGE_ID ?>&by=<?= strtolower($field) ?>&order=<?= $linkOrd ?>">
            <?= htmlspecialcharsbx($label) ?>
            <span class="fc-cc-sort-arrow<?= $isActive ? ' fc-cc-sort-arrow--active' : '' ?>"><?= $arrow ?></span>
        </a>
    </th>
    <?php endforeach; ?>
    <th><?= Loc::getMessage('FCO_CC_RULES_COL_ACTIVE') ?></th>
    <th><?= Loc::getMessage('FCO_CC_RULES_COL_ENTITY') ?></th>
    <th><?= Loc::getMessage('FCO_CC_RULES_COL_CONDITION') ?></th>
    <th><?= Loc::getMessage('FCO_CC_RULES_COL_ACTION') ?></th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
    $id = (int)$row['ID'];

    $entityLabel = $entityLabels[$row['ENTITY_TYPE']] ?? htmlspecialcharsbx($row['ENTITY_TYPE']);
    if ($row['ENTITY_TYPE'] === 'DEAL' && (int)$row['CATEGORY_ID'] >= 0) {
        $entityLabel .= ' <span style="color:#aab4be;font-size:11px;">#' . (int)$row['CATEGORY_ID'] . '</span>';
    }
    if ($row['ENTITY_TYPE'] === 'DYNAMIC' && $row['SMART_TYPE_ID']) {
        $entityLabel .= ' <span style="color:#aab4be;font-size:11px;">typeId=' . (int)$row['SMART_TYPE_ID'] . '</span>';
    }

    $condLabel = $conditionLabels[$row['CONDITION_TYPE']] ?? htmlspecialcharsbx($row['CONDITION_TYPE']);
    if ($row['CONDITION_FIELD']) {
        $condLabel .= '<br><span style="font-size:11px;color:#aab4be;font-family:monospace;">' . htmlspecialcharsbx($row['CONDITION_FIELD']) . '</span>';
    }

    $actionParts = [];
    if ($row['ACTION_CARD_COLOR']) {
        $c = htmlspecialcharsbx($row['ACTION_CARD_COLOR']);
        $actionParts[] = '<span class="fc-cc-dot" style="background:' . $c . '" title="' . Loc::getMessage('FCO_CC_ACTION_CARD') . ' ' . $c . '"></span>';
    }
    if ($row['ACTION_FIELD_COLOR']) {
        $c = htmlspecialcharsbx($row['ACTION_FIELD_COLOR']);
        $actionParts[] = '<span class="fc-cc-dot fc-cc-dot--round" style="background:' . $c . '" title="' . Loc::getMessage('FCO_CC_ACTION_FIELD') . ' ' . $c . '"></span>';
    }
    $actionHtml = implode(' ', $actionParts);

    $isActive = $row['ACTIVE'] === 'Y';
?>
<tr class="<?= $isActive ? '' : 'fc-cc-inactive' ?>">
    <td><?= $id ?></td>
    <td><?= (int)$row['SORT'] ?></td>
    <td><?= htmlspecialcharsbx($row['NAME']) ?></td>
    <td><span class="fc-cc-badge <?= $isActive ? 'fc-cc-badge--on' : 'fc-cc-badge--off' ?>"><?= $isActive ? Loc::getMessage('FCO_CC_YES') : Loc::getMessage('FCO_CC_NO') ?></span></td>
    <td><?= $entityLabel ?></td>
    <td><?= $condLabel ?></td>
    <td><div style="display:flex;gap:6px;align-items:center;"><?= $actionHtml ?></div></td>
    <td>
        <div class="fc-cc-actions">
            <a href="<?= $editPage ?>?id=<?= $id ?>&lang=<?= LANGUAGE_ID ?>" class="fc-cc-act-btn"><?= Loc::getMessage('FCO_CC_RULES_ACT_EDIT') ?></a>
            <button type="button" class="fc-cc-act-btn"
                onclick="document.querySelector('#fc_cc_toggle_form input[name=id]').value=<?= $id ?>;document.getElementById('fc_cc_toggle_form').submit();">
                <?= $isActive ? Loc::getMessage('FCO_CC_RULES_ACT_DISABLE') : Loc::getMessage('FCO_CC_RULES_ACT_ENABLE') ?>
            </button>
            <button type="button" class="fc-cc-act-btn fc-cc-act-btn--danger"
                onclick="if(confirm('<?= CUtil::JSEscape(Loc::getMessage('FCO_CC_RULES_ACT_DELETE_CONFIRM')) ?>')){document.querySelector('#fc_cc_del_form input[name=id]').value=<?= $id ?>;document.getElementById('fc_cc_del_form').submit();}">
                <?= Loc::getMessage('FCO_CC_RULES_ACT_DELETE') ?>
            </button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<form id="fc_cc_del_form" method="post" action="<?= $selfPage ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
</form>
<form id="fc_cc_toggle_form" method="post" action="<?= $selfPage ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
</form>

<?php
PageHeader::renderClose();
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
