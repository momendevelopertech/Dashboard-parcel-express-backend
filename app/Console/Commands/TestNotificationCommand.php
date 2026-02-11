<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNotificationCommand extends Command
{
    protected $signature = 'notification:test 
                            {--user= : ID of the user to send notification to}
                            {--title= : Notification title}
                            {--content= : Notification content}
                            {--read : Mark as read}';
    protected $description = 'Test notification creation';
    public function handle()
    {
        // Get or create test user
        $userId = $this->option('user') ?? Merchant::first()?->user_id;

        if (!$userId) {
            $this->error('No user found. Please provide --user option or run MerchantSeeder first.');
            return 1;
        }

        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $title = $this->option('title') ?? 'Test Notification';
        $content = $this->option('content') ?? 'This is a test notification created at ' . now()->format('Y-m-d H:i:s');
        $markAsRead = $this->option('read');

        $this->info("Creating notification for user: {$user->name} (ID: {$user->id})");
        $this->line("Title: {$title}");
        $this->line("Content: {$content}");
        $this->line("Mark as read: " . ($markAsRead ? 'Yes' : 'No'));

        try {
            // Use our global function
            $notification = create_notification(
                $user,
                $title,
                $content,
                ['source' => 'test_command'],
                null,
                $markAsRead
            );
            $this->info("Notification created successfully!");
            $this->line("Notification ID: {$notification->id}");
            $this->line("\nBroadcasting details:");
            Log::info('Test notification created', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'title' => $title,
            ]);
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create notification: " . $e->getMessage());
            Log::error('Test notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return 1;
        }
    }
}
