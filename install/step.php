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
                            <?= Loc::getMessage('FCO_CC_INSTALL_SUCCESS_TITLE') ?>
                        </div>
                        <div class="adm-info-message-body" style="text-align:center;">
                            <?= Loc::getMessage('FCO_CC_INSTALL_SUCCESS_TEXT') ?>
                            <br><br>
                            <a href="/local/admin/fivecorners_crmcolors_rules.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn">
                                <?= Loc::getMessage('FCO_CC_INSTALL_GO_SETTINGS') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="text-align:center;">
                <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
                <input type="hidden" name="id" value="fivecorners.crmcolors">
                <input type="hidden" name="install" value="Y">
                <input type="submit" name="inst" value="<?= Loc::getMessage('FCO_CC_BACK') ?>">
            </td>
        </tr>
    </table>
</form>
