<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?User $approvedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking er godkendt')
            ->greeting('Hej '.$notifiable->name)
            ->line('En booking er blevet godkendt.')
            ->line('Booking: '.$this->booking->title)
            ->line('Virksomhed: '.($this->booking->company?->name ?? '-'))
            ->line('Godkendt af: '.($this->approvedBy?->name ?? 'Admin'))
            ->line('Start: '.$this->booking->starts_at?->format('d-m-Y H:i'))
            ->line('Slut: '.$this->booking->ends_at?->format('d-m-Y H:i'))
            ->action('Aabn dashboard', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking_approved',
            'title' => 'Booking godkendt',
            'message' => 'Booking "'.$this->booking->title.'" er godkendt.',
            'booking_id' => $this->booking->id,
            'approved_by' => $this->approvedBy?->name,
            'url' => '/dashboard',
        ];
    }
}
