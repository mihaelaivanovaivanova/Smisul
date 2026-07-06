<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Review $review,
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
        $productUrl = rtrim(config('app.frontend_url'), '/')."/products/{$product->slug}";

        return (new MailMessage)
            ->subject('Твоят отзив е одобрен')
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line("Твоят отзив за \"{$product->name}\" вече е одобрен и е видим за останалите клиенти.")
            ->action('Виж продукта', $productUrl);
    }
}
