<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use \Curl\Curl;
use App\Jobs\QueueJob;
use DateTime;

class QueueTest2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:queueTest2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $retry = 0;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $curl = new Curl();

        $date = date("Y-m-d");

        $hour = date("H");
        
        $min = date("i");

        if ($hour == "00" && $min == "00") {   //換日處理
            $hour = "23";
            $min = "59";
            $date = new DateTime('now');
            date_modify($date, '-1 day');
            $date = $date->format('Y-m-d');
        } elseif ($min == "00") {              //整點處理
            $hour = substr(($hour-1)+100,1,2);
            $min = 59;
        } else {
            $min = substr(($min-1)+100,1,2);
        }     

        $url = "http://train.rd6/?start={$date}T{$hour}:{$min}:00&end={$date}T{$hour}:{$min}:59&from=10000";
        echo $url. "\n";
        
        $curl->get($url);

        if ($curl->error) {   //如果發生錯誤會在重試3次 如果都失敗會把網址記到log裡
            echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
            while ($curl->error && $this->retry < 4) {
                $this->retry ++;
                echo $this->retry;
                $curl->get($url);
            }
            if ($curl->error && $this->retry == 3) {
                Log::warning("遺漏的單:{$url}");
                die();
            }
        }
            
        echo 'Response:' . "\n";
        echo "起始筆數: 0 \n";

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
        
        $items = array_chunk($data['hits']['hits'],1000); //每次分成1000筆推至隊列
        foreach($items as $item){
            QueueJob::dispatch($item);
        }
            
        $curl->close();

    }
}
