<?php

namespace App\Notifications;

use App\Models\TransferTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransferTaskCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transferTask;

    /**
     * Create a new notification instance.
     */
    public function __construct(TransferTask $transferTask)
    {
        $this->transferTask = $transferTask;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transfer_task_created',
            'title' => 'New Transfer Task Created',
            'body' => "A new transfer task #{$this->transferTask->id} has been created.",
            'id' => $this->transferTask->id,
            'origin_type' => $this->transferTask->origin_type,
            'origin_id' => $this->transferTask->origin_id,
        ];
    }
}
