<?php
Route::group('api',function(){
    //添加商品
    Route::post('admin/product/create','Product/create');
    //秒杀
    Route::post('seckill','Seckill/index');
});
