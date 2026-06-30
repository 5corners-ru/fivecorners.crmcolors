'use strict';

/**
 * 5 УГЛОВ. Цветные акценты в CRM
 * Раскраска карточек Канбана и полей детальной карточки по правилам.
 */
(function () {
    var cfg   = window.FcCrmColorsConfig || {};
    var rules = cfg.rules || [];
    if (!rules.length) return;

    var pageType     = cfg.pageType    || 'DETAIL';
    var currentVals  = cfg.currentValues || {};

    // =====================================================================
    // ОБЩИЕ УТИЛИТЫ
    // =====================================================================

    function isValueEmpty(value) {
        if (value === null || value === undefined) return true;
        if (Array.isArray(value)) {
            return value.filter(function (v) { return v !== null && String(v).trim() !== ''; }).length === 0;
        }
        return String(value).trim() === '';
    }

    function isDateApproaching(dateValue, days) {
        if (!dateValue) return false;
        var d = new Date(String(dateValue).replace(' ', 'T'));
        if (isNaN(d.getTime())) return false;
        var diffDays = (d.getTime() - Date.now()) / 86400000;
        return diffDays <= days; // включая просроченные (diffDays < 0)
    }

    function conditionMatches(rule, fieldValue) {
        var ct  = rule.conditionType;
        var cv  = rule.conditionValue;

        if (ct === 'FIELD_EMPTY')     return isValueEmpty(fieldValue);
        if (ct === 'FIELD_NOT_EMPTY') return !isValueEmpty(fieldValue);

        if (ct === 'DATE_APPROACHING') {
            return isDateApproaching(fieldValue, rule.conditionDays != null ? rule.conditionDays : 3);
        }

        if (ct === 'FIELD_EQUALS' || ct === 'STAGE_EQUALS') {
            if (isValueEmpty(fieldValue) || cv === null || cv === undefined) return false;
            if (Array.isArray(fieldValue)) {
                return fieldValue.some(function (v) { return String(v) === String(cv); });
            }
            return String(fieldValue) === String(cv);
        }

        return false;
    }

    // Устанавливает background-color на элемент, запоминая оригинал
    function setElColor(el, color) {
        if (!el) return;
        if (!el.hasAttribute('data-fc-cc-colored')) {
            el.setAttribute('data-fc-cc-orig-bg', el.style.backgroundColor || '');
            el.setAttribute('data-fc-cc-colored', '1');
        }
        el.style.backgroundColor = color;
    }

    // Сбрасывает все ранее установленные модулем цвета в документе (или в контейнере)
    function resetColors(root) {
        var scope = root || document;
        var colored = scope.querySelectorAll('[data-fc-cc-colored]');
        for (var i = 0; i < colored.length; i++) {
            colored[i].style.backgroundColor = colored[i].getAttribute('data-fc-cc-orig-bg') || '';
            colored[i].removeAttribute('data-fc-cc-colored');
            colored[i].removeAttribute('data-fc-cc-orig-bg');
        }
    }

    // =====================================================================
    // ДЕТАЛЬНАЯ КАРТОЧКА
    // =====================================================================

    var _editor    = null;
    var _applyTimer = null;

    function getEntityEditor() {
        if (typeof BX === 'undefined' || !BX.Crm || !BX.Crm.EntityEditor) return null;
        var EE = BX.Crm.EntityEditor;
        if (EE.getAll) {
            var arr = EE.getAll();
            if (arr && arr.length) return arr[0];
        }
        if (EE.getDefault) {
            var def = EE.getDefault();
            if (def) return def;
        }
        if (EE.defaultInstance) return EE.defaultInstance;
        if (EE.items) {
            var keys = Object.keys(EE.items);
            if (keys.length) return EE.items[keys[0]];
        }
        var container = document.querySelector('[data-bx-crm-editor]') || document.getElementById('CrmEntityEditor');
        return container ? container.bxComponent : null;
    }

    function getFieldValueEditor(fieldCode, editor) {
        var val = null;

        if (editor) {
            try {
                var control = editor.getControlById
                    ? editor.getControlById(fieldCode)
                    : (editor.getControl ? editor.getControl(fieldCode) : null);

                if (control) {
                    // DOM wrapper — самое актуальное значение
                    if (control.getWrapper) {
                        var wrapper = control.getWrapper();
                        if (wrapper) {
                            var uiSel = wrapper.classList.contains('main-ui-select')
                                ? wrapper
                                : wrapper.querySelector('.main-ui-select');
                            if (uiSel) {
                                val = readUiSelectValue(uiSel);
                            }
                            if (!val) {
                                var inp = wrapper.querySelector('input[name="' + fieldCode + '"]')
                                       || wrapper.querySelector('input[data-name="' + fieldCode + '"]');
                                if (inp && inp.value) val = inp.value;
                            }
                        }
                    }
                    // control.getValue()
                    if (!val) {
                        var raw = control.getValue ? control.getValue() : null;
                        if (raw !== null && raw !== undefined) {
                            if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
                                if (raw.IS_EMPTY === true) raw = null;
                                else if (raw.hasOwnProperty('VALUE')) raw = raw.VALUE;
                                else raw = null;
                            }
                            if (raw !== null && raw !== '' && raw !== undefined) val = raw;
                        }
                    }
                }
            } catch (e) {}
        }

        // DOM документа
        if (!val) val = getFieldValueDOM(fieldCode);

        // Начальные значения с сервера (PHP)
        if (!val && currentVals.hasOwnProperty(fieldCode)) {
            val = currentVals[fieldCode];
        }

        return val;
    }

    function readUiSelectValue(uiSelect) {
        var dv = uiSelect.getAttribute('data-value');
        if (dv) {
            try {
                var p = JSON.parse(dv);
                if (p && p.VALUE !== undefined) return p.VALUE !== '' ? String(p.VALUE) : '';
            } catch (e) {}
        }
        var ns = uiSelect.querySelector('.main-ui-select-name');
        if (ns) {
            var name = ns.textContent.trim();
            var di = uiSelect.getAttribute('data-items');
            if (di && name) {
                try {
                    var items = JSON.parse(di);
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].NAME === name && items[i].VALUE !== '' && items[i].VALUE !== undefined) {
                            return String(items[i].VALUE);
                        }
                    }
                } catch (e) {}
            }
        }
        return '';
    }

    function getFieldValueDOM(fieldCode) {
        var uiSel = document.querySelector('[data-name="' + fieldCode + '"].main-ui-select')
                 || document.querySelector('[data-cid="' + fieldCode + '"].main-ui-select')
                 || document.querySelector('[data-cid="' + fieldCode + '"] .main-ui-select');
        if (uiSel) return readUiSelectValue(uiSel) || '';

        var el = document.querySelector('[data-cid="' + fieldCode + '"] select')
              || document.querySelector('select[name="' + fieldCode + '"]')
              || document.querySelector('input[name="' + fieldCode + '"]');
        if (el) return el.value || null;

        return null;
    }

    function findCardContainer(editor) {
        if (editor) {
            try {
                var w = editor.getContainer ? editor.getContainer()
                      : editor.getWrapper   ? editor.getWrapper() : null;
                if (w) return w;
            } catch (e) {}
        }
        return document.querySelector(
            '.ui-entity-editor-page, .crm-entity-card-container, .crm-entity-card-layout, .crm-entity-card'
        );
    }

    function findFieldElement(fieldCode, editor) {
        if (editor) {
            try {
                var control = editor.getControlById
                    ? editor.getControlById(fieldCode)
                    : (editor.getControl ? editor.getControl(fieldCode) : null);
                if (control && control.getWrapper) return control.getWrapper();
            } catch (e) {}
        }
        return document.querySelector('[data-cid="' + fieldCode + '"]')
            || document.querySelector('[data-name="' + fieldCode + '"]');
    }

    // Возвращает true, если поле сейчас в режиме редактирования (курсор внутри)
    function isFieldEditing(fieldEl) {
        if (!fieldEl) return false;
        return fieldEl.contains(document.activeElement);
    }

    function applyAllColors() {
        clearTimeout(_applyTimer);
        _applyTimer = setTimeout(function () {
            var editor = _editor || getEntityEditor();
            resetColors();
            rules.forEach(function (rule) {
                var fv = getFieldValueEditor(rule.conditionField, editor);
                if (!conditionMatches(rule, fv)) return;

                // actionCardColor в детальной карточке не применяется — только в Канбане
                if (rule.actionFieldColor) {
                    var fc = rule.actionFieldCode || rule.conditionField;
                    var fieldEl = findFieldElement(fc, editor);
                    if (!isFieldEditing(fieldEl)) {
                        setElColor(fieldEl, rule.actionFieldColor);
                    }
                }
            });
        }, 200);
    }

    function bindBxEvents() {
        if (typeof BX === 'undefined') return;
        // onControlChange/onControlChanged убраны — они срабатывают при каждом нажатии клавиши
        BX.addCustomEvent('BX.Crm.EntityEditor:onLayout', function () { applyAllColors(); });
        BX.addCustomEvent('BX.Crm.EntityEditor:onModeChange', function () {
            setTimeout(applyAllColors, 100);
            setTimeout(applyAllColors, 500);
        });
        BX.addCustomEvent('BX.Crm.EntityEditor:onSave', function () {
            setTimeout(applyAllColors, 300);
            setTimeout(applyAllColors, 700);
        });
        document.addEventListener('click', function () {
            setTimeout(applyAllColors, 250);
            setTimeout(applyAllColors, 700);
        }, true);
    }

    function observeUiSelects() {
        if (!window.MutationObserver) return;
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].attributeName === 'data-value') {
                    applyAllColors();
                    return;
                }
            }
        });
        function attach() {
            document.querySelectorAll('.main-ui-select').forEach(function (el) {
                try { observer.observe(el, { attributes: true, attributeFilter: ['data-value'] }); } catch (e) {}
            });
        }
        attach();
        setTimeout(attach, 1000);
        setTimeout(attach, 2000);
    }

    function waitAndInit(attempt) {
        attempt = attempt || 0;
        if (attempt > 15) return;
        _editor = getEntityEditor();
        if (!_editor) {
            setTimeout(function () { waitAndInit(attempt + 1); }, 250);
            return;
        }
        bindBxEvents();
        applyAllColors();
        observeUiSelects();
        setTimeout(applyAllColors, 1000);
        setTimeout(applyAllColors, 2000);
    }

    // =====================================================================
    // КАНБАН
    // =====================================================================

    var _gridTmp = null;

    var _kanbanDomAttrList = ['data-cid', 'data-id', 'data-field-id', 'data-entity-field-name',
                              'data-entity-field-code', 'data-code', 'data-name', 'data-field'];

    function _findKanbanFieldEl(container, fieldCode) {
        for (var a = 0; a < _kanbanDomAttrList.length; a++) {
            var el = container.querySelector('[' + _kanbanDomAttrList[a] + '="' + fieldCode + '"]');
            if (el) return el;
        }
        return null;
    }

    function getKanbanFieldValue(fields, item, fieldCode) {
        // Стадия хранится отдельно
        if (fieldCode === 'STAGE_ID' || fieldCode === 'STATUS_ID') {
            return (item.options && item.options.data && item.options.data.stageId) || null;
        }
        // Array format: [{code, value}, ...]
        if (Array.isArray(fields)) {
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                if (f && (f.code === fieldCode || f.CODE === fieldCode)) {
                    return f.value !== undefined ? f.value : (f.VALUE !== undefined ? f.VALUE : null);
                }
            }
        }
        // Hash/object format: {UF_CRM_...: value_or_object, ...}
        if (fields && typeof fields === 'object' && !Array.isArray(fields) && fields[fieldCode] !== undefined) {
            var fv = fields[fieldCode];
            if (fv && typeof fv === 'object' && !Array.isArray(fv)) {
                return fv.value !== undefined ? fv.value : (fv.VALUE !== undefined ? fv.VALUE : null);
            }
            return fv;
        }
        // Прямые свойства item.options.data
        var d = item.options && item.options.data;
        if (d && d[fieldCode] !== undefined) return d[fieldCode];
        // DOM fallback: поиск по атрибутам во всём контейнере карточки
        var root = item.container || item.el || null;
        if (root) {
            var domEl = _findKanbanFieldEl(root, fieldCode);
            if (domEl) {
                var text = (domEl.textContent || domEl.innerText || '').trim();
                return text || null;
            }
        }
        return null;
    }

    function getItemFieldsWrapper(item) {
        if (item.fieldsWrapper) return item.fieldsWrapper;
        var root = item.container || item.el || item.node || null;
        if (!root) return null;
        return root.querySelector('.crm-kanban-item-fields, .kanban-item-fields, .kanban-item-body') || null;
    }

    function applyKanbanItemColors(item) {
        if (!item || !item.options || !item.options.data) return;

        var itemId = parseInt(item.options.data.id || item.options.data.ID || 0, 10);
        if (!itemId) return;

        var fields = item.options.data.fields || [];
        var fw = getItemFieldsWrapper(item);

        if (fw) resetColors(fw.parentElement || fw);

        rules.forEach(function (rule) {
            var fv = getKanbanFieldValue(fields, item, rule.conditionField);

            if (rule.conditionType === 'STAGE_EQUALS') {
                fv = (item.options.data.stageId != null) ? String(item.options.data.stageId) : null;
            }

            if (!conditionMatches(rule, fv)) return;

            if (rule.actionCardColor && fw) {
                setElColor(fw.parentElement || fw, rule.actionCardColor);
            }

            if (rule.actionFieldColor) {
                var targetCode = rule.actionFieldCode || rule.conditionField;
                colorKanbanField(item, targetCode, rule.actionFieldColor);
            }
        });
    }

    function _stripHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').trim();
    }

    function colorKanbanField(item, fieldCode, color) {
        // Стратегия 1: атрибутный поиск во всём контейнере
        var root = item.container || item.el || null;
        if (root) {
            var el = _findKanbanFieldEl(root, fieldCode);
            if (el) { setElColor(el, color); return; }
        }

        var fw = getItemFieldsWrapper(item);
        if (!fw || !fw.children.length) return;

        // Стратегия 2: атрибутный поиск внутри fw
        var el2 = _findKanbanFieldEl(fw, fieldCode);
        if (el2) { setElColor(el2, color); return; }

        var fields = (item.options && item.options.data && item.options.data.fields) || [];

        // Стратегия 3: поиск по тексту значения в fw.children
        // Bitrix отдаёт value как HTML-строку когда html:true
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            if (!f) continue;
            var fCode = f.code || f.CODE || '';
            if (fCode !== fieldCode) continue;

            var rawVal = f.value !== undefined ? f.value : (f.VALUE !== undefined ? f.VALUE : null);
            if (rawVal === null || rawVal === undefined) break;

            var searchText = String(rawVal);
            if (/</.test(searchText)) searchText = _stripHtml(searchText);
            searchText = searchText.trim();
            if (!searchText) break;

            for (var j = 0; j < fw.children.length; j++) {
                var cText = (fw.children[j].textContent || fw.children[j].innerText || '').trim();
                if (cText.indexOf(searchText) !== -1) {
                    setElColor(fw.children[j], color);
                    return;
                }
            }
            break;
        }

        // Стратегия 4: index-based со смещением
        // Стандартные поля (TITLE, OPPORTUNITY, DATE_CREATE) рендерятся вне fw —
        // их кол-во = data.fields.length - fw.children.length
        if (!Array.isArray(fields)) return;
        var numStd = fields.length - fw.children.length;
        if (numStd < 0) return;
        for (var k = 0; k < fields.length; k++) {
            var fk = fields[k];
            if (!fk) continue;
            var fc = fk.code || fk.CODE || '';
            if (fc !== fieldCode) continue;
            var fwIdx = k - numStd;
            if (fwIdx >= 0 && fw.children[fwIdx]) {
                setElColor(fw.children[fwIdx], color);
                return;
            }
        }
    }

    function updateKanbanColumn(column) {
        if (!column || !column.items) return;
        for (var i in column.items) {
            if (Object.prototype.hasOwnProperty.call(column.items, i)) {
                applyKanbanItemColors(column.items[i]);
            }
        }
    }

    function kanbanGridHandler(grid) {
        _gridTmp = grid;
        if (grid && grid.columns) {
            for (var i in grid.columns) {
                if (Object.prototype.hasOwnProperty.call(grid.columns, i)) {
                    updateKanbanColumn(grid.columns[i]);
                }
            }
        }
    }

    function columnLoadAsync() {
        setTimeout(function () {
            if (_gridTmp) kanbanGridHandler(_gridTmp);
        }, 500);
    }

    // =====================================================================
    // DOM-ФОЛБЭК ДЛЯ КАНБАНА (new main-kanban-* structure)
    // Срабатывает когда Kanban.Grid:onRender уже отработал до загрузки этого скрипта.
    // =====================================================================

    function applyKanbanColorsDom() {
        rules.forEach(function (rule) {
            if (!rule.actionCardColor) return;

            if (rule.conditionType === 'STAGE_EQUALS') {
                // Колонка DOM: .main-kanban-column-body[data-type="column"][data-id="STAGE_VALUE"]
                var colBody = document.querySelector(
                    '.main-kanban-column-body[data-type="column"][data-id="' + rule.conditionValue + '"]'
                );
                if (!colBody) return;
                var items = colBody.querySelectorAll('.main-kanban-item');
                items.forEach(function (itemEl) {
                    // Внутренний элемент карточки — .crm-kanban-item
                    var card = itemEl.querySelector('.crm-kanban-item') || itemEl;
                    setElColor(card, rule.actionCardColor);
                });
            }
        });
    }

    function applyKanbanColorsDomDelayed() {
        // Несколько волн — чтобы поймать и быструю и медленную перерисовку после AJAX
        setTimeout(applyKanbanColorsDom, 100);
        setTimeout(applyKanbanColorsDom, 500);
        setTimeout(applyKanbanColorsDom, 1200);
        setTimeout(applyKanbanColorsDom, 2500);
    }

    function setupKanbanDomObserver() {
        var kanbanRoot = document.querySelector('.main-kanban, #crm_kanban');
        if (kanbanRoot && window.MutationObserver) {
            var obs = new MutationObserver(function () {
                clearTimeout(_kanbanObsTimer);
                // Только childList — избегаем цикла через attribute-наблюдение
                _kanbanObsTimer = setTimeout(applyKanbanColorsDomDelayed, 100);
            });
            obs.observe(kanbanRoot, { childList: true, subtree: true });
        }

        // Ловим конец drag-drop по pointer/mouse up на всём документе
        document.addEventListener('pointerup', function () {
            setTimeout(applyKanbanColorsDom, 300);
            setTimeout(applyKanbanColorsDom, 900);
            setTimeout(applyKanbanColorsDom, 2200);
        });

        // Страховочная сетка: перекраска каждые 2 сек пока страница видима
        setInterval(applyKanbanColorsDom, 2000);
    }

    function patchKanbanGridMethods() {
        if (typeof BX === 'undefined' || !BX.Kanban || !BX.Kanban.Grid || !BX.Kanban.Grid.prototype) return;
        var proto = BX.Kanban.Grid.prototype;

        // moveItem срабатывает при drag-drop и при смене стадии через кнопку
        var origMove = proto.moveItem;
        proto.moveItem = function () {
            var ret = origMove.apply(this, arguments);
            applyKanbanColorsDomDelayed();
            return ret;
        };

        var origMoveItems = proto.moveItems;
        if (origMoveItems) {
            proto.moveItems = function () {
                var ret = origMoveItems.apply(this, arguments);
                applyKanbanColorsDomDelayed();
                return ret;
            };
        }
    }

    var _kanbanObsTimer = null;

    // =====================================================================
    // ИНИЦИАЛИЗАЦИЯ
    // =====================================================================

    if (typeof BX !== 'undefined') {
        // Канбан-события (работают на любой странице с Канбаном)
        BX.addCustomEvent('Kanban.Grid:onRender',       BX.delegate(kanbanGridHandler));
        BX.addCustomEvent('Kanban.Grid:onFirstRender',  BX.delegate(kanbanGridHandler));
        BX.addCustomEvent('Kanban.Grid:onColumnLoadAsync', BX.delegate(columnLoadAsync));
        BX.addCustomEvent('Kanban.Column:render',       BX.delegate(columnLoadAsync));
    }

    // Канбан — DOM-фолбэк для случая когда события уже отработали
    if (pageType === 'KANBAN') {
        patchKanbanGridMethods();
        applyKanbanColorsDom();
        setTimeout(applyKanbanColorsDom, 500);
        setTimeout(applyKanbanColorsDom, 1500);
        setupKanbanDomObserver();
    }

    // Детальная карточка — только если pageType=DETAIL
    if (pageType === 'DETAIL') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { waitAndInit(0); });
        } else {
            waitAndInit(0);
        }
    }

})();
