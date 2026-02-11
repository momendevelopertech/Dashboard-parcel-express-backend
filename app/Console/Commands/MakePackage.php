<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MakePackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:package {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a complete Package';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $this->info("Creating Package: {$name}");

        Artisan::call("make:model {$name} -mcsf", [], $this->getOutput());
        Artisan::call("make:resource {$name}Resource", [], $this->getOutput());
        Artisan::call("make:observer {$name}Observer", [], $this->getOutput());
        Artisan::call("make:request Store{$name}Request", [], $this->getOutput());
        Artisan::call("make:request Update{$name}Request", [], $this->getOutput());

        $this->info("Model {$name} created successfully.");
        return 0;
    }
}
