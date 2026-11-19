<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\ApartmentManagementPeriod;
use App\Models\ManagementOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApartmentManagementHistory
{
    public function startInitial(Apartment $apartment, User $user): void
    {
        $this->create($apartment, $user->id, $user->name, 'initial:'.$apartment->id.':'.$user->id, now(), 'created');
    }

    public function transfer(Apartment $apartment, User $user, ManagementOffer $offer): void
    {
        $this->backfill($apartment);
        $at = now();
        ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)->where('is_current', true)
            ->where('source_key', '!=', 'offer:'.$offer->id)->update(['ended_at' => $at, 'is_current' => false, 'end_source' => 'transfer']);
        $this->create($apartment, $user->id, $user->name, 'offer:'.$offer->id, $offer->accepted_at ?? $at, 'accepted_offer');
    }

    public function end(Apartment $apartment, User $user): void
    {
        $this->backfill($apartment);
        ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)->where('user_id', $user->id)
            ->where('is_current', true)->update(['ended_at' => now(), 'is_current' => false, 'end_source' => 'role_change']);
    }

    /** Reconstruct only supported dates; never interpret a normal member as a former manager. */
    public function backfill(Apartment $apartment): void
    {
        DB::transaction(function () use ($apartment) {
            $apartment = Apartment::withTrashed()->whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            if (ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)->exists()) {
                return;
            }
            $members = DB::table('apartment_user')->join('users', 'users.id', '=', 'apartment_user.user_id')
                ->where('apartment_id', $apartment->id)->select('apartment_user.*', 'users.name')->get()->keyBy('user_id');
            $offers = ManagementOffer::where('apartment_id', $apartment->id)->where('status', ManagementOffer::STATUS_ACCEPTED)
                ->whereNotNull('accepted_at')->with(['user', 'offerer'])->orderBy('accepted_at')->orderBy('id')->get();
            $open = [];
            $creator = $members->get($apartment->user_id);
            if ($creator) {
                $open[$creator->user_id] = $this->create($apartment, $creator->user_id, $creator->name,
                    'initial:'.$apartment->id.':'.$creator->user_id, $creator->created_at, 'initial_membership');
            }
            foreach ($offers as $offer) {
                $previous = $offer->previous_manager_ids ?? [];
                if ($offer->previous_manager_ids === null) {
                    // Old invitations did not necessarily revoke another manager.
                    $previous = collect(array_keys($open))->filter(function ($id) use ($members, $offer) {
                        $member = $members->get($id);

                        return (int) $id !== (int) $offer->user_id && (! $member || $member->role !== 'owner' || ! $member->is_active);
                    })->all();
                }
                foreach ($previous as $id) {
                    if ((int) $id === (int) $offer->user_id) {
                        continue;
                    }
                    $member = $members->get($id);
                    $period = $open[$id] ?? $this->create($apartment, $member ? (int) $id : null,
                        $member?->name ?? ((int) $offer->offered_by === (int) $id ? $offer->offerer?->name : null) ?? 'Silinmiş kullanıcı',
                        'previous:'.$offer->id.':'.$id, null, 'unknown');
                    // A legacy acceptance may have been reconciled later; keep that actual closure time.
                    $end = $offer->previous_manager_ids !== null && $offer->updated_at->gt($offer->accepted_at)
                        ? $offer->updated_at : $offer->accepted_at;
                    $period->update(['ended_at' => $end, 'is_current' => false, 'end_source' => 'transfer_record']);
                    unset($open[$id]);
                }
                $open[$offer->user_id] = $this->create($apartment, $offer->user_id, $offer->user?->name ?? 'Silinmiş kullanıcı',
                    'offer:'.$offer->id, $offer->accepted_at, 'accepted_offer');
            }
            foreach ($open as $id => $period) {
                $member = $members->get($id);
                if (! $member || $member->role !== 'owner' || ! $member->is_active) {
                    $period->update(['is_current' => false]);
                }
            }
            foreach ($members as $member) {
                if ($member->role === 'owner' && $member->is_active && ! isset($open[$member->user_id])) {
                    $this->create($apartment, $member->user_id, $member->name,
                        'legacy-current:'.$apartment->id.':'.$member->user_id, null, 'unknown');
                }
            }
        });
    }

    private function create(Apartment $apartment, ?int $userId, string $name, string $key, mixed $start, string $source): ApartmentManagementPeriod
    {
        return ApartmentManagementPeriod::firstOrCreate(['source_key' => $key], [
            'apartment_id' => $apartment->id, 'original_apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name, 'apartment_code' => $apartment->code,
            'user_id' => $userId, 'manager_name' => $name, 'started_at' => $start,
            'is_current' => true, 'start_source' => $source,
        ]);
    }
}
