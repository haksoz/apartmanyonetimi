const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const template = fs.readFileSync(path.join(__dirname, '../../resources/views/expenses/_relation-field.blade.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

function picker({ preserve = false, initial = 'unassigned', categoryDefault = 'aidat' } = {}) {
    const choices = ['unassigned', 'aidat', 'malik_payi'].map(value => ({ value, checked: value === initial }));
    const listeners = {};
    const category = {
        selectedOptions: [{ dataset: { expenseRelation: categoryDefault } }],
        addEventListener: (type, listener) => { listeners[type] = listener; },
    };
    vm.runInNewContext(script.replace(/@json\([^\n]*\)/, JSON.stringify(preserve)), {
        document: { getElementById: () => category, querySelectorAll: () => choices },
    });
    return {
        value: () => choices.find(choice => choice.checked)?.value,
        choose: value => choices.forEach(choice => { choice.checked = choice.value === value; }),
        changeCategory: value => { category.selectedOptions[0].dataset.expenseRelation = value; listeners.change(); },
    };
}

test('new expense selects the category default and permits a manual override', () => {
    const form = picker();
    assert.equal(form.value(), 'aidat');
    form.choose('malik_payi');
    assert.equal(form.value(), 'malik_payi');
    form.changeCategory('unassigned');
    assert.equal(form.value(), 'unassigned');
});

test('editing an expense preserves its saved relationship until category changes', () => {
    const form = picker({ preserve: true, initial: 'malik_payi' });
    assert.equal(form.value(), 'malik_payi');
    form.changeCategory('aidat');
    assert.equal(form.value(), 'aidat');
});

test('validation return preserves an explicit unassigned choice', () => {
    assert.equal(picker({ preserve: true }).value(), 'unassigned');
});

test('category without a defined default selects unassigned', () => {
    assert.equal(picker({ categoryDefault: null }).value(), 'unassigned');
    assert.equal(picker({ categoryDefault: '' }).value(), 'unassigned');
});
