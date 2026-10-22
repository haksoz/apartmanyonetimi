@extends('layouts.app')

@section('content')
    @include('assessment-plans._header')
    <form method="POST" action="{{ route('assessment-plans.store') }}" class="space-y-4">
        @csrf
        @include('assessment-plans._form')
    </form>
@endsection
