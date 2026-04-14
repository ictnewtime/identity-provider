<?php

namespace App\Jobs;

use App\Models\Session;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ClearExpiredSessionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Esegue il job.
     */
    public function handle(): void
    {
        $deletedCount = Session::where("expires_at", "<", now())->delete();

        if ($deletedCount > 0) {
            Log::info("Garbage Collection: Eliminate {$deletedCount} sessioni scadute.");
        }
    }
}
