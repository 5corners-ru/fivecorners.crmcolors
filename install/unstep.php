<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/index.php');

?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <table class="adm-detail-content-table edit-table" width="100%">
        <tr>
            <td align="center" width="100%" style="text-align:center;">
                <div class="adm-info-message-wrap adm-info-message-green">
                    <div class="adm-info-message">
                        <div class="adm-info-message-title" style="text-align:center;">
                            <?= Loc::getMessage('FCO_CC_UNINSTALL_SUCCESS_TITLE') ?>
                        </div>
                        <div class="adm-info-message-body" style="text-align:center;">
                            <?= Loc::getMessage('FCO_CC_UNINSTALL_SUCCESS_TEXT') ?>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="text-align:center;">
                <a href="/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn">
                    <?= Loc::getMessage('FCO_CC_BACK') ?>
                </a>
            </td>
        </tr>
    </table>
</form>
