<?php

namespace app\http\middleware;

use think\Request;
use app\common\model\RiderInfo;

class RiderAuth
{
    public function handle($request, \Closure $next)
    {
        $uid = $request->header('rider-auth');
        if (!$uid) {
            return json_error('参数出错，暂无登录', 205);
        } 
        
        $result = RiderInfo::find($uid);
        if (!$result) {
            return json_error('未查到该用户',201);
        }

        return $next($request);
    }
}
