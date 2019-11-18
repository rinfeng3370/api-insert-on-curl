<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
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
        $this->date = $this->ask('請輸入要查詢的日期("YYYY-MM-DD")');
        
        $this->verifyDate($this->date);

        $this->start_num = $this->ask('請輸入起始筆數("0或萬為單位，最多90000")');

        $this->end_num = $this->ask('請輸入最終筆數("以萬為單位，最多100000")');

        $this->verifyNum($this->start_num,$this->end_num);

        $this->time = $this->ask('請輸入查詢時間("HH:mm 24小時制")');

        $this->verifyTime($this->time);

        $this->hour = substr($this->time,0,2);

        $this->min = substr($this->time,3,2);

        $i = 0;

        for ($num = $this->start_num; $num < $this->end_num; $num += 10000) {
            $urls[$i]="http://train.rd6/?start={$this->date}T{$this->hour}:{$this->min}:00&end={$this->date}T{$this->hour}:{$this->min}:59&from={$num}";
            $i++;
        }

        QueueJob::dispatch($urls);
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
