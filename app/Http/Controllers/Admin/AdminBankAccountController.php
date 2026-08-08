<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::ordered()->paginate(20);
        return view('admin.bank-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('admin.bank-accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        BankAccount::create($validated);

        return redirect()->route('admin.bank-accounts.index')->with('status', 'Banka hesabı eklendi.');
    }

    public function edit(BankAccount $bankAccount)
    {
        return view('admin.bank-accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate($this->rules($bankAccount->id));

        $bankAccount->update($validated);

        return redirect()->route('admin.bank-accounts.index')->with('status', 'Banka hesabı güncellendi.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return redirect()->route('admin.bank-accounts.index')->with('status', 'Banka hesabı silindi.');
    }

    private function rules(?int $excludeId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'iban' => ['required', 'string', 'max:255', Rule::unique('bank_accounts')->ignore($excludeId)],
            'currency' => ['required', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
