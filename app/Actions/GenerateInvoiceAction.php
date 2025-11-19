<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateInvoiceAction
{
    /**
     * Generate PDF invoice for an order
     *
     * @param Order $order
     * @return Invoice
     */
    public function execute(Order $order): Invoice
    {
        // Check if invoice already exists
        if ($order->invoice) {
            return $order->invoice;
        }

        $invoiceNumber = $this->generateInvoiceNumber();

        // Generate PDF
        $pdf = Pdf::loadView('invoices.template', ['order' => $order]);
        $filename = "invoice-{$invoiceNumber}.pdf";
        $path = "invoices/{$filename}";

        // Ensure invoices directory exists and store PDF
        Storage::disk('public')->makeDirectory('invoices');
        Storage::disk('public')->put($path, $pdf->output());

        // Create invoice record
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
            'pdf_path' => Storage::url($path),
        ]);

        // Dispatch event for sending invoice via email
        event(new \App\Events\InvoiceGenerated($invoice));

        return $invoice;
    }

    /**
     * Generate unique invoice number
     *
     * @return string
     */
    protected function generateInvoiceNumber(): string
    {
        do {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        } while (Invoice::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }
}
