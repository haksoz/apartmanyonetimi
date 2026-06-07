@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Toplu Hesap İçe Aktar</h1>
            <p class="mt-1 text-sm text-slate-500">Excel dosyasından toplu hesap ve cari hareket aktarımı.</p>
        </div>
        <a href="{{ route('accounts.bulk-import-sample') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Şablon İndir
        </a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('accounts.bulk-import-preview') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Excel Dosyası</label>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-slate-600
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-xl file:border-0
                              file:text-sm file:font-semibold
                              file:bg-slate-100 file:text-slate-700
                              hover:file:bg-slate-200
                              file:cursor-pointer">
                <p class="mt-2 text-xs text-slate-500">
                    Desteklenen formatlar: .xlsx, .xls
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
                                <th class="pr-4 py-1">C: Daire No</th>
                                <th class="pr-4 py-1">D: Kategori</th>
                                <th class="pr-4 py-1">E: Açıklama</th>
                                <th class="pr-4 py-1">F: Alacak</th>
                                <th class="py-1">G: Borç</th>
                            </tr>
                        </thead>
                        <tbody class="text-amber-700">
                            <tr>
                                <td class="pr-4 py-1">05.01.2020</td>
                                <td class="pr-4 py-1">Recep Kalkan</td>
                                <td class="pr-4 py-1">03</td>
                                <td class="pr-4 py-1">-</td>
                                <td class="pr-4 py-1">2020 Bina Masraf</td>
                                <td class="pr-4 py-1">481,26</td>
                                <td class="py-1">-</td>
                            </tr>
                            <tr>
                                <td class="pr-4 py-1">02.12.2020</td>
                                <td class="pr-4 py-1">2020 Yıldırım Suyu</td>
                                <td class="pr-4 py-1">-</td>
                                <td class="pr-4 py-1">Demirbaş</td>
                                <td class="pr-4 py-1">Gider Tamiri</td>
                                <td class="pr-4 py-1">-</td>
                                <td class="py-1">170,00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="mt-3 text-xs text-amber-700 space-y-1">
                    <li>• <strong>Hesap Adı:</strong> Zorunlu. Aynı isimdeki kayıtlar tek hesapta birleştirilir.</li>
                    <li>• <strong>Daire No:</strong> Opsiyonel. Daire ilişkisi kurulur (Kat Maliki/Kiracı için).</li>
                    <li>• <strong>Tip Belirleme:</strong> Ön izleme ekranında hesap tipleri seçilecektir.</li>
                    <li>• <strong>Kategori:</strong> Opsiyonel. Gider kategorisi (tedarikçiler için).</li>
                    <li>• Eşleşmeyen kategoriler "Diğer" olarak atanır.</li>
                </ul>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-blue-800 mb-2">Nasıl Çalışır?</h3>
                <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                    <li>Excel dosyanızı yükleyin</li>
                    <li>Ön izleme ekranında hesap tiplerini seçin (Kat Maliki / Kiracı / Tedarikçi)</li>
                    <li>Mevcut hesaplarla eşleşenleri onaylayın</li>
                    <li>Hesaplar ve cari hareketler otomatik oluşturulur</li>
                </ol>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Önizleme Yap
                </button>
                <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Vazgeç
                </a>
            </div>
        </form>
    </div>
@endsection
