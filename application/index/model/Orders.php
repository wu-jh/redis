<?php
namespace app\index\model;

use think\Model;
use Redis;
use think\Db;

class Orders extends Model
{
    protected $table = 'order';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    //将redis中的数据存入数据库中
    public function executing()
    {
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(0);
        while (true){
            if($redis->sCard('order_list') > 0){

                Db::startTrans();
                foreach($redis->sMembers('order_list') as $v){
                    $list = $redis->hGetAll($v);
                    try {
                        //将订单数据存入数据库
                        $res1 = Db::name('order')->insert($list);
                        if(!$res1){
                            throw new \Exception();
                        }
                        //商品库存-1,销量+1
                        $res2 = Db::name('product')->where('id',$list['pid'])
                            ->inc('sale_num')
                            ->dec('stock')
                            ->data(['updated_at'=> time()])
                            ->update();
                        if(!$res2){
                            throw new \Exception();
                        }
                        $tmp = true;
                        $arr[] = $v;
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        $tmp = false;
                        break;
                    }
                }
                if($tmp){
                    Db::commit();
                    foreach($arr as $v){
                        $redis->del($v);
                        $redis->srem('order_list',$v);
                    }
                }
            }else{
                sleep(10);
            }
        }
    }
}