<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CopyColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "app:copy-columns {fromTable} {fromColumn} {toColumn}";
    // esempio php artisan app:copy-columns fromTableName fromColumnName toColumnName
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Given a table name, copy columns from table";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // example php artisan app:copy-column users email username
        $fromTable = $this->argument("fromTable");
        $fromColumn = $this->argument("fromColumn");
        $toColumn = $this->argument("toColumn");

        try {
            DB::update(
                "update " .
                    $fromTable .
                    " set " .
                    $toColumn .
                    " = " .
                    $fromColumn .
                    " where " .
                    $toColumn .
                    " is null or " .
                    $toColumn .
                    " = ''",
            );
            $this->info("Copied " . $fromColumn . " to " . $toColumn);
        } catch (\Throwable $th) {
            $this->error("Error: " . $th->getMessage());
            return;
        }
    }
}
