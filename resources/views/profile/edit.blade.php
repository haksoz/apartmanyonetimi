@extends('layouts.app')

@section('title', 'Hesabım')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Hesabım</h1>
        <p class="mt-1 text-sm text-slate-500">Ad ve telefon size aittir. E-posta giriş adresinizdir.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="max-w-xl rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="name">Ad Soyad</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600">E-posta</label>
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $user->email }}</p>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600" for="phone">Telefon</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('phone')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
                <button type="button" data-password-open class="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Parola değiştir</button>
            </div>
        </form>
    </div>

    @php
        $passwordDialogOpen = $errors->has('current_password') || $errors->has('password');
    @endphp
    <div id="password-dialog" @class([
        'fixed inset-0 z-50 items-center justify-center bg-black/50 p-4',
        'flex' => $passwordDialogOpen,
        'hidden' => ! $passwordDialogOpen,
    ]) role="dialog" aria-modal="true" aria-labelledby="password-dialog-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 id="password-dialog-title" class="text-base font-bold text-slate-950">Yeni parola</h2>
            <p class="mt-1 text-sm text-slate-500">Mevcut parolanızı yazıp yeni bir parola belirleyin.</p>
            <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-600" for="current_password">Mevcut parola</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('current_password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-600" for="password">Yeni parola</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-600" for="password_confirmation">Yeni parola tekrar</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" data-password-close class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Parolayı kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const dialog = document.getElementById('password-dialog');
            const open = function () {
                dialog.classList.remove('hidden');
                dialog.classList.add('flex');
                const field = document.getElementById('current_password');
                if (field) field.focus();
            };
            const close = function () {
                dialog.classList.add('hidden');
                dialog.classList.remove('flex');
            };
            document.querySelectorAll('[data-password-open]').forEach(function (button) {
                button.addEventListener('click', open);
            });
            document.querySelectorAll('[data-password-close]').forEach(function (button) {
                button.addEventListener('click', close);
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) close();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && ! dialog.classList.contains('hidden')) close();
            });
        })();
    </script>

    <div class="mt-4 max-w-xl rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Onaylanan belgeler</h2>
        <p class="mt-1 text-sm text-slate-500">Kabul ettiğiniz aydınlatma ve sözleşmeler.</p>
        @if ($acceptances->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz onaylanmış bir belge yok.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-200">
                @foreach ($acceptances as $acceptance)
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ \App\Support\LegalConsent::label($acceptance->document_key) }}</div>
                                @if ($acceptance->order)
                                    <div class="mt-0.5 text-xs text-slate-500">Sipariş {{ $acceptance->order->order_number }}</div>
                                @elseif ($acceptance->apartment)
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $acceptance->apartment->name }}</div>
                                @endif
                            </div>
                            <div class="shrink-0 text-right text-xs text-slate-500">
                                <div>{{ $acceptance->accepted_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                <div>Sürüm {{ $acceptance->document_version }}</div>
                            </div>
                        </div>
                        @if (filled($acceptance->accepted_content))
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-600">Kabul edilen metin</summary>
                                <div class="mt-2 whitespace-pre-wrap rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">{{ $acceptance->accepted_content }}</div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @unless ($user->isAdmin() || $user->canPurchaseSubscription())
        <div class="mt-4 max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">AidatCep'te apartman yönetmek istiyorum</h2>
            <p class="mt-1 text-sm text-slate-500">Dairelerinizin bulunduğu apartmanlar dışında bir apartmanın yöneticisiyseniz ve AidatCep'i kullanarak bir apartman yönetmek istiyorsanız buradan kuruluma geçebilirsiniz.</p>
            @if ($remainingApartments === null)
                <p class="mt-3 text-sm text-slate-700">Yeni apartman oluşturup yönetebilirsiniz.</p>
            @elseif ($remainingApartments > 0)
                <p class="mt-3 text-sm text-slate-700">Ücretsiz {{ $remainingApartments }} apartman oluşturabilir ve yönetebilirsiniz.</p>
            @else
                <p class="mt-3 text-sm text-slate-700">{{ \App\Support\UserApartmentQuota::CONTACT_MESSAGE }}</p>
            @endif
            @if ($remainingApartments === null || $remainingApartments > 0)
                <form method="POST" action="{{ route('profile.open-apartment') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kuruluma git</button>
                </form>
            @endif
        </div>
    @endunless
@endsection
