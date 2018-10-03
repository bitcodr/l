<?php   namespace App\Console\Commands;

use Telegram\Bot\Api;
use Illuminate\Console\Command;
use App\Http\Aggregates\Start\Controller\StartController;
use App\Http\Aggregates\Bot\Controller\BotController;
use App\Http\Aggregates\User\Controller\UserController;


class Telegramcommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'telegram bot long polling';

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
        set_time_limit(0);
        $last_update = 0;
        while(true)
        {
            $telegram = new Api(config('telegram.bot_token'));
            $updates = $telegram->getUpdates();
            foreach ($updates as $key => $value) 
            {     
                if($last_update < $value['update_id'])
                {
                    $last_update = $value['update_id'];
                    
                    

                    if(isset($value['message']['text']))
                    {

                        // validate user token with sms
                        if(is_numeric($value['message']['text']))
                        {   
                            app(UserController::class)->checkAndActiveUser($telegram,$value['message']);
                            break;
                        }


                        // get botfather token with exact token
                        if(strlen($value['message']['text']) >= 45 && strlen($value['message']['text']) < 150)
                        {
                            config(['telegram.bot_token' => $value['message']['text']]);
                            $telegramBot = new Api(config('telegram.bot_token'));
                            $createdBot = $telegramBot->getMe();
                            if($createdBot->toArray() == [])
                            {
                                app(BotController::class)->botNotFound($telegram,$value['message']);
                                break;
                            }
                            app(BotController::class)->checkAndCreateBot($value['message']['text'],$createdBot,$telegram,$value['message']);
                            break;
                        }

                        // get botfather token with forwarded text in botfather
                        if(strlen($value['message']['text']) > 150)
                        {
                            $text =  explode(' ',substr($value['message']['text'], strpos($value['message']['text'], 'API:')));
                            $cleanText = str_replace(["API:","\n","For",'"'],"",$text[0]);
                            config(['telegram.bot_token' => $cleanText]);
                            $telegramBot = new Api(config('telegram.bot_token'));
                            $createdBot = $telegramBot->getMe();
                            if($createdBot->toArray() == [])
                            {
                                app(BotController::class)->botNotFound($telegram,$value['message']);
                                break;
                            }
                            app(BotController::class)->checkAndCreateBot($cleanText,$createdBot,$telegram,$value['message']);
                            break;
                        }



                        switch($value['message']['text'])
                        {
                            case trans('start.StartBot'):
                                app(StartController::class)->start($telegram,$value['message']);
                                break;
                            case trans('start.home'):
                                app(StartController::class)->start($telegram,$value['message']);
                                break;
                            case trans('start.NewBot'):
                                app(BotController::class)->newBot($telegram,$value['message']);
                                break;        
                            case trans('start.repeatSms'):
                                app(UserController::class)->repeatSms($telegram,$value['message']);
                                break;
                            case trans('start.createBotContinue'):
                                app(BotController::class)->createBot($telegram,$value['message']);
                                break;
                            default:
                                app(StartController::class)->notFound($telegram,$value['message']);
                                break;
                        }
                        

                    }

                    if(isset($value['message']['contact']))
                    {
                        app(UserController::class)->register($telegram,$value['message']);
                        break;
                    }


                }
            }
            sleep(0.1);
        }
    }



}