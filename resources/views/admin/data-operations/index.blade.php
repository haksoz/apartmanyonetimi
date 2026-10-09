@extends('layouts.app')

@section('title', 'Veri İşlem Kayıtları')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Veri İşlem Kayıtları</h1>
        <p class="mt-1 text-sm text-slate-500">İşlem verisini silme ve kurulum yenileme kayıtları. Arşiv dosyası ve geri yükleme henüz yok.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">No</th>
                        <th class="px-4 py-3 font-semibold">Tarih</th>
                        <th class="px-4 py-3 font-semibold">İşlem</th>
                        <th class="px-4 py-3 font-semibold">Kullanıcı</th>
                        <th class="px-4 py-3 font-semibold">Apartman</th>
                        <th class="px-4 py-3 font-semibold">Sonuç</th>
                        <th class="px-4 py-3 font-semibold">Yedekleme</th>
                        <th class="px-4 py-3 font-semibold">Kayıt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($operations as $operation)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.data-operations.show', $operation) }}" class="font-semibold text-slate-900 hover:underline">#{{ $operation->id }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $operation->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ $operation->actionLabel() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $operation->actor_name ?: ($operation->user?->name ?? '—') }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                #{{ $operation->source_apartment_id ?: $operation->apartment_id ?: '—' }}
                                {{ $operation->apartment_name }}
                            </td>
                            <td class="px-4 py-3 text-slate-900">{{ $operation->resultLabel() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $operation->archiveLabel() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $operation->apartmentPresenceLabel() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">Kayıt yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $operations->links() }}</div>
@endsection
