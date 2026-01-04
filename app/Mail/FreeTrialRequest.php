<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FreeTrialRequest extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $company;
    public string $messageLocale;
    public string $ip;
    public string $userAgent;

    /**
     * @param array{name:string,email:string,company:string,locale:string,ip:string,user_agent:string} $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->company = $data['company'];
        $this->messageLocale = $data['locale'];
        $this->ip = $data['ip'];
        $this->userAgent = $data['user_agent'];
    }

    public function build()
    {
        return $this->subject('Nueva solicitud de prueba gratuita')
            ->replyTo($this->email, $this->name)
            ->view('emails.free_trial_request');
    }
}
