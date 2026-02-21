<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ny booking-forespørgsel')
            ->greeting('Hej '.$notifiable->name)
            ->line('Du har modtaget en ny booking-forespørgsel.')
            ->line('Booking: '.$this->booking->title)
            ->line('Virksomhed: '.($this->booking->company?->name ?? '-'))
            ->line('Start: '.$this->booking->starts_at?->format('d-m-Y H:i'))
            ->line('Slut: '.$this->booking->ends_at?->format('d-m-Y H:i'))
            ->action('Se forespørgsler', url('/employee/requests'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking_request',
            'title' => 'Ny booking-forespørgsel',
            'message' => 'Du har modtaget en forespørgsel: '.$this->booking->title,
            'booking_id' => $this->booking->id,
            'url' => '/employee/requests',
        ];
    }
}
