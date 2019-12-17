<?php
namespace app\common\command;

use app\index\controller\Product;
use app\index\model\Goods;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\index\model\Orders;
use Redis;

class Executing extends Command
{
    protected function configure()
    {
        $this->setName('executing')
             ->setDescription('将缓存中的数据存入数据库中');
    }

    protected function execute(Input $input, Output $output)
    {
        (new Orders())->executing();
//        $output->writeln('executing ok');
    }
}