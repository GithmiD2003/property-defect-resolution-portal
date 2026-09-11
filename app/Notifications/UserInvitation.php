<?php

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(
        public string $acceptUrl,
        public UserRole $role,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation to the Property Defect Portal')
            ->greeting('You have been invited!')
            ->line(
                'You have been invited to join the Property Defect Portal as an '
                .$this->role->value.'.'
            )
            ->line('Use the button below to set your name and password.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation expires after 48 hours and can be used once.')
            ->line('If you were not expecting this invitation, you can ignore it.');
    }
}
