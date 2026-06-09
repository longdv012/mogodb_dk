<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestBatchJob implements ShouldQueue
{
    use Queueable, Batchable;

    protected $jobId;
    protected $shouldFail;

    /**
     * Create a new job instance.
     */
    public function __construct($jobId, $shouldFail = false)
    {
        $this->jobId = $jobId;
        $this->shouldFail = $shouldFail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new \Exception("Intentionally failing job: {$this->jobId}");
        }

        Log::info("TestBatchJob executed: {$this->jobId} inside Batch: " . $this->batchId);
    }
}
