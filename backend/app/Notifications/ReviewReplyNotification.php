<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewReplyNotification extends Notification
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
            ->subject('Получи отговор на твоя отзив')
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line("Получи отговор от екипа ни на отзива ти за \"{$product->name}\":")
            ->line("\"{$this->review->admin_reply}\"")
            ->action('Виж продукта', $productUrl);
    }
}
