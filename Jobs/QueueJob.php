<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DB;

class QueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $item;

    public function __construct($item)
    {
        //
        $this->item = $item;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::table('queues')->insert($this->item);
    }
}
