<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $adminUsers = User::query()
            ->whereIn('role', User::adminRoles())
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.admin-users.index', compact('adminUsers', 'search'));
    }

    public function create()
    {
        return view('admin.admin-users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(User::adminRoles())],
            'password' => ['required', Password::defaults()],
        ]);

        User::create($validated);

        return redirect()->route('admin.admin-users.index')->with('status', 'Admin kullanıcısı oluşturuldu.');
    }

    public function edit(User $adminUser)
    {
        abort_if(! $adminUser->isAdminPanelUser(), 404);

        return view('admin.admin-users.edit', compact('adminUser'));
    }

    public function update(Request $request, User $adminUser)
    {
        abort_if(! $adminUser->isAdminPanelUser(), 404);
        abort_if($adminUser->id === $request->user()?->id && $request->input('role') !== User::ROLE_SUPER_ADMIN, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($adminUser->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(User::adminRoles())],
            'password' => ['nullable', Password::defaults()],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $adminUser->update($validated);

        return redirect()->route('admin.admin-users.index')->with('status', 'Admin kullanıcısı güncellendi.');
    }

    public function destroy(User $adminUser)
    {
        abort_if(! $adminUser->isAdminPanelUser(), 404);
        abort_if($adminUser->id === auth()->id(), 403, 'Kendi hesabınızı silemezsiniz.');

        $adminUser->delete();

        return redirect()->route('admin.admin-users.index')->with('status', 'Admin kullanıcısı silindi.');
    }
}
