<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
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

        // Pivot'tan gelen üyeler (dışarıdan yönetenler dahil)
        $pivotUsers = $apartment->members()->get()->keyBy('id');

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
                    : (object) ['role' => 'resident'];
                return $user;
            })
            ->sortByDesc(fn ($u) => $u->pivot->role === 'owner');

        // Hesapları ayrı çek (hızlı lookup için)
        $linkedAccounts = Account::query()
            ->with('unit')
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->whereNotNull('user_id')
            ->get()
            ->keyBy('user_id');

        // Kullanıcıya dönüştürülebilir hesaplar (user_id = null, bilgileri dolu)
        $availableAccounts = Account::query()
            ->with('unit')
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->whereNull('user_id')
            ->whereNotNull('name')
            ->orderBy('unit_id')
            ->get();

        return view('users.index', compact('apartment', 'users', 'linkedAccounts', 'availableAccounts'));
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
        ]);

        DB::transaction(function () use ($validated, $account, $apartment) {
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $validated['name'],
                    'password' => bcrypt(Str::random(16)),
                    'role'     => 'user',
                ]
            );

            $account->update(['user_id' => $user->id]);

            if (! $apartment->members()->whereKey($user->id)->exists()) {
                $apartment->members()->attach($user->id, ['role' => 'resident']);
            }
        });

        return back()->with('status', 'Kullanıcı hesaba bağlandı.');
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
            'role' => ['required', 'in:owner,resident'],
        ]);

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
            'role' => ['required', 'in:owner,resident'],
        ]);

        $apartment->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back()->with('status', 'Kullanıcı rolü güncellendi.');
    }

    /**
     * Hesaplardan kullanıcı oluştur (bir veya çoklu).
     */
    public function invite(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);

        $validated = $request->validate([
            'accounts'   => ['required', 'array', 'min:1'],
            'accounts.*' => ['required', 'exists:accounts,id'],
        ]);

        $created = 0;

        DB::transaction(function () use ($validated, $apartment, &$created) {
            $accounts = Account::whereIn('id', $validated['accounts'])
                ->where('apartment_id', $apartment->id)
                ->whereNull('user_id')
                ->get();

            foreach ($accounts as $account) {
                // Email olmayanları atla
                if (! $account->email) {
                    continue;
                }

                // User oluştur veya bul
                $user = User::firstOrCreate(
                    ['email' => $account->email],
                    [
                        'name'     => $account->name,
                        'phone'    => $account->phone,
                        'password' => bcrypt(Str::random(16)),
                        'role'     => 'user',
                    ]
                );

                // Hesaba bağla
                $account->update(['user_id' => $user->id]);

                // Apartman'a ekle (resident olarak)
                if (! $apartment->members()->whereKey($user->id)->exists()) {
                    $apartment->members()->attach($user->id, ['role' => 'resident']);
                }

                // Tenant ise unit occupant'ını güncelle
                if ($account->type === Account::TYPE_TENANT && $account->unit) {
                    $account->unit->update(['occupant_account_id' => $account->id]);
                }

                $created++;
            }
        });

        return back()->with('status', $created.' kullanıcı oluşturuldu. Giriş bilgilerini paylaşabilirsiniz.');
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

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => bcrypt($validated['password'])]);

        return back()->with('status', $user->name.' için şifre güncellendi. Kullanıcıya yeni şifreyi bildirin.');
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

        if ($userId) {
            $hasOtherAccounts = Account::where('apartment_id', $apartment->id)
                ->where('user_id', $userId)
                ->exists();

            if (! $hasOtherAccounts) {
                $apartment->members()->detach($userId);
            }
        }

        return back()->with('status', 'Kullanıcı bağlantısı kaldırıldı.');
    }
}
