@extends('layouts.app')

@section('title', 'Satış XML Gir')

@section('content')
    <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-emerald-700">← Siparişlere dön</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Satış XML gir</h1>
    <p class="mt-2 max-w-3xl text-sm text-slate-600">Atmaca’da düzenlediğiniz satış faturasını yükleyin. SIP numarası varsa ilgili sipariş; yalnızca ABN varsa aynı aboneliğin takip kayıtları listelenir. Alıcı VKN/TCKN ve tutarları karşılaştırıp doğru kaydı onaylayın.</p>
    @if ($errors->any())
        <div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('admin.order-invoices.xml.upload') }}" enctype="multipart/form-data" class="mt-5 max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <label class="block text-sm font-medium text-slate-700" for="xml_file">XML dosyası</label>
        <input type="file" id="xml_file" name="xml_file" accept=".xml,application/xml,text/xml" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold">
        <p class="text-xs text-slate-500">En fazla 5 MB. Dosya seçin veya aşağıya XML içeriğini yapıştırın.</p>
        <label class="block text-sm font-medium text-slate-700" for="xml_content">XML içeriği</label>
        <textarea id="xml_content" name="xml_content" rows="10" class="w-full rounded-xl border border-slate-300 p-3 font-mono text-xs" placeholder="Dosya yerine XML metnini buraya yapıştırabilirsiniz.">{{ old('xml_content') }}</textarea>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Yükle ve eşleşenleri listele</button>
            <a href="{{ route('admin.orders.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Vazgeç</a>
        </div>
    </form>
@endsection
