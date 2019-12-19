<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

//查询单个商品
Route::get('product/find','Product/find');
//查询所有商品
Route::get('product/select','Product/select');
//获取所有参加秒杀的商品
Route::get('product/seckill','Product/getSkill');
//数据存入redis
Route::get('product/redis','product/redis');

return [
    
];
