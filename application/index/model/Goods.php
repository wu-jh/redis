<?php
namespace app\index\model;

use think\Model;

class Goods extends Model
{
    protected $table = 'product';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    //添加商品
    public function add(array $arr)
    {
        $res = self::save($arr);
        return $res;
    }

    //查询所有商品
    public function select()
    {
        $res = self::all();
        return $res->toArray();
    }
    //查询单个商品
    public function find($id)
    {
        $res = self::get($id);
        return $res->toArray();
    }

    //查询参加秒杀的商品
    public function skill()
    {
        $res = $this->where('skill',config('skill_on'))->all();
        return $res->toArray();
    }

}