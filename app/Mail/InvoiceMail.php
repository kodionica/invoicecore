<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceMail extends Mailable {
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public string $pdfPath;

    public function __construct( Invoice $invoice, string $pdfPath ) {
        $this->invoice = $invoice;
        $this->pdfPath = $pdfPath;
    }

    public function build(): self {
        $file_name = "faktura-{$this->invoice->invoice_number}.pdf";

        return $this
            ->subject( "Faktura #{$this->invoice->invoice_number}" )
            ->view( 'emails.invoice', [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'client'  => $this->invoice->client,
            ] )
            ->attach( Storage::disk( 'local' )->path( $this->pdfPath ), [
                'as'   => $file_name,
                'mime' => 'application/pdf',
            ] );
    }
}
