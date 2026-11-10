const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function picker() {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            value: '', handlers: {}, attributes: {}, classList: { toggle() {}, remove() {}, add() {} },
            addEventListener(name, fn) { this.handlers[name] = fn; },
            dispatchEvent(event) { this.lastEvent = event; this.handlers[event.type]?.(event); },
            setAttribute(name, value) { this.attributes[name] = value; },
            reportValidity() { return Number(this.value) >= 1 && Number(this.value) <= 2026; },
            stepDown() { this.value = Number(this.value) - 1; },
            stepUp() { this.value = Number(this.value) + 1; },
            showModal() { this.open = true; }, close() { this.open = false; },
        });
        return elements.get(id);
    };
    const buttons = Array.from({ length: 12 }, (_, index) => {
        const button = element(`month-${index + 1}`);
        button.dataset = { boardMonth: String(index + 1) };
        return button;
    });
    element('board-period-dialog').querySelectorAll = () => buttons;
    element('board-year').value = 2026;
    element('board-month').value = 4;
    element('board-month-end').value = '2026-04';
    let source = fs.readFileSync('resources/views/reports/_board-period-picker.blade.php', 'utf8').split('<script>')[1].split('</script>')[0];
    source = source.replace('@json($trMonths)', JSON.stringify({ 1: 'Ocak', 2: 'Şubat', 3: 'Mart', 4: 'Nisan', 5: 'Mayıs', 6: 'Haziran', 7: 'Temmuz', 8: 'Ağustos', 9: 'Eylül', 10: 'Ekim', 11: 'Kasım', 12: 'Aralık' }))
        .replace("@json(['year' => now()->year, 'month' => now()->month])", '{"year":2026,"month":11}');
    vm.runInNewContext(source, {
        document: { getElementById: element, querySelectorAll: () => [] },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    });
    const click = id => element(id).handlers.click({});
    click('board-period-open');
    return { element, click };
}

test('two clicks highlight the inclusive range and apply it without changing the form prematurely', () => {
    const { element, click } = picker();
    click('month-4');
    assert.equal(element('month-4').attributes['data-selection'], 'pending');
    click('month-7');
    assert.equal(element('month-4').attributes['data-selection'], 'complete');
    assert.equal(element('month-5').attributes['data-selection'], 'complete');
    assert.equal(element('month-7').attributes['data-selection'], 'complete');
    assert.equal(element('month-8').attributes['data-selection'], 'none');
    assert.equal(element('month-5').attributes['aria-pressed'], 'true');
    assert.equal(element('month-8').attributes['aria-pressed'], 'false');
    assert.equal(element('board-month-end').value, '2026-04');
    click('board-picker-apply');
    assert.equal(element('board-month-end').value, '2026-07');
    assert.equal(element('board-period-text').textContent, 'Nisan 2026 – Temmuz 2026');
    assert.equal(element('board-period-dialog').open, false);
});

test('clicking the same month twice visibly completes the selection and a third click starts over', () => {
    const { element, click } = picker();
    click('month-9');
    assert.equal(element('month-9').attributes['data-selection'], 'pending');
    assert.match(element('board-picker-selection').textContent, /Bitiş ayını seçin/);
    click('month-9');
    assert.equal(element('month-9').attributes['data-selection'], 'complete');
    assert.match(element('board-picker-selection').textContent, /Seçim tamamlandı/);
    click('month-10');
    assert.equal(element('month-9').attributes['data-selection'], 'none');
    assert.equal(element('month-10').attributes['data-selection'], 'pending');
});

test('same-month, reversed selection, cancellation and current month work', () => {
    const { element, click } = picker();
    click('month-7'); click('month-4'); click('board-picker-apply');
    assert.equal(element('board-month').value, 4);
    assert.equal(element('board-month-end').value, '2026-07');
    click('board-period-open'); click('month-2'); click('board-period-close');
    assert.equal(element('board-month').value, 4);
    click('board-period-open'); click('month-5'); click('month-5'); click('board-picker-apply');
    assert.equal(element('board-month-end').value, '2026-05');
    click('board-period-open'); click('board-picker-current');
    assert.equal(element('board-month').value, 11);
    assert.equal(element('board-month-end').value, '2026-11');
});

test('year navigation retains the start and future months are disabled', () => {
    const { element, click } = picker();
    assert.equal(element('month-12').disabled, true);
    click('board-year-previous'); click('month-12');
    click('board-year-next'); click('month-2'); click('board-picker-apply');
    assert.equal(element('board-year').value, 2025);
    assert.equal(element('board-month').value, 12);
    assert.equal(element('board-month-end').value, '2026-02');
});
