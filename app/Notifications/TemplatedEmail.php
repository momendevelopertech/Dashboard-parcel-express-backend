<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\EmailTemplate;
use Illuminate\Support\Str;

class TemplatedEmail extends Notification
{
    use Queueable;

    public function __construct(protected string $templateKey, protected array $vars = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tpl = EmailTemplate::where('key', $this->templateKey)
            ->where('is_active', true)
            ->first();

        $subject = $tpl?->subject ?? 'Welcome';
        $body = $tpl?->body ?? 'Hello {{receiver_name}}';

        [$subject, $body] = $this->compile($subject, $body, $this->vars);

        $html = Str::markdown($body);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.raw', ['html' => $html]);
    }

    private function compile(string $subject, string $body, array $vars): array
    {
        $search = [];
        $replace = [];
        foreach ($vars as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }
        return [
            str_replace($search, $replace, $subject),
            str_replace($search, $replace, $body)
        ];
    }
}
