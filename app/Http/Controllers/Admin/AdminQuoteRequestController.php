<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminQuoteRequestController extends Controller
{
    public function index()
    {
        $requests = QuoteRequest::query()
            ->with('user')
            ->latest()
            ->paginate(30);

        return view('admin.quote-requests.index', compact('requests'));
    }

    public function update(Request $request, QuoteRequest $quoteRequest)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(QuoteRequest::STATUSES)],
        ]);

        $quoteRequest->update(['status' => $validated['status']]);

        return back()->with('status', 'Talep durumu güncellendi.');
    }
}
