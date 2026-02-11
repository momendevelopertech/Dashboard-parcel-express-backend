<?php

namespace App\Console\Commands;

use App\Jobs\DeliverScheduledMessage;
use App\Models\ScheduledMessage;
use Illuminate\Console\Command;

class DeliverScheduledMessages extends Command
{
    protected $signature = 'scheduled:deliver-messages';
    protected $description = 'Deliver scheduled messages that are due';

    public function handle()
    {
        $messages = ScheduledMessage::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($messages as $message) {
            DeliverScheduledMessage::dispatch($message);
        }

        $this->info('Scheduled messages processed');
    }
}
