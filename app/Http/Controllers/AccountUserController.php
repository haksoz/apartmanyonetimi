<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountUserController extends Controller
{
    /**
     * Kullanıcı yönetimi listesi.
     */
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            return redirect()->route('current-apartment.select');
        }

        // Pivot'tan gelen üyeler (dışarıdan yönetenler dahil) — aktif ve pasif
        $pivotUsers = $apartment->members()->withPivot('role', 'is_active')->get()->keyBy('id');

        // Accounts tablosundan user_id'si olanları da al (kat maliki/kiracı hesapları)
        $accountUserIds = Account::where('apartment_id', $apartment->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        // Tüm kullanıcı ID'lerini birleştir
        $allUserIds = $pivotUsers->keys()->merge($accountUserIds)->unique();

        // Kullanıcıları çek ve hesaplarını yükle
        $users = User::whereIn('id', $allUserIds)
            ->with(['accounts' => fn ($q) => $q->where('apartment_id', $apartment->id)])
            ->get()
            ->map(function ($user) use ($pivotUsers) {
                // Pivot rolünü ata (yoksa resident varsay)
                $user->pivot = $pivotUsers->has($user->id)
                    ? $pivotUsers[$user->id]->pivot
                    : (object) ['role' => 'member'];
                return $user;
            })
            ->sortByDesc(fn ($u) => $u->pivot->role === 'owner');

        // Hesapları ayrı çek - bir kullanıcının birden fazla hesabı olabilir
        $linkedAccounts = Account::query()
            ->with('unit')
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->whereNotNull('user_id')
            ->get()
            ->groupBy('user_id');

        // Sadece user'a bağlı olmayan boşta hesapları getir (sadece aktif ve tedarikçi olmayan)
        $availableAccounts = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->where('type', '!=', Account::TYPE_SUPPLIER)
            ->with('unit')
            ->get();

        return view('users.index', compact('apartment', 'users', 'linkedAccounts', 'availableAccounts'));
    }

    /**
     * Yeni kullanıcı oluşturma formu.
     */
    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        // Boşta hesapları getir (sadece aktif ve tedarikçi olmayan)
        $availableAccounts = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->where('type', '!=', Account::TYPE_SUPPLIER)
            ->with('unit')
            ->get();

        // Sadece kullanıcı bağlanmamış hesap isimleri (Ad Soyad için öneri)
        $accountNames = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->where('type', '!=', Account::TYPE_SUPPLIER)
            ->whereNotNull('name')
            ->pluck('name')
            ->unique()
            ->values();

        return view('users.create', compact('apartment', 'availableAccounts', 'accountNames'));
    }

    /**
     * E-posta adresine göre sistemde kayıtlı kullanıcı ara (JSON).
     */
    public function lookupEmail(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());
        $email = $request->query('email', '');

        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['found' => false]);
        }

        $isMember = $apartment
            ? $apartment->members()->whereKey($user->id)->exists()
            : false;

        return response()->json([
            'found'     => true,
            'name'      => $user->name,
            'phone'     => $user->phone,
            'is_member' => $isMember,
        ]);
    }

    /**
     * Kullanıcı detay sayfası.
     */
    public function show(CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        $linkedAccounts = Account::where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->with('unit')
            ->get();

        $availableAccounts = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->where('type', '!=', Account::TYPE_SUPPLIER)
            ->with('unit')
            ->get();

        return view('users.show', compact('apartment', 'user', 'linkedAccounts', 'availableAccounts'));
    }

    /**
     * Kullanıcı düzenleme formu.
     */
    public function edit(CurrentApartment $currentApartment, User $user)
    {
        if ($user->isActivated()) {
            return redirect()
                ->route('users.show', $user)
                ->withErrors(['name' => 'Daveti kabul eden kişinin ad, e-posta ve telefonunu kendisi düzenler.']);
        }

        $apartment = $currentApartment->getFor(auth()->user());

        // Kullanıcının bağlı olduğu hesaplar
        $linkedAccounts = Account::where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->with('unit')
            ->get();

        // Bağlanabilecek boşta hesaplar (sadece aktif ve tedarikçi olmayan)
        $availableAccounts = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->where('type', '!=', Account::TYPE_SUPPLIER)
            ->with('unit')
            ->get();

        return view('users.edit', compact('apartment', 'user', 'linkedAccounts', 'availableAccounts'));
    }

    /**
     * Kullanıcı bilgilerini güncelle.
     */
    public function update(Request $request, CurrentApartment $currentApartment, User $user)
    {
        if ($user->isActivated()) {
            return redirect()
                ->route('users.show', $user)
                ->withErrors(['name' => 'Daveti kabul eden kişinin ad, e-posta ve telefonunu kendisi düzenler.']);
        }

        $apartment = $currentApartment->getFor(auth()->user());

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone'            => ['nullable', 'string', 'max:255'],
            'sync_account_info'=> ['nullable', 'boolean'],
        ]);

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        // Bağlı hesapları sadece kullanıcı onaylarsa güncelle
        if (! empty($validated['sync_account_info'])) {
            Account::where('apartment_id', $apartment->id)
                ->where('user_id', $user->id)
                ->update([
                    'name'  => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                ]);
        }

        return redirect()->route('users.show', $user)->with('status', $user->name.' kullanıcısı güncellendi.');
    }

    /**
     * Hesaba mevcut kullanıcıyı e-posta ile bağla (varsa) veya yeni oluştur.
     */
    public function store(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated, $account, $apartment) {
            $user = User::query()->where('email', $validated['email'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Str::random(32),
                    'role' => 'user',
                    'activated_at' => null,
                ]);
            }

            $account->update([
                'user_id' => $user->id,
            ]);

            if (! $apartment->members()->whereKey($user->id)->exists()) {
                $apartment->members()->attach($user->id, ['role' => 'member']);
            }

            return $user;
        });

        return $this->redirectAfterProvision($user, 'Kullanıcı hesaba bağlandı.');
    }

    /**
     * Mevcut kullanıcıya hesap bağla.
     */
    public function attachAccounts(Request $request, CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);

        $validated = $request->validate([
            'account_ids'       => ['required', 'array', 'min:1'],
            'account_ids.*'     => ['integer', 'exists:accounts,id'],
            'sync_account_info' => ['nullable', 'boolean'],
        ]);

        $accounts = Account::where('apartment_id', $apartment->id)
            ->whereNull('user_id')
            ->whereIn('id', $validated['account_ids'])
            ->get();

        $syncInfo = ! empty($validated['sync_account_info']);

        foreach ($accounts as $account) {
            $updateData = ['user_id' => $user->id];

            if ($syncInfo) {
                $updateData['name']  = $user->name;
                $updateData['phone'] = $user->phone;
                $updateData['email'] = $user->email;
            }

            $account->update($updateData);

            if ($account->type === Account::TYPE_TENANT && $account->unit) {
                $account->unit->update(['occupant_account_id' => $account->id]);
            }
        }

        return back()->with('status', 'Hesap(lar) kullanıcıya bağlandı.');
    }

    /**
     * Apartmanda belirtilen kullanıcı dışında başka aktif yönetici var mı?
     */
    private function hasOtherActiveOwner(\App\Models\Apartment $apartment, int $excludeUserId): bool
    {
        return $apartment->members()
            ->where('users.id', '!=', $excludeUserId)
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();
    }

    /**
     * Kullanıcının apartmandaki rolünü güncelle (account üzerinden).
     */
    public function updateRole(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->user_id, 404);

        $validated = $request->validate([
            'role' => ['required', 'in:owner,member'],
        ]);

        if ($validated['role'] === 'member' && ! $this->hasOtherActiveOwner($apartment, $account->user_id)) {
            return back()->withErrors(['role' => 'Apartmanda en az bir yönetici olmalıdır.']);
        }

        $apartment->members()->updateExistingPivot($account->user_id, ['role' => $validated['role']]);

        return back()->with('status', 'Kullanıcı rolü güncellendi.');
    }

    /**
     * Kullanıcının apartmandaki rolünü güncelle (direkt user ID üzerinden).
     */
    public function updateUserRole(Request $request, CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);
        abort_unless($apartment->members()->whereKey($user->id)->exists(), 403);

        $validated = $request->validate([
            'role' => ['required', 'in:owner,member'],
        ]);

        if ($validated['role'] === 'member' && ! $this->hasOtherActiveOwner($apartment, $user->id)) {
            return back()->withErrors(['role' => 'Apartmanda en az bir yönetici olmalıdır.']);
        }

        $apartment->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back()->with('status', 'Kullanıcı rolü güncellendi.');
    }

    /**
     * Kullanıcı şifresini güncelle (yönetici tarafından).
     */
    public function updatePassword(Request $request, CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);
        abort_unless($apartment->members()->whereKey($user->id)->exists(), 403);
        abort_if($user->id === auth()->id(), 403, 'Kendi şifrenizi buradan değiştiremezsiniz.');

        if (! $user->isActivated()) {
            return back()->withErrors(['password' => 'Davetli kullanıcının parolasını davet bağlantısı belirler.']);
        }

        return back()->withErrors(['password' => 'Parola kişiye aittir. Hesabım sayfasından değiştirilir.']);
    }

    /**
     * Yeni kullanıcı oluştur ve seçilen hesaplara bağla.
     */
    public function storeUser(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:255'],
            'account_ids'     => ['nullable', 'array'],
            'account_ids.*'   => ['integer', 'exists:accounts,id'],
            'sync_account_info' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user && $apartment->members()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => $user->name.' zaten bu apartmanın üyesi.'])->withInput();
        }

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Str::random(32),
                'role' => 'user',
                'activated_at' => null,
            ]);
        }

        // Apartmana ekle
        $apartment->members()->attach($user->id, ['role' => 'member']);

        // Seçilen hesaplara bağla
        if (! empty($validated['account_ids'])) {
            $accounts = Account::where('apartment_id', $apartment->id)
                ->whereNull('user_id')
                ->whereIn('id', $validated['account_ids'])
                ->get();

            $syncInfo = ! empty($validated['sync_account_info']);

            foreach ($accounts as $account) {
                $updateData = ['user_id' => $user->id];

                if ($syncInfo) {
                    $updateData['name']  = $user->name;
                    $updateData['phone'] = $user->phone;
                    $updateData['email'] = $user->email;
                }

                $account->update($updateData);

                // Tenant ise unit occupant'ını güncelle
                if ($account->type === Account::TYPE_TENANT && $account->unit) {
                    $account->unit->update(['occupant_account_id' => $account->id]);
                }
            }
        }

        return $this->redirectAfterProvision($user, $user->name.' kullanıcısı oluşturuldu ve hesaplara bağlandı.');
    }

    public function refreshInvite(CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);
        abort_unless($apartment->members()->whereKey($user->id)->exists(), 403);

        if ($user->isActivated()) {
            return back()->withErrors(['invite' => 'Bu kullanıcı daveti zaten kabul etti.']);
        }

        $plain = UserInvitation::issue($user);

        return redirect()
            ->route('users.show', $user)
            ->with('invite_url', route('invite.show', $plain))
            ->with('status', 'Yeni davet bağlantısı hazır.');
    }

    private function redirectAfterProvision(User $user, string $status)
    {
        $redirect = redirect()->route('users.show', $user)->with('status', $status);

        if ($user->isActivated()) {
            return $redirect;
        }

        $plain = UserInvitation::issue($user);

        return $redirect->with('invite_url', route('invite.show', $plain));
    }

    /**
     * Kullanıcının apartman üyeliğini pasife al / aktife al.
     */
    public function toggleActive(Request $request, CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);
        abort_unless($apartment->members()->whereKey($user->id)->exists(), 403);
        abort_if($user->id === auth()->id(), 403, 'Kendi üyeliğinizi pasife alamazsınız.');

        $member = $apartment->members()->withPivot('role', 'is_active')->whereKey($user->id)->first();
        $pivot = $member->pivot;
        $newState = ! (bool) $pivot->is_active;

        if (! $newState && $pivot->role === 'owner' && ! $this->hasOtherActiveOwner($apartment, $user->id)) {
            return back()->withErrors(['active' => 'Apartmanda en az bir aktif yönetici olmalıdır.']);
        }

        $apartment->members()->updateExistingPivot($user->id, ['is_active' => $newState]);

        $message = $newState ? 'Bu binadaki erişim açıldı.' : 'Bu binadaki erişim kapatıldı.';

        return back()->with('status', $message);
    }

    /**
     * Hesaptan kullanıcı bağını kaldır.
     */
    public function destroy(CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);

        $userId = $account->user_id;
        $account->update(['user_id' => null]);

        if ($userId && ! $apartment->members()->whereKey($userId)->exists()) {
            $apartment->members()->attach($userId, ['role' => 'member']);
        }

        return back()->with('status', 'Kullanıcı bağlantısı kaldırıldı.');
    }
}
