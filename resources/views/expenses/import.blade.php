@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Gider İçe Aktar</h1>
            <p class="mt-1 text-sm text-slate-500">Excel dosyasından toplu gider aktarımı.</p>
        </div>
        <a href="{{ route('expenses.import-sample') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Şablon İndir
        </a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('expenses.import-preview') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Excel Dosyası</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-sm text-slate-600
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-xl file:border-0
                              file:text-sm file:font-semibold
                              file:bg-slate-100 file:text-slate-700
                              hover:file:bg-slate-200
                              file:cursor-pointer">
                <p class="mt-2 text-xs text-slate-500">
                    Desteklenen formatlar: .xlsx, .xls, .csv
                </p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-amber-800 mb-2">Excel Formatı</h3>
                <p class="text-xs text-amber-700 mb-3">
                    Dosyanızda şu kolonlar olmalıdır:
                </p>
                <div class="overflow-x-auto">
                    <table class="text-xs text-left">
                        <thead>
                            <tr class="text-amber-800">
                                <th class="pr-4 py-1">A: Tarih</th>
                                <th class="pr-4 py-1">B: Hesap Adı</th>
                                <th class="pr-4 py-1">C: Açıklama</th>
                                <th class="pr-4 py-1">D: Son Ödeme</th>
                                <th class="pr-4 py-1">E: Kategori</th>
                                <th class="pr-4 py-1">F: Alacak</th>
                                <th class="py-1">G: Borç</th>
                            </tr>
                        </thead>
                        <tbody class="text-amber-700">
                            <tr>
                                <td class="pr-4 py-1">15.01.2024</td>
                                <td class="pr-4 py-1">ABC Ltd</td>
                                <td class="pr-4 py-1">Ocak aidat</td>
                                <td class="pr-4 py-1">30.01.2024</td>
                                <td class="pr-4 py-1">Temizlik</td>
                                <td class="pr-4 py-1">1000</td>
                                <td class="py-1">600</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="mt-3 text-xs text-amber-700 space-y-1">
                    <li>• <strong>Tarih formatı:</strong> GG.AA.YYYY (örn: 15.01.2024)</li>
                    <li>• <strong>Alacak:</strong> Toplam gider tutarı</li>
                    <li>• <strong>Borç:</strong> Ödenen tutar (Alacak'tan büyük olamaz)</li>
                    <li>• <strong>Kalan:</strong> Alacak - Borç hesaplanır</li>
                    <li>• Eşleşmeyen kategoriler "Diğer" olarak atanır</li>
                    <li>• Tedarikçi sonradan gider detayından bağlanabilir</li>
                </ul>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Önizleme Yap
                </button>
                <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Vazgeç
                </a>
            </div>
        </form>
    </div>
@endsection
