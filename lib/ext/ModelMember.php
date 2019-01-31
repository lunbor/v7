<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 *
 * 用户模型
 * 保证用户名的唯一性，请对用户名索引成unique
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait ModelMember {
    /**
     * 配置，使用的类配置
     * @var array
     */
    /*
      protected static $configs = [
      'cookieName' => 'vapp_member', // cookie名称
      'saltKey' => 'i_erY2~3/t,7S',  // 加密key
      'onlyOne' => true, // 是否只允许唯一终端
      'failnum' => 6,
      'failsec' => 600,
      ]; */

    /**
     * cookie的名字
     * @var string
     */
    protected $cookieName = null;

    /**
     * 当前登陆用户
     * @var array
     */
    protected $loginer = null;

    /**
     * 取得用户标识cookie的名称
     * @return string
     */
    protected function cookieName() {
        if (is_null($this->cookieName)) {
            $name = $this->conf('cookieName');
            if (empty($name)) {
                $name = strtr(get_class($this), ['\\' => '_']);
            }
            $this->cookieName = strtolower($name);
        }
        return $this->cookieName;
    }

    /**
     * 取得当前登录用户
     * @param string $field 用户字段
     * @return array
     */
    public function loginer($field = null) {
        if (is_null($this->loginer)) {
            $this->loginer = false;
            // 先从session中取得用户数据
            $cookieName = $this->cookieName();
            $onlyTerminal = $this->conf('onlyOne', false);
            $terminal = v\Req::isMobile() ? 'mobile' : 'pc';
            $userToken = v\Req::cookie($cookieName);
            $user = v\Session::get("logined_member_$cookieName");
            if (empty($userToken)) {
                // 无用户令牌
                $user = false;
            } elseif (!empty($user)) {
                // session中已有用户判断用户的合法性
                if ($userToken === array_value($user, "token.$terminal")) {
                    if (!$onlyTerminal) {
                        $this->loginer = $user;
                    } else {
                        // 仅限一个终端登陆，重新判断session是否合法
                        $user = $this->getByID($user['_id']);
                        if (session_id() !== array_value($user, "session.$terminal")) {  // 已在其他终端登陆
                            v\Err::add('You have landed at another termina', 403);
                        } else {
                            $this->loginer = $user;
                        }
                    }
                }
            } else {
                // session中无用户从令牌中取得用户
                $tokenStr = v\kit\Mcrypt::setKey($this->conf('saltKey'))->deAesCBC($userToken);
                if (!empty($tokenStr)) {
                    $cks = explode("\t", $tokenStr);
                    if (count($cks) >= 3 && intval($cks[2]) >= NOW_TIME && $cks[1] === md516(v\Req::ua())) {
                        $user = $this->getByID($cks[0]);
                        if (!empty($user)) {
                            if ($onlyTerminal && $userToken !== array_value($user, "token.$terminal")) {
                                // token不对应，已在另外终端登陆
                                v\Err::add('You have landed at another termina', 403);
                            } else {
                                // 登陆成功
                                v\Session::anew();
                                $user = $this->data(['session' => ["$terminal" => session_id()]])->field('*')->upByID($user['_id']);  // 更新用户session并取得数据
                                unset($user['password']);
                                v\Session::set("logined_member_$cookieName", $user);
                                $this->loginer = $user;
                            }
                        }
                    }
                }
            }
            if (empty($this->loginer)) {
                $this->logout();  // 如果cookie非法清除客户端cookie，避免多次解密COOKIE
            }
        }
        return empty($this->loginer) ? false : empty($field) ? $this->loginer : array_value($this->loginer, $field);
    }

    /**
     * 是否未登陆
     * @return boolean
     */
    public function isGuest() {
        $id = $this->loginer('_id');
        return empty($id);
    }

    /**
     * 是否已经登陆
     * @return boolean
     */
    public function isLogined() {
        return !$this->isGuest();
    }

    /**
     * 是否绝对安全
     * 需要验证原始IP与原始userAgent
     * @return boolean
     */
    public function isSecurity() {
        if (!$this->isGuest()) {
            $userAgent = md516(v\Req::ua());
            $ip = v\Req::ip();
            if ($userAgent === array_value($this->loginer, 'loginua') && $ip === array_value($this->loginer, 'loginip')) {
                return true;
            }
        }
        return false;
    }

    /**
     * 记录密码错误信息
     * @param array $user 登陆的用户信息
     * @return $this
     */
    public function errorLogin(&$user) {
        array_extend($user, ['lastfail' => 0, 'failnum' => 0], false);  // 赋初值
        $udata = ['lastfail' => NOW_TIME];
        if (!empty($user['lastfail']) && NOW_TIME - $user['lastfail'] > $this->config('failsec', 1)) {
            // 过错误持续时间后重置错误次数
            $udata['failnum'] = 1;
        } else {
            $udata['$inc'] = ['failnum' => 1];
        }
        return $this->data($udata)->upByID($user['_id']);
    }

    /**
     * 检查用户是否被允许登陆
     * @param array $user
     * @return $this
     */
    public function canLogin(&$user) {
        if (isset($user['status']) && $user['status'] <= 0) {
            // 用户被禁用
            v\Err::add("Member [{$user['username']}] is disabled", 'username', 403);
            return false;
        }
        array_extend($user, ['lastfail' => 0, 'failnum' => 0], false);  // 赋初值
        $failnum = $this->config('failnum', 6);
        $failsec = $this->config('failsec', 600);
        if ($user['failnum'] >= $failnum && NOW_TIME - $user['lastfail'] < $failsec) {
            // 超出允许的次数
            $willsec = $failsec - (NOW_TIME - $user['lastfail']);
            v\Err::add("Beyond login number [$failnum], please [$willsec] second retry", 'password', 403);
            return false;
        }
        return true;
    }

    /**
     * 登录用户, 分配用户的session
     * 请登录前先验证用户的合法性
     * useragent与ip地址可以再关键时候验证用户合法性
     * 
     * @param array $user 用户数据，必须有用户ID
     * @param int $expireDay 过期时间天
     * @reutrn string 用户登陆的令牌
     */
    public function keepLogin(&$user, $expireDay = null) {
        $userAgent = md516(v\Req::ua());
        // 始终set cookie，便于多站点联合登陆
        if (is_null($expireDay)) {  // session保持
            $expireTime = 0;
            $expiryTime = NOW_TIME + 86400;
        } else {  // 长久保持
            if ($expireDay >= NOW_TIME) {
                $expiryTime = $expireDay;
                $expireTime = $expiryTime - NOW_TIME;
            } else {
                $expireTime = $expireDay * 86400;
                $expiryTime = NOW_TIME + $expireTime;
            }
        }

        $terminal = v\Req::isMobile() ? 'mobile' : 'pc';
        $token = v\kit\Mcrypt::setKey($this->conf('saltKey'))->enAesCBC("{$user['_id']}\t$userAgent\t$expiryTime");
        v\Session::anew(); //重新生成session_id
        // 存入数据库
        $udata = [
            'lastlogin' => NOW_TIME,
            'prevlogin' => array_value($user, 'lastlogin', NOW_TIME),
            'loginip'   => v\Req::ip(),
            'loginua'   => $userAgent,
            'failnum'   => 0, // 错误次数重置为0
            '$inc'      => ['loginnum' => 1],
            'token'     => [$terminal => $token], // 分别保存pc与mobile的令牌
            'session'   => [$terminal => session_id()]  // 分别保存pc与mobile的sessionid
        ];
        $user = $this->data($udata)->field('*')->upByID($user['_id']);
        unset($user['password']);
        $this->loginer = $user;

        $cookieName = $this->cookieName();
        v\Session::set("logined_member_$cookieName", $user);  // set session
        v\Res::cookie($cookieName, $token, $expireTime, array_value(session_get_cookie_params(), 'domain'));  // set cookie

        return "$cookieName=$token";
    }

    /**
     * 用户登出
     * @return $this;
     */
    public function logout() {
        $cookieName = $this->cookieName();
        v\Session::del("logined_member_$cookieName");  // set session
        v\Res::cookie($cookieName, 0, 0, array_value(session_get_cookie_params(), 'domain'));
        return $this;
    }

}
