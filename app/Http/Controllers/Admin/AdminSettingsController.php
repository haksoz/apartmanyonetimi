<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', ['showTestCustomers' => (bool) SystemSetting::query()->value('show_test_customers')]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate(['show_test_customers' => ['required', 'boolean']]);
        $settings = SystemSetting::query()->firstOrCreate([], ['trial_duration_months' => 2]);
        $settings->show_test_customers = (bool) $validated['show_test_customers'];
        $settings->save();

        return redirect()->route('admin.settings.index')->with('status', 'Test müşterilerinin görünürlüğü güncellendi.');
    }
}
