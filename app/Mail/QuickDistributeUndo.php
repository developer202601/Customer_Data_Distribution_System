<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuickDistributeUndo extends Mailable
{
    use Queueable, SerializesModels;

    public int $reportId;
    public string $token;

    public function __construct(int $reportId, string $token)
    {
        $this->reportId = $reportId;
        $this->token = $token;
    }

    public function build()
    {
        $cancelUrl = route('cc.reports.distribute.cancel', ['report' => $this->reportId, 'token' => $this->token]);

        return $this->subject('Undo: Quick Call Center Distribution')
            ->view('emails.quick_distribute_undo')
            ->with([
                'cancelUrl' => $cancelUrl,
                'reportId' => $this->reportId,
                'token' => $this->token,
            ]);
    }
}
