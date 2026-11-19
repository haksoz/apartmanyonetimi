<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderInvoice;
use App\Support\OrderInvoiceXmlMatching;
use App\Support\OrderInvoiceXmlParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminOrderInvoiceXmlController extends Controller
{
    private const SESSION_KEY = 'order_invoice_xml_preview';

    public function create()
    {
        return view('admin.order-invoices.xml-upload');
    }

    public function upload(Request $request, OrderInvoiceXmlParser $parser)
    {
        $data = $request->validate([
            'xml_file' => ['nullable', 'file', 'max:5120', 'required_without:xml_content'],
            'xml_content' => ['nullable', 'string', 'max:5242880', 'required_without:xml_file'],
        ]);
        if ($request->hasFile('xml_file') && filled($data['xml_content'] ?? null)) {
            throw ValidationException::withMessages(['xml_content' => 'Dosya veya metinden yalnızca birini gönderin.']);
        }
        $xml = $request->hasFile('xml_file') ? $request->file('xml_file')->get() : (string) ($data['xml_content'] ?? '');
        try {
            $parsed = $parser->parse($xml);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['xml_content' => $exception->getMessage()]);
        }
        if ($this->duplicate($parsed, hash('sha256', $xml))) {
            throw ValidationException::withMessages(['xml_content' => 'Bu fatura numarası, UUID veya XML daha önce eklenmiş.']);
        }
        $this->discard($request);
        $token = (string) Str::uuid();
        $path = 'invoice-xml-previews/'.$token.'.xml';
        if (! Storage::disk('local')->put($path, $xml)) {
            throw ValidationException::withMessages(['xml_content' => 'XML saklanamadı. Lütfen tekrar deneyin.']);
        }
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token, 'path' => $path, 'parsed' => $parsed,
            'expires_at' => now()->addHour()->timestamp, 'user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.order-invoices.xml.preview');
    }

    public function preview(Request $request, OrderInvoiceXmlMatching $matching)
    {
        $state = $this->state($request);
        if (! $state) {
            return redirect()->route('admin.order-invoices.xml.create')->withErrors(['xml_content' => 'Önizleme süresi doldu. XML’i yeniden yükleyin.']);
        }

        return view('admin.order-invoices.xml-preview', [
            'parsed' => $state['parsed'], 'token' => $state['token'],
            'candidates' => $matching->candidates($state['parsed']), 'matching' => $matching,
        ]);
    }

    public function confirm(Request $request, OrderInvoiceXmlParser $parser, OrderInvoiceXmlMatching $matching)
    {
        $data = $request->validate([
            'preview_token' => ['required', 'uuid'],
            'invoice_id' => ['required', 'integer'],
            'difference_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $state = $this->state($request);
        if (! $state || ! hash_equals($state['token'], $data['preview_token'])) {
            return redirect()->route('admin.order-invoices.xml.create')->withErrors(['xml_content' => 'Önizleme değişti veya süresi doldu. XML’i yeniden yükleyin.']);
        }
        $xml = Storage::disk('local')->get($state['path']);
        try {
            $parsed = $parser->parse($xml);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['invoice_id' => $exception->getMessage()]);
        }
        $hash = hash('sha256', $xml);
        try {
            $invoice = DB::transaction(function () use ($request, $data, $parsed, $xml, $hash, $matching) {
                $invoice = OrderInvoice::whereKey($data['invoice_id'])->lockForUpdate()->first();
                if (! $invoice || ! $matching->matches($invoice, $parsed)) {
                    throw ValidationException::withMessages(['invoice_id' => 'Seçilen takip kaydı bu XML ile eşleşmiyor veya zaten faturalanmış.']);
                }
                if ($this->duplicate($parsed, $hash)) {
                    throw ValidationException::withMessages(['invoice_id' => 'Bu XML veya fatura daha önce başka bir kayda eklenmiş.']);
                }
                $reason = trim((string) ($data['difference_reason'] ?? ''));
                $hasDifference = collect($matching->differences($invoice, $parsed))->contains(fn ($cents) => $cents !== 0);
                if ($hasDifference && $reason === '') {
                    throw ValidationException::withMessages(['difference_reason' => 'XML ile sistem tutarları farklı. Onaylamak için farkın nedenini yazın.']);
                }
                $invoice->update([
                    'invoice_number' => $parsed['invoice_number'], 'invoice_date' => $parsed['invoice_date'],
                    'invoice_uuid' => $parsed['uuid'], 'xml_sha256' => $hash,
                    // Base64 preserves the original bytes, including UTF-16 XML files.
                    'xml_content' => base64_encode($xml), 'xml_details' => $parsed,
                    'xml_difference_reason' => $hasDifference ? $reason : null,
                    'xml_imported_by' => $request->user()->id, 'xml_imported_at' => now(),
                ]);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['invoice_id' => 'Bu fatura daha önce kaydedilmiş. XML’i yeniden kontrol edin.']);
        }
        $this->discard($request);

        return redirect()->route('admin.order-invoices.show', $invoice)->with('status', 'XML eşleştirildi. Gerçek fatura numarası ve tarihi kaydedildi.');
    }

    public function cancel(Request $request)
    {
        $this->discard($request);

        return redirect()->route('admin.orders.index')->with('status', 'XML eşleştirme iptal edildi.');
    }

    public function download(OrderInvoice $invoice)
    {
        abort_unless($invoice->xml_content, 404);

        return response(base64_decode($invoice->xml_content), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$invoice->trackingNumber().'.xml"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function duplicate(array $parsed, string $hash): bool
    {
        return OrderInvoice::where('invoice_number', $parsed['invoice_number'])
            ->orWhere('xml_sha256', $hash)
            ->when($parsed['uuid'], fn ($query, $uuid) => $query->orWhere('invoice_uuid', $uuid))->exists();
    }

    private function state(Request $request): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);
        if (! is_array($state) || ($state['user_id'] ?? null) !== $request->user()->id
            || ($state['expires_at'] ?? 0) < now()->timestamp
            || ! Storage::disk('local')->exists($state['path'])) {
            $this->discard($request);

            return null;
        }

        return $state;
    }

    private function discard(Request $request): void
    {
        $state = $request->session()->pull(self::SESSION_KEY);
        if (is_array($state) && isset($state['path'])) {
            Storage::disk('local')->delete($state['path']);
        }
    }
}
