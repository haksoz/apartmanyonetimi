<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountPortalOffer;
use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\CurrentApartment;
use App\Support\ManagementOfferService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountUserController extends Controller
{
    /**
     * Kullanıcı yönetimi listesi.
     */
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment) {
            return redirect()->route('subscriber.dashboard');
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

        $pendingManagerIds = ManagementOffer::query()
            ->where('apartment_id', $apartment->id)
            ->openPending()
            ->pluck('user_id');

        $filter = $request->query('durum') === 'eski' ? 'eski' : 'aktif';
        $users = $users->filter(function ($user) use ($linkedAccounts, $filter) {
            $accounts = $linkedAccounts->get($user->id, collect());
            $former = $user->pivot->role !== 'owner' && $accounts->isNotEmpty()
                && $accounts->every(fn ($account) => $account->residenceHasEnded());

            return $filter === 'eski' ? $former : ! $former;
        });

        return view('users.index', compact('apartment', 'users', 'linkedAccounts', 'pendingManagerIds', 'filter'));
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

        abort_unless($linkedAccounts->isNotEmpty() || $apartment->members()->whereKey($user->id)->exists(), 404);

        return view('users.show', compact('apartment', 'user', 'linkedAccounts'));
    }

    /**
     * Eski düzenleme adresi detaya döner.
     */
    public function edit(User $user)
    {
        return redirect()->route('users.show', $user);
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

        $hasLinkedAccount = Account::query()
            ->where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($hasLinkedAccount) {
            return redirect()
                ->route('users.show', $user)
                ->withErrors(['name' => 'Bu kişinin adı, e-postası ve telefonu bağlı hesabından düzenlenir.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return redirect()->route('users.show', $user)->with('status', $user->name.' kullanıcısı güncellendi.');
    }

    /**
     * Hesabın e-postasına davet oluştur veya apartman üyesini bağla.
     */
    public function store(CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless(in_array($account->type, [Account::TYPE_OWNER, Account::TYPE_TENANT], true), 404);

        return DB::transaction(function () use ($account, $apartment) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            if ($account->user_id) {
                return back()->withErrors(['email' => 'Bu hesabın AidatCep kullanıcısı zaten var.']);
            }

            if (! filled($account->email)) {
                return back()->withErrors(['email' => 'Önce bu hesaba e-posta yazın.']);
            }

            $existing = User::query()->where('email', $account->email)->first();
            if (! $existing || ! $existing->isActivated()) {
                return redirect()->route('accounts.show', $account)
                    ->with('invite_account_id', $account->id)
                    ->with('invite_url', route('invite.show', UserInvitation::issueForAccount($account)));
            }

            $account->portalInvitations()->whereNull('accepted_at')->delete();
            $account->portalOffers()->where('status', AccountPortalOffer::STATUS_PENDING)
                ->update(['status' => AccountPortalOffer::STATUS_REJECTED, 'rejected_at' => now()]);

            if (! $apartment->members()->whereKey($existing->id)->exists()) {
                AccountPortalOffer::query()->create([
                    'apartment_id' => $apartment->id,
                    'account_id' => $account->id,
                    'user_id' => $existing->id,
                    'offered_by' => auth()->id(),
                    'status' => AccountPortalOffer::STATUS_PENDING,
                ]);

                return redirect()->route('accounts.show', $account);
            }

            $account->update(['user_id' => $existing->id]);

            return redirect()->route('accounts.show', $account);
        });
    }

    public function refreshAccountInvite(CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        if (! $account->user_id) {
            abort_unless($account->portalInvitations()->whereNull('accepted_at')->exists(), 404);

            return $this->store($currentApartment, $account);
        }

        $user = User::query()->findOrFail($account->user_id);

        if ($user->isActivated()) {
            return back()->withErrors(['invite' => 'Bu kullanıcı daveti zaten kabul etti.']);
        }

        return redirect()
            ->route('accounts.show', $account)
            ->with('invite_url', route('invite.show', UserInvitation::issue($user)))
            ->with('status', 'Yeni davet bağlantısı hazır.');
    }

    public function applyIdentity(CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->user_id, 404);

        $user = User::query()->findOrFail($account->user_id);
        abort_unless($user->isActivated(), 403);

        $account->update([
            'name' => $user->name,
            'phone' => $user->phone,
        ]);

        return back()->with('status', 'AidatCep adı ve telefonu bu hesaba yazıldı.');
    }

    /**
     * Apartmanda belirtilen kullanıcı dışında başka aktif yönetici var mı?
     */
    private function hasOtherActiveOwner(Apartment $apartment, int $excludeUserId): bool
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

        return $this->applyRole($apartment, User::query()->findOrFail($account->user_id), $validated['role']);
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

        return $this->applyRole($apartment, $user, $validated['role']);
    }

    private function applyRole(Apartment $apartment, User $user, string $role)
    {
        if ($role === 'owner') {
            return app(ManagementOfferService::class)->offer($apartment, $user, auth()->user());
        }

        if (! $this->hasOtherActiveOwner($apartment, $user->id)) {
            return back()->withErrors(['role' => 'Apartmanda en az bir yönetici olmalıdır.']);
        }

        $apartment->members()->updateExistingPivot($user->id, ['role' => 'member']);

        ManagementOffer::query()
            ->where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->openPending()
            ->update([
                'status' => ManagementOffer::STATUS_REJECTED,
                'rejected_at' => now(),
            ]);

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

    public function refreshInvite(CurrentApartment $currentApartment, User $user)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment, 403);
        abort_unless($apartment->members()->whereKey($user->id)->exists(), 403);

        if ($user->isActivated()) {
            return back()->withErrors(['invite' => 'Bu kullanıcı daveti zaten kabul etti.']);
        }

        $plain = UserInvitation::issue($user);

        return back()
            ->with('invite_url', route('invite.show', $plain))
            ->with('invite_user_id', $user->id);
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

        return redirect()->route('users.show', $user)
            ->withErrors(['portal_access' => 'Erişimi bağlı hesap üzerinden gerekçe ve süre belirterek yönetin.']);
    }

    public function manageAccess(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor($request->user());
        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->user?->isActivated(), 422);
        $validated = $request->validate([
            'action' => ['required', 'in:suspend,resume'],
            'reason' => ['required', 'string', 'max:1000'],
            'suspended_until' => ['required_if:action,suspend', 'nullable', 'date'],
        ]);
        if ($validated['action'] === 'suspend') {
            $validated['suspended_until'] = Carbon::parse($validated['suspended_until'], 'Europe/Istanbul')->utc();
            if (! $validated['suspended_until']->isFuture()) {
                throw ValidationException::withMessages(['suspended_until' => 'Bitiş tarihi ve saati gelecekte olmalıdır.']);
            }
        }
        DB::transaction(function () use ($account, $validated, $request) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($account->user?->isActivated(), 422);
            $until = $validated['action'] === 'suspend' ? $validated['suspended_until'] : null;
            $account->update([
                'portal_suspended_until' => $until,
                'portal_suspension_reason' => $until ? $validated['reason'] : null,
            ]);
            $account->portalAccessEvents()->create([
                'user_id' => $account->user_id,
                'performed_by' => $request->user()->id,
                'action' => $validated['action'],
                'reason' => $validated['reason'],
                'suspended_until' => $until,
            ]);
        });

        return back();
    }

    /**
     * Hesaptan kullanıcı bağını kaldır.
     */
    public function destroy(Request $request, CurrentApartment $currentApartment, Account $account)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);

        $userId = DB::transaction(function () use ($account, $apartment, $request) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            $userId = $account->user_id;
            if ($account->user?->isActivated()) {
                $validated = $request->validate([
                    'action' => ['required', 'in:unlink'],
                    'reason' => ['required', 'string', 'max:1000'],
                ]);
                $account->portalAccessEvents()->create([
                    'user_id' => $userId, 'performed_by' => $request->user()->id,
                    'action' => 'unlink', 'reason' => $validated['reason'],
                ]);
            }
            $account->portalInvitations()->whereNull('accepted_at')->delete();
            $account->update(['user_id' => null, 'portal_suspended_until' => null, 'portal_suspension_reason' => null]);

            AccountPortalOffer::query()
                ->where('account_id', $account->id)
                ->where('status', AccountPortalOffer::STATUS_PENDING)
                ->update([
                    'status' => AccountPortalOffer::STATUS_REJECTED,
                    'rejected_at' => now(),
                ]);

            if ($userId && ! Account::where('apartment_id', $apartment->id)->where('user_id', $userId)->exists()) {
                $apartment->members()->wherePivot('role', '!=', 'owner')->detach($userId);
            }

            return $userId;
        });

        return back()->with('status', $userId
            ? 'AidatCep bağlantısı kaldırıldı.'
            : 'Bekleyen davet iptal edildi.');
    }
}
