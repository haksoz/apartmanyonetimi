<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $setting = SystemSetting::firstOrCreate(['id' => 1]);
        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.settings.index', compact('setting', 'packages'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'trial_package_id' => ['nullable', 'exists:packages,id'],
            'trial_duration_months' => ['required', 'integer', 'min:1', 'max:12'],
            'fallback_package_id' => ['nullable', 'exists:packages,id'],
        ]);

        $setting = SystemSetting::firstOrCreate(['id' => 1]);
        $setting->update($validated);

        return back()->with('success', 'Ayarlar başarıyla güncellendi.');
    }
}
