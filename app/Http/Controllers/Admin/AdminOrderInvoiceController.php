<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderInvoice;
use App\Models\UserSubscription;
use App\Support\OrderInvoicing;
use App\Support\SubscriptionOrderLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderInvoiceController extends Controller
{
    public function store(Request $request, UserSubscription $order, OrderInvoicing $invoicing)
    {
        try {
            $invoice = DB::transaction(function () use ($order, $request, $invoicing) {
                $order = SubscriptionOrderLock::acquire($order);
                if ($order->invoice()->exists()) {
                    throw ValidationException::withMessages(['invoicing' => 'Bu sipariş zaten faturalandırılmış.']);
                }
                if (! $invoicing->eligible($order)) {
                    throw ValidationException::withMessages(['invoicing' => 'Yalnızca tamamı tahsil edilmiş, onaylı ve ödeme incelemesi bulunmayan siparişler faturalanabilir.']);
                }

                return $order->invoice()->create([
                    'created_by' => $request->user()->id,
                    'snapshot' => $invoicing->snapshot($order),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['invoicing' => 'Bu sipariş zaten faturalandırılmış.']);
        }

        return redirect()->route('admin.order-invoices.show', $invoice)->with('status', 'Fatura takip kaydı oluşturuldu. Atmaca’ya aktarabilirsiniz.');
    }

    public function show(OrderInvoice $invoice)
    {
        $invoice->load('creator');

        return view('admin.order-invoices.show', compact('invoice'));
    }
}
