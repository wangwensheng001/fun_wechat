<?php
/**
 * Created by PhpStorm.
 * User: zhangtaotao
 * Date: 2019/6/28
 * Time: 2:56 PM
 */

namespace app\common\service;


/**
 * 推送事件
 * 典型调用方式：
 * $push = new PushEvent();
 * $push->setUser($user_id)->push();
 *
 * Class PushEvent
 */
class PushEvent
{


    /**
     * @var string 目标用户id
     */
    protected $to_user;
 

    /**
     * @var string 推送服务地址
     */
    protected $push_api_url = 'https://dev.api.daigefan.com:2121/';//如果在服务器上127.0.0.1换成服务器上的域名：2121
 

    /**
     * @var string 推送内容
     */
    protected $content;
 

    /**
     * 设置推送用户，若参数留空则推送到所有在线用户
     *
     * @param string $user
     * @return $this
     */
    public function setUser($user = '')
    {
        $this->to_user = $user ? : '';
        return $this;
    }


    /**
     * 设置推送内容
     *
     * @param string $content
     * @return $this
     */
    public function setContent($content = '')
    {
        $this->content = $content;
        return $this;
    }

 
    /**
     * 推送
     */
    public function push()
    {
        if (!$this->content || !$this->to_user) {
            return json_error('缺少必要推送参数');
        }
        $data = [
            'type' => 'publish',
            'content' => $this->content,
            'to' => $this->to_user,
        ];
        $ch = curl_init ();
        curl_setopt($ch, CURLOPT_URL, $this->push_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $res = curl_exec($ch);
        curl_close($ch);
    }

}