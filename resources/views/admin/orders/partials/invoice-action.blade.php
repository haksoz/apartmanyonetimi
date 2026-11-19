@if ($order->invoice)
    <a href="{{ route('admin.order-invoices.show', $order->invoice) }}" class="mr-3 whitespace-nowrap text-sm font-semibold text-emerald-700 hover:text-emerald-800">Fatura takibi</a>
@elseif ($order->can_invoice)
    <button type="button" onclick="document.getElementById('invoice-order-modal-{{ $order->id }}').showModal()" class="mr-3 text-sm font-semibold text-emerald-700 hover:text-emerald-800">Faturala</button>
@endif
