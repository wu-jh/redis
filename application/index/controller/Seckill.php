<?php
namespace app\index\controller;

use think\Controller;
use think\Request;
use think\Validate;
use Redis;

class Seckill extends Controller
{
    public function index(Request $request)
    {
        //数据验证
        $rule = [
            'uid' => 'require|integer|min:0',
            'pid' => 'require|integer|min:0',
        ];
        $msg = [
            'uid.require' => '用户id为必填项',
            'uid.integer' => '用户id必须是数字',
            'uid.min' => '用户id不能小于0',
            'pid.require' => '商品id为必填项',
            'pid.integer' => '商品id必须是数字',
            'pid.min' => '商品id不能小于0',
        ];
        $data = [
            'uid' => $request->param('uid'),
            'pid' => $request->param('pid'),
        ];

        $validate = Validate::make($rule,$msg);
        if(!$validate->check($data)){
            return json([
                'status' => false,
                'data' => $validate->getError()
            ]);
        }
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(0);
        $key = 'skillProduct_'.$data['pid'];
        //判断商品是不是秒杀商品
        $lsize = $redis->lLen('activity_list');
        $tmp = false;
        foreach($redis->lRange('activity_list',0,$lsize-1) as $v){
            if($key === $v){
                $tmp = true;
                break;
            }
        }
        if(!$tmp){
            return json([
                'status' => false,
                'data' => '您选购的商品没有参加秒杀活动'
            ]);
        }

        //检测用户是否已经购买
        if($redis->sismember('sold_'.$data['pid'],$data['uid'])){
            return json([
                'status' => false,
                'data' => '每个用户每件商品只能抢购一件'
            ]);
        }

        //开始抢购
        $res = $redis->rPop('stock_'.$data['pid']);
        if(!$res){
            return json([
                'status' => false,
                'data' => '不好意思,您来晚了,商品已卖完'
            ]);
        }
        //商品库存-1
        $redis->hincrby($key,'stock',-1);
        //销量+1
        $redis->hincrby($key,'sale_num',1);
        //将用户id放入商品集合中
        $redis->sAdd('sold_'.$data['pid'],$data['uid']);
        //创建订单信息哈希数据
        $orderNum = date('Ymd').str_shuffle(uniqid());//订单号
        //创建虚拟订单id
        if(!$redis->exists('oid')){
            $redis->set('oid',-1);
        }
        $oid = $redis->incrby('oid',1);
        $redis->hMset('order_'.$oid,[
            'uid' => $data['uid'],
            'pid' => $data['pid'],
            'orderNum' => $orderNum,
            'price' => $redis->hget($key,'price'),
            'number' => 1,
            'created_at' => time(),
            'updated_at' => time()
        ]);
        //将订单信息存入订单列表当中
        $redis->sAdd('order_list','order_'.$oid);
        return json([
            'status' => true,
            'data' => '恭喜您,抢购成功!'
        ]);
    }
}