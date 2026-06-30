-- REFERENCE ONLY: not loaded by installer (uses ORM RuleTable::getEntity()->createDbTable())
-- Index is created separately via CREATE INDEX in InstallDB()

CREATE TABLE IF NOT EXISTS `b_fc_crmcolors_rule` (
    `ID`                 INT(11)      NOT NULL AUTO_INCREMENT,
    `ACTIVE`             CHAR(1)      NOT NULL DEFAULT 'Y',
    `SORT`               INT(11)      NOT NULL DEFAULT 100,
    `NAME`               VARCHAR(255) NOT NULL DEFAULT '',
    `ENTITY_TYPE`        VARCHAR(32)  NOT NULL DEFAULT '',
    `CATEGORY_ID`        INT(11)      NOT NULL DEFAULT -1,
    `SMART_TYPE_ID`      INT(11)               DEFAULT NULL,
    `CONDITION_TYPE`     VARCHAR(32)  NOT NULL DEFAULT 'FIELD_NOT_EMPTY',
    `CONDITION_FIELD`    VARCHAR(128) NOT NULL DEFAULT '',
    `CONDITION_VALUE`    TEXT                  DEFAULT NULL,
    `CONDITION_DAYS`     INT(11)               DEFAULT NULL,
    `ACTION_CARD_COLOR`  VARCHAR(32)           DEFAULT NULL,
    `ACTION_FIELD_COLOR` VARCHAR(32)           DEFAULT NULL,
    `ACTION_FIELD_CODE`  VARCHAR(128)          DEFAULT NULL,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE INDEX `IDX_FC_CC_ENTITY` ON `b_fc_crmcolors_rule` (`ENTITY_TYPE`, `CATEGORY_ID`, `SMART_TYPE_ID`, `ACTIVE`);
