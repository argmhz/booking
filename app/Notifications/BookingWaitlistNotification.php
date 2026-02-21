<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingWaitlistNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly int $position,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Du er sat pa venteliste')
            ->greeting('Hej '.$notifiable->name)
            ->line('Du er sat pa venteliste til en booking.')
            ->line('Booking: '.$this->booking->title)
            ->line('Placering: #'.$this->position)
            ->line('Virksomhed: '.($this->booking->company?->name ?? '-'))
            ->action('Se foresporgsler', url('/employee/requests'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking_waitlist',
            'title' => 'Sat pa venteliste',
            'message' => 'Du er pa venteliste (#'.$this->position.') for '.$this->booking->title,
            'booking_id' => $this->booking->id,
            'position' => $this->position,
            'url' => '/employee/requests',
        ];
    }
}
