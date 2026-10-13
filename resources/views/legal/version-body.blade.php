@php
    $published = \App\Models\LegalDocumentVersion::current($documentKey);
    $legalHtml = $published
        ? \App\Support\LegalPlaceholders::html($published->body, $legalPlaceholderValues ?? null, (bool) ($legalPlaceholderTokens ?? false))
        : null;
@endphp
@if ($legalHtml !== null)
    @if (($legalPlaceholderValues ?? null) === null && in_array($documentKey, \App\Support\LegalConsent::SALE_DOCUMENTS, true))
        <p class="mb-4 text-sm text-slate-500">Bu metin genel şablondur. Siparişe özel bilgiler satın alma sırasında doldurulur ve kabul kaydında saklanır.</p>
    @endif
    <div class="whitespace-pre-wrap">{!! $legalHtml !!}</div>
@else
    @include('legal.content.pending')
@endif
