<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceDropNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly float $oldAmount,
        public readonly float $newAmount,
        public readonly string $currency,
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
            ->subject("Намалена цена: {$product->name}")
            ->greeting("Здравей, {$notifiable->first_name}!")
            ->line('Продукт от твоите любими продукти вече е на по-ниска цена.')
            ->line("{$product->name} — {$this->variant->name}")
            ->line("Нова цена: {$this->newAmount} {$this->currency} (преди: {$this->oldAmount} {$this->currency})")
            ->action('Разгледай продукта', $productUrl)
            ->line('Получаваш това известие, защото продуктът е в твоите любими.');
    }
}
