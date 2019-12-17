<?php
namespace app\index\controller;

use Redis;
use think\App;
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
        //删除之前的缓存
        $redis = new Redis;
        $redis->connect('redis',6379);
        $redis->select(1);
        $redis->del('productList');
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

    //秒杀
    public function skill(Request $request)
    {
        //数据验证
        $rule = [
            'id' => 'require|integer',
            'number' => 'require|integer|min:1|max:1',
            'uid' => 'require|integer'
        ];
        $msg = [
            'id.require' => '商品id为必填项',
            'id.integer' => '商品id必须是整数',
            'number.require' => '购买数量为必填项',
            'number.integer' => '购买数量必须是整数',
            'number.min' => '购买数量不能小于1',
            'number.max' => '每个用户只能购买一件',
            'uid.require' => '用户id为必填项',
            'uid.integer' => '用户id必须是整数'
        ];
        $data = [
            'id' => $request->param('id'),
            'number' => $request->param('number'),
            'uid' => $request->param('uid'),
        ];
        $validate = Validate::make($rule,$msg);
        if(!$validate->check($data)){
            return json([
                'status' => false,
                'data' => $validate-> getError()
            ]);
        }
        //链接redis
        $redis = new Redis();
        $redis->connect('redis',6379);
        $redis->select(1);
        $key = 'productList_'.$data['id'];
        if($redis->exists($key)){
            $res = $redis->hGetAll($key);
            if(!$res['id']){
                return json([
                    'status' => false,
                    'data' => '商品id不存在'
                ]);
            }
        }else{
            $res = (new Goods)->find($data['id']);
            if(!$res){
                return json([
                    'status' => false,
                    'data' => '商品id不存在'
                ]);
            }
            $redis->hMSet($key,$res);
        }
        //判断该用户是否已经购买
        if($redis->sIsMember('orderList_'.$res['id'],$data['uid'])){
            return json([
                'status' => true,
                'data' => '每个用户每个商品只能抢购一件'
            ]);
        }
        //判断集合中的个数是否超标
        if($redis->sCard('orderList_'.$res['id']) <= config('skill_number')){
            $redis->sAdd('orderList_'.$res['id'],$data['uid']);
            if(!$redis->exists('order_id')){
                $redis->set('order_id',-1);
            }
            $redis->incr('order_id');
            $id = $redis->get('order_id');
            //将订单放入订单集合当中
            $redis->sAdd('orders','order_'.$id);

            $redis->hMSet('order_'.$id,[
                'id'=>null,
                'uid'=>$data['uid'],
                'pid'=>$res['id'],
                'price'=>$res['price'],
                'number'=>$data['number'],
                'created_at' => time(),
                'updated_at' => time()
            ]);
            $redis->hIncrBy('productList_'.$res['id'],'sale_num',1);
            $redis->hIncrBy('productList_'.$res['id'],'stock',-1);
            return json([
                'status' => true,
                'data' => '恭喜你,抢购成功!'
            ]);
        }else{
            return json([
                'status' => false,
                'data' => '不好意思,你来晚了!'
            ]);
        }

    }
}