<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CryptSourceId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dwh:crypt-source-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Encrypted Source ID for API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->ask('What is the source name?');
        $query = DB::select("SELECT id FROM dwh_sources WHERE name = ?", [$name]);
        if (count($query) === 1) {
            return Crypt::encrypt($query[0]->id);
        } else {
            return 0;
        }
    }
}