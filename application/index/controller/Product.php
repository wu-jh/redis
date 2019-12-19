<?php
namespace app\index\controller;

use Redis;
use think\Controller;
use think\Request;
use think\Validate;
use app\index\model\Goods;

class Product extends Controller
{
    //添加商品
    public function create(Request $request)
    {
        $rule = [
            'name' => 'require|chsDash',
            'content' => 'max:255',
            'price' => 'require|float',
            'stock' => 'require|integer|min:0',
        ];
        $msg = [
            'name.require' => '商品名称为必填项',
            'name.chsDash' => '商品名称只能是汉字、字母、数字和下划线_及破折号-',
            'content.max' => '商品描述的信息不能超过255个字符',
            'price.require' => '商品价格为必填项',
            'price.float' => '商品价格格式不正确',
            'stock.require' => '商品库存为必填项',
            'stock.integer' => '商品库存必须是整数',
            'stock.min' => '商品库存不能小于0'
        ];
        $data = [
            'name' => $request->param('name'),
            'content' => $request->param('content'),
            'price' => $request->param('price',0.0),
            'delete_time' => $request->param('delete_time',null),
            'stock' => $request->param('stock')
        ];
        $validate = Validate::make($rule,$msg);
        if(!$validate->check($data)){
            return json([
                'status' => false,
                'data' => $validate->getError()
                ]);
        }
        $res = (new Goods)->add($data);
        if(!$res){
            return json([
                'status' => false,
                'data' => '添加失败'
            ]);
        }
        return json([
            'status' => true,
            'data' => '添加成功'
        ]);
    }

    //查询单个商品
    public function find(Request $request)
    {
        $rule = ['id' => 'require'];
        $msg = ['id.require' => '商品id为必填项'];
        $data = ['id' => $request->param('id')];
        $validate = Validate::make($rule,$msg);
        if(!$validate->check($data)){
            return json([
                'status' => false,
                'data' => $validate->getError(),
            ]);
        }
        $res = (new Goods)->find($data['id']);
            return json([
                'status' => true,
                'data' => $res
            ]);
    }

    //查询所有商品
    public function select(Request $request)
    {
        //链接redis
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(1);
        if($redis->exists('productList')){
            $res = [];
            foreach($redis->lRange('productList',0,$redis->lLen('productList')-1) as $v){
                $res[] = $redis->hGetAll($v);
            }
        }else{
            $res = (new Goods)->select();
            foreach($res as $k => $v){
                $redis->rPush('productList','productList_'.$v['id']);
                $redis->hMSet('productList_'.$v['id'],$v);
            }
        }
        return $this->fetch('/ProductList',['data'=>$res]);

    }

    //将数据存入redis
    public function redis()
    {
        //查出参加了秒杀活动的商品
        $res = (new Goods())->skill();
        //将数据放入redis中
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(0);
        //将原来缓存链表中的数据清空
        if($redis->exists('activity_list')){
            $redis->del('activity_list');
        }
        //将所有商品信息存储起来,并将键名存入activity_list链表中
        foreach($res as $v){
            $key = 'skillProduct_'.$v['id'];
            $redis->hMSet($key,$v);
            //创建商品库存链表
            $k = 'stock_'.$v['id'];
            if($redis->exists($k)){
                $redis->del($k);
            }
            for($i = 1;$i <= $v['stock'];$i++ ){
                $redis->lpush($k,$i);
            }
            //放入参加秒杀商品的集合
//            $redis->sAdd('activity_list',$key);
            $redis->rPush('activity_list',$key);
        }
        return '商品存入redis成功';
    }

    //获取所有参加秒杀的商品
    public function getSkill()
    {
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(0);
        $res = [];
        $lsize = $redis->lLen('activity_list');
        foreach($redis->lRange('activity_list',0,$lsize-1) as $v){
            $res[] = $redis->hGetAll($v);
        }
        return $this->fetch('/ProductList',['data'=>$res]);
    }

}