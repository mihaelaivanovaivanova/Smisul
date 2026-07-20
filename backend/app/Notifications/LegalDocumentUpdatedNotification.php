<?php

namespace App\Notifications;

use App\Models\LegalDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalDocumentUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LegalDocument $document,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentUrl = rtrim(config('app.frontend_url'), '/')."/legal/{$this->document->type->slug()}";

        return (new MailMessage)
            ->subject("Актуализирахме нашите {$this->document->title}")
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line("{$this->document->title} на Smisul бяха актуализирани до версия {$this->document->version}, в сила от {$this->document->published_at->format('d.m.Y')}.")
            ->line('Продължавайки да използвате профила си, е необходимо да се запознаете и приемете актуалната версия.')
            ->action('Преглед на документа', $documentUrl)
            ->line('Получавате това известие, защото имате регистриран профил в Smisul.');
    }
}
