<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Only migrate unused resident identities. Keep users with any other history.
        $references = [];
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $foreign) {
                if ($foreign['foreign_table'] === 'users') {
                    foreach ($foreign['columns'] as $column) {
                        $references[] = [$table['name'], $column];
                    }
                }
            }
        }

        DB::table('users')->whereNull('activated_at')->where('role', 'user')->orderBy('id')
            ->each(function ($user) use ($references) {
                DB::transaction(function () use ($user, $references) {
                    $accounts = DB::table('accounts')->where('user_id', $user->id)->get();
                    if ($accounts->isEmpty() || $accounts->contains(fn ($account) => $account->deleted_at
                        || ! in_array($account->type, ['owner', 'tenant'], true)
                        || strcasecmp((string) $account->email, $user->email) !== 0)) {
                        return;
                    }
                    if (DB::table('apartment_user')->where('user_id', $user->id)->where('role', '!=', 'member')->exists()
                        || DB::table('apartment_user')->where('user_id', $user->id)->whereNotIn('apartment_id', $accounts->pluck('apartment_id'))->exists()
                        || DB::table('user_invitations')->where('user_id', $user->id)->whereNotNull('accepted_at')->exists()
                        || DB::table('sessions')->where('user_id', $user->id)->exists()) {
                        return;
                    }
                    foreach ($references as [$table, $column]) {
                        if (! in_array($table, ['accounts', 'apartment_user', 'user_invitations'], true)
                            && DB::table($table)->where($column, $user->id)->exists()) {
                            return;
                        }
                    }
                    $oldInvite = DB::table('user_invitations')->where('user_id', $user->id)->latest('id')->first();
                    if (! $oldInvite) {
                        return;
                    }
                    foreach ($accounts as $index => $account) {
                        $values = [
                            'user_id' => null,
                            'account_id' => $account->id,
                            'email' => $account->email,
                            'accepted_at' => null,
                            'updated_at' => now(),
                        ];
                        if ($index === 0 && $oldInvite) {
                            DB::table('user_invitations')->where('id', $oldInvite->id)->update($values);
                        } else {
                            DB::table('user_invitations')->insert($values + [
                                'token_hash' => hash('sha256', Str::random(64)),
                                'expires_at' => now()->addDays(7),
                                'created_at' => now(),
                            ]);
                        }
                    }
                    DB::table('accounts')->where('user_id', $user->id)->update(['user_id' => null]);
                    DB::table('apartment_user')->where('user_id', $user->id)->delete();
                    DB::table('user_invitations')->where('user_id', $user->id)->delete();
                    DB::table('users')->where('id', $user->id)->delete();
                });
            });
    }

    public function down(): void
    {
        // Accepted users are retained; pending invitations do not recreate unused identities.
    }
};
