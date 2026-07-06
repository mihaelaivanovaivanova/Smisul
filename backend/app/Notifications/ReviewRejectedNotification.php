<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Review $review,
        public readonly ?string $reason = null,
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
        $product = $this->review->product;

        $mail = (new MailMessage)
            ->subject('Твоят отзив не бе одобрен')
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line("Отзивът ти за \"{$product->name}\" не отговаря на условията ни за публикуване и няма да бъде показан публично.");

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line("Причина: {$this->reason}");
        }

        return $mail;
    }
}
