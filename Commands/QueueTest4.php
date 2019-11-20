<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \Curl\Curl;
use App\Jobs\QueueJob;
use DB;

class QueueTest4 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:queueTest4';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $unis=[];
    
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
        $url="https://next.json-generator.com/api/json/get/NyXVCNAiP";
        $curl = new Curl();
        
        echo $url. "\n";
        $curl->get($url);
        if ($curl->error) {
            echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
        }
        
        echo 'Response:' . "\n";

        
        $data = json_encode($curl->response,true);
        $data = json_decode($data,true);
        
        $pass = true;
        
        $array_key = array_keys($data);   //回傳error或沒資料時不會進到處理資料的流程
        if ($array_key[0] == "error" || $data['hits']['total']['value'] == 0) {
            echo  '發生錯誤或無資料!' . "\n";
            $pass = false;
        }

        if ($pass) {
            foreach ($data['hits']['hits'] as $i => $value) {
                $data['hits']['hits'][$i]['_source'] = stripcslashes(json_encode($value['_source']));
                $data['hits']['hits'][$i]['sort'] = stripcslashes(json_encode($value['sort']));
            }
    
            $arrayLength = count($data['hits']['hits']);
            echo "資料量: $arrayLength \n";
            
            // $chunks = array_chunk($data['hits']['hits'],1000);
            
            if (false) {
                foreach ($chunks as $items) {
                    foreach ($items as $item) {
                        Queue::updateOrCreate($item);
                    }
                }
            } else {
                $count = DB::table('queues')->count();

                if (!$count) {
                    $chunks = $this->unique_multidim_array($data['hits']['hits'], "_id");
                } else {
                    
                }
                foreach ($chunks as $item) {
                    DB::table('queues')->insert($item);
                }
                foreach ($this->unis as $uni){
                    DB::table('queues')->where('_id', $uni['_id'])->update($uni);
                }
            }
        }

        $curl->close();
    }

    protected function searchRepeatArray($array, $key) 
    {
        $temp_array = [];
        $i = 0;
        $key_array = [];
       
        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            } else {
                array_push($this->unis, $val);
            }
            $i++;
        }
        return $temp_array;
    }
}
