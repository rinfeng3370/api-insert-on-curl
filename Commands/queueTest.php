<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use \Curl\Curl;
use App\Jobs\QueueJob;

class QueueTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:queueTest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $date;

    protected $start_num;

    protected $end_num;

    protected $time;

    protected $hour;

    protected $min;


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
        if (!$this->date && !$this->start_num && !$this->end_num) {    //避免需要重複輸入
            $this->date = $this->ask('請輸入要查詢的日期("YYYY-MM-DD")');
            
            $this->verifyDate($this->date);

            $this->start_num = $this->ask('請輸入起始筆數("0或萬為單位，最多90000")');

            $this->end_num = $this->ask('請輸入最終筆數("以萬為單位，最多100000")');

            $this->verifyNum($this->start_num,$this->end_num);

            $this->time = $this->ask('請輸入查詢時間("HH:mm 24小時制")');

            $this->verifyTime($this->time);

            $this->hour = substr($this->time,0,2);

            $this->min = substr($this->time,3,2);
        }

        $curl = new Curl();

        $url = "http://train.rd6/?start={$this->date}T{$this->hour}:{$this->min}:00&end={$this->date}T{$this->hour}:{$this->min}:59&from={$this->start_num}";
        echo $url. "\n";

        if ($curl->error) {   //如果發生錯誤會在重試3次 如果都失敗會把網址記到log裡
            echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
            while ($curl->error && $this->retry < 4) {
                $this->retry ++;
                echo $this->retry;
                $curl->get($url);
            }
            if ($curl->error && $this->retry == 3){
                Log::warning("遺漏的單:{$url}");
                die();
            }
        }
        
        echo 'Response:' . "\n";
        echo "起始筆數: {$this->startNum} \n";

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
            QueueJob::dispatch($item);
        }
        
        $curl->close();

        if($this->start_num == ($this->end_num-10000)){  //到最終筆數時停止
            die();
        }

        $this->start_num += 10000;
        $this->call('command:queueTest');
    }

    protected function verifyDate($date)  
    {
        if (!preg_match('/^2[0]\d{2}$/',substr($date,0,4))) {  //驗證年份
            $this->error('請輸入正確年份!');
            die();
        }

        if (!preg_match('/^0\d|1[0-2]$/',substr($date,5,2))) {   //驗證月份  
            $this->error('請輸入正確月份!');
            die();
        }
        
        $day = substr($date,8,2);

        switch (substr($date,5,2)) {      //驗證日期
            case "01": case "03": case "05": case "07": case "08": case "10": case "12":
                $days = 31;
                if(!preg_match('/^0[1-9]|[1-2]\d|3[0-1]$/',substr($date,8,2))){     
                    $this->error('請輸入正確日期!');
                    die();
                }
                break;
            case "04": case "06": case "09": case "11":
                $days = 30;
                if(!preg_match('/^0[1-9]|[1-2]\d|30$/',substr($date,8,2))){     
                    $this->error('請輸入正確日期!');
                    die();
                }
                break;
            case "02":
                $days = 28;
                if(!preg_match('/^0[1-9]|[1-2]\d$/',substr($date,8,2))){     
                    $this->error('請輸入正確日期!');
                    die();
                }
                break;
            default:
                $days = -1;
        }
    }

    protected function verifyNum($start_num, $end_num)  //驗證筆數
    {
        if (!preg_match('/^0|[1-9]0000$/',$start_num)) {     
            $this->error('請輸入正確起始筆數!');
            die();
        }

        if (!preg_match('/^[1-9]0000|100000$/',$end_num)) {     
            $this->error('請輸入正確最終筆數!');
            die();
        }
    }

    protected function verifyTime($time)  //驗證時間
    {
        if (!preg_match('/^([0-1]\d|2[0-3]):[0-5]\d$/',$time)) {     
            $this->error('請輸入正確時間格式!');
            die();
        }
    }
}
