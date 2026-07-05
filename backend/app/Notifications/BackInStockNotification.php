<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackInStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProductVariant $variant,
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
        $product = $this->variant->product;
        $productUrl = rtrim(config('app.frontend_url'), '/')."/products/{$product->slug}";

        return (new MailMessage)
            ->subject("Отново в наличност: {$product->name}")
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line('Продукт от твоите любими продукти вече е отново в наличност.')
            ->line("{$product->name} — {$this->variant->name}")
            ->action('Разгледай продукта', $productUrl)
            ->line('Получаваш това известие, защото продуктът е в твоите любими.');
    }
}
