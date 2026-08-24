<?php

namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PayslipEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payslip $payslip)
    {
    }

    public function envelope(): Envelope
    {
        $nama = $this->payslip->salaryRecord?->employee?->nama ?? $this->payslip->salaryRecord?->nama_snapshot ?? 'Pegawai';
        $periode = $this->payslip->salaryRecord?->salaryPeriod?->nama_periode ?? '';

        return new Envelope(subject: 'Slip Gaji '.$periode.' — '.$nama);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payslip');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath(Storage::disk('local')->path($this->payslip->path_file))
                ->as(basename($this->payslip->path_file))
                ->withMime('application/pdf'),
        ];
    }
}
