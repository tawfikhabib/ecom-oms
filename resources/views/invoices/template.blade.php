<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice - {{ $invoice->invoice_number ?? ($order->order_number ?? 'N/A') }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #222; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px }
        .company { font-weight:700; font-size:18px }
        .meta { text-align:right }
        table { width:100%; border-collapse:collapse; margin-top:20px }
        th, td { border:1px solid #ddd; padding:8px; }
        th { background:#f7f7f7; text-align:left }
        .totals td { border:none; }
        .right { text-align:right }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company">{{ config('app.name', 'EOMS') }}</div>
            <div>Orders & Invoices</div>
            <div>support@example.com</div>
        </div>
        <div class="meta">
            <div><strong>Invoice:</strong> {{ $invoice->invoice_number ?? ($order->order_number ?? 'N/A') }}</div>
            <div><strong>Issued:</strong> {{ 
                isset($invoice) && $invoice->issued_at ? $invoice->issued_at->format('Y-m-d') : ($order->created_at? $order->created_at->format('Y-m-d') : now()->format('Y-m-d'))
            }}</div>
            <div><strong>Customer:</strong> {{ $order->customer->name ?? ($order->customer_name ?? 'Customer') }}</div>
            <div>{{ $order->customer->email ?? ($order->customer_email ?? '') }}</div>
        </div>
    </div>

    <h3>Order #{{ $order->order_number ?? 'N/A' }}</h3>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? $item->product_name ?? 'Product' }}</td>
                    <td>{{ $item->product->sku ?? $item->sku ?? '' }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->price, 2) }}</td>
                    <td class="right">{{ number_format($item->quantity * $item->price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width:300px; float:right; margin-top:20px;">
        <tbody>
            <tr>
                <td>Subtotal</td>
                <td class="right">{{ number_format($order->subtotal ?? $order->items->sum(function($i){ return $i->quantity * $i->price; }), 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="right">{{ number_format($order->tax_amount ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Shipping</td>
                <td class="right">{{ number_format($order->shipping_amount ?? 0, 2) }}</td>
            </tr>
            <tr style="font-weight:700">
                <td>Total</td>
                <td class="right">{{ number_format($order->total ?? ($order->subtotal + ($order->tax_amount ?? 0) + ($order->shipping_amount ?? 0)), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="clear:both; margin-top:120px; font-size:12px; color:#666">Thank you for your business.</div>
</body>
</html>
