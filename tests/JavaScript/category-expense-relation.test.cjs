const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const template = fs.readFileSync(path.join(__dirname, '../../resources/views/expenses/_relation-field.blade.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

function picker({ initial = 'aidat', categoryId = '1', readyState = 'complete' } = {}) {
    const choices = ['unassigned', 'aidat', 'malik_payi'].map(value => ({
        value, checked: value === initial, addEventListener(type, listener) { this.change = listener; },
    }));
    const category = {
        value: categoryId,
        options: [
            { value: '', dataset: {} },
            { value: '1', dataset: { expenseRelations: 'aidat' } },
            { value: '2', dataset: { expenseRelations: 'malik_payi' } },
            { value: '3', dataset: { expenseRelations: 'aidat,malik_payi' } },
            { value: '4', dataset: { expenseRelations: '' } },
        ],
        replaceChildren(...options) { this.options = options; },
    };
    const summary = { textContent: '' };
    let onReady;
    vm.runInNewContext(script, {
        document: {
            readyState,
            getElementById: id => id === 'category_id' ? category : summary,
            querySelectorAll: () => choices,
            addEventListener: (event, listener) => { onReady = listener; },
        },
    });
    return {
        value: () => choices.find(choice => choice.checked)?.value,
        category: () => category.value,
        summary: () => summary.textContent,
        options: () => category.options.map(option => option.value),
        selectType: value => {
            choices.forEach(choice => { choice.checked = choice.value === value; });
            choices.find(choice => choice.checked).change();
        },
        selectCategory: value => { category.value = value; },
        ready: () => onReady(),
    };
}

test('category selection never changes the user-selected expense type', () => {
    const form = picker();
    form.selectCategory('3');
    assert.equal(form.value(), 'aidat');
    assert.deepEqual(form.options(), ['', '1', '3']);
});

test('changing type clears an incompatible category', () => {
    const form = picker();
    form.selectType('malik_payi');
    assert.equal(form.value(), 'malik_payi');
    assert.equal(form.category(), '');
    assert.deepEqual(form.options(), ['', '2', '3']);
});

test('changing type preserves a category that supports both types', () => {
    const form = picker({ categoryId: '3' });
    form.selectType('malik_payi');
    assert.equal(form.category(), '3');
});

test('historical category is preserved on load but not after an incompatible type change', () => {
    const form = picker({ initial: 'malik_payi', categoryId: '4' });
    assert.equal(form.category(), '4');
    assert.ok(form.options().includes('4'));
    form.selectType('aidat');
    assert.equal(form.category(), '');
    assert.ok(!form.options().includes('4'));
});

test('unassigned historical expenses keep their category and all available options', () => {
    const form = picker({ initial: 'unassigned', categoryId: '4' });
    assert.equal(form.value(), 'unassigned');
    assert.equal(form.category(), '4');
    assert.deepEqual(form.options(), ['', '1', '2', '3', '4']);
});

test('initialization waits until the category field below the type selection exists', () => {
    const form = picker({ readyState: 'loading' });
    form.ready();
    assert.deepEqual(form.options(), ['', '1', '3']);
});

test('type summary reflects the user change', () => {
    const form = picker();
    form.selectType('malik_payi');
    assert.equal(form.summary(), 'Kat Maliki Gideri');
    form.selectType('unassigned');
    assert.equal(form.summary(), 'Henüz Belirlenmedi');
});
