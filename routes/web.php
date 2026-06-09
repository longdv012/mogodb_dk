<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-batch', function () {
    $batch = \Illuminate\Support\Facades\Bus::batch([
        new \App\Jobs\TestBatchJob(101),
        new \App\Jobs\TestBatchJob(102),
        new \App\Jobs\TestBatchJob(103),
    ])->then(function ($batch) {
        \Illuminate\Support\Facades\Log::info('Test Batch completed successfully from Route: ' . $batch->id);
    })->finally(function ($batch) {
        \Illuminate\Support\Facades\Log::info('Test Batch finished execution from Route: ' . $batch->id);
    })->dispatch();

    return "Batch Dispatched! ID: " . $batch->id;
});
Route::get('/test-single', function () {
    \App\Jobs\TestQueueJob::dispatch()->onConnection('database');
    return "Single Job Dispatched to MongoDB Queue!";
});

Route::get('/test-failed-batch', function () {
    // 1. Clear the 3 job tables in MongoDB
    // \Illuminate\Support\Facades\DB::connection('mongodb')->table('jobs')->delete();
    // \Illuminate\Support\Facades\DB::connection('mongodb')->table('failed_jobs')->delete();
    // \Illuminate\Support\Facades\DB::connection('mongodb')->table('job_batches')->delete();

    // 2. Clear Redis to ensure a clean queue
    try {
        \Illuminate\Support\Facades\Redis::connection()->flushall();
    } catch (\Exception $e) {
        // Ignore if Redis flush fails
    }

    // 3. Dispatch the batch with 3 failing jobs
    $batch = \Illuminate\Support\Facades\Bus::batch([
        new \App\Jobs\TestBatchJob(201, true),
        new \App\Jobs\TestBatchJob(202, true),
        new \App\Jobs\TestBatchJob(203),
    ])
        ->name('failed-batch-test' . rand(1, 100))
        ->allowFailures()
        ->catch(function ($batch, $e) {
            \Illuminate\Support\Facades\Log::error('Batch Job failed inside catch callback: ' . $e->getMessage());
        })
        ->then(function ($batch) {
            \Illuminate\Support\Facades\Log::info('Failed Batch finished execution: ' . $batch->id);
        })
        ->dispatch();

    return "Failed Batch Dispatched! ID: " . $batch->id;
});

