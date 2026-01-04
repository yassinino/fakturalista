<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $subjectLine;
    public string $content;
    public string $messageLocale;
    public string $ip;
    public string $userAgent;

    /**
     * @param array{name:string,email:string,subject:string,content:string,locale:string,ip:string,user_agent:string} $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->subjectLine = $data['subject'];
        $this->content = $data['content'];
        $this->messageLocale = $data['locale'];
        $this->ip = $data['ip'];
        $this->userAgent = $data['user_agent'];
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->replyTo($this->email, $this->name)
            ->view('emails.contact_message');
    }
}
