<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DB;
use \Curl\Curl;
use Log;

class QueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $urls;

    protected $retry = 0;


    public function __construct($urls)
    {
        //
        $this->urls = $urls;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach($this->urls as $url){
            $curl = new Curl();

            echo $url. "\n";

            $curl->get($url);

            if ($curl->error) {   //如果發生錯誤會在重試3次 如果都失敗會把網址記到log裡
                echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
                while ($curl->error && $this->retry < 3) {
                    $this->retry ++;
                    echo $this->retry. "\n";
                    $curl->get($url);
                }
                if ($curl->error && $this->retry == 3){
                    Log::warning("遺漏的單:{$url}");
                    die();
                }
            }
            
            echo 'Response:' . "\n";

            $data = json_decode($curl->response,true);

            $array_key = array_keys($data);   //回傳error或沒資料時停止
            if ($array_key[0] == "error" || $data['hits']['total']['value'] == 0) {
                $this->error('發生錯誤或無資料!');
                die();
            }

            foreach($data['hits']['hits'] as $i => $value){
                $data['hits']['hits'][$i]['_source'] = stripcslashes(json_encode($value['_source']));
                $data['hits']['hits'][$i]['sort'] = stripcslashes(json_encode($value['sort']));
            }

            $arrayLength = count($data['hits']['hits']);
            echo "資料量: $arrayLength \n";
            
            $items = array_chunk($data['hits']['hits'],1000);

            foreach($items as $item){
                DB::table('queues')->insert($item);
            }
            
            $curl->close();
        }
    }
}
