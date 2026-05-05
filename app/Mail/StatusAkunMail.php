<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;

class StatusAkunMail extends Mailable
{
    use Queueable, SerializesModels;

    public $status;
    public $name;

    public function __construct($status, $name)
    {
        $this->status = $status;
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Status Pendaftaran Akun')
                    ->view('emails.status_akun');
    }
}