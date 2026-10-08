@php
    $published = \App\Models\LegalDocumentVersion::current($documentKey);
@endphp
@if ($published)
    <div class="whitespace-pre-wrap">{{ $published->body }}</div>
@else
    @include('legal.content.pending')
@endif
