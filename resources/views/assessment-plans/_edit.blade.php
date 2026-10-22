<form method="POST" action="{{ route('assessment-plans.update', $plan) }}" class="mb-8 space-y-4">
    @csrf
    @method('PUT')
    @include('assessment-plans._form')
</form>
