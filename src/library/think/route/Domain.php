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
declare (strict_types = 1);

namespace think\route;

use think\App;
use think\Container;
use think\Request;
use think\Route;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;

class Domain extends RuleGroup
{
    protected $bind;

    /**
     * 架构函数
     * @access public
     * @param  Route       $router   路由对象
     * @param  string      $name     路由域名
     * @param  mixed       $rule     域名路由
     * @param  array       $option   路由参数
     */
    public function __construct(Route $router, string $name = '', $rule = null, array $option = [])
    {
        $this->router = $router;
        $this->domain = $name;
        $this->option = $option;
        $this->rule   = $rule;
    }

    /**
     * 检测域名路由
     * @access public
     * @param  Request      $request  请求对象
     * @param  string       $url      访问地址
     * @param  string       $depr     路径分隔符
     * @param  bool         $completeMatch   路由是否完全匹配
     * @return Dispatch|false
     */
    public function check(Request $request, string $url, bool $completeMatch = false)
    {
        // 检测别名路由
        $result = $this->checkRouteAlias($request, $url);

        if (false !== $result) {
            return $result;
        }

        // 检测URL绑定
        $result = $this->checkUrlBind($request, $url);

        if (!empty($this->option['append'])) {
            $request->setRoute($this->option['append']);
            unset($this->option['append']);
        }

        if (false !== $result) {
            return $result;
        }

        // 添加域名中间件
        if (!empty($this->option['middleware'])) {
            Container::get('middleware')->import($this->option['middleware']);
            unset($this->option['middleware']);
        }

        return parent::check($request, $url, $completeMatch);
    }

    /**
     * 设置路由绑定
     * @access public
     * @param  string     $bind 绑定信息
     * @return $this
     */
    public function bind(string $bind)
    {
        $this->bind = $bind;
        $this->router->bind($bind, $this->domain);

        return $this;
    }

    /**
     * 检测路由别名
     * @access private
     * @param  Request   $request
     * @param  string    $url URL地址
     * @return Dispatch|false
     */
    private function checkRouteAlias(Request $request, string $url)
    {
        $alias = strpos($url, '|') ? strstr($url, '|', true) : $url;

        $item = $this->router->getAlias($alias);

        return $item ? $item->check($request, $url) : false;
    }

    /**
     * 检测URL绑定
     * @access private
     * @param  Request   $request
     * @param  string    $url URL地址
     * @return Dispatch|false
     */
    private function checkUrlBind(Request $request, string $url)
    {
        if (!empty($this->bind)) {
            $bind = $this->bind;
            $this->parseBindAppendParam($bind);

            // 记录绑定信息
            Container::get('app')->log('[ BIND ] ' . var_export($bind, true));

            // 如果有URL绑定 则进行绑定检测
            $type = substr($bind, 0, 1);
            $bind = substr($bind, 1);

            $bindTo = [
                '\\' => 'bindToClass',
                '@'  => 'bindToController',
                ':'  => 'bindToNamespace',
            ];

            if (isset($bindTo[$type])) {
                return $this->{$bindTo[$type]}($request, $url, $bind);
            }
        }

        return false;
    }

    protected function parseBindAppendParam(string &$bind): void
    {
        if (false !== strpos($bind, '?')) {
            list($bind, $query) = explode('?', $bind);
            parse_str($query, $vars);
            $this->append($vars);
        }
    }

    /**
     * 绑定到类
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $class 类名（带命名空间）
     * @return CallbackDispatch
     */
    protected function bindToClass(Request $request, string $url, string $class): CallbackDispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->router->config('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new CallbackDispatch($request, $this, [$class, $action]);
    }

    /**
     * 绑定到命名空间
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $namespace 命名空间
     * @return CallbackDispatch
     */
    protected function bindToNamespace(Request $request, string $url, string $namespace): CallbackDispatch
    {
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : $this->router->config('default_controller');
        $method = !empty($array[1]) ? $array[1] : $this->router->config('default_action');

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2]);
        }

        return new CallbackDispatch($request, $this, [$namespace . '\\' . App::parseName($class, 1), $method]);
    }

    /**
     * 绑定到控制器
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $controller 控制器名
     * @return ControllerDispatch
     */
    protected function bindToController(Request $request, string $url, string $controller): ControllerDispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->router->config('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ControllerDispatch($request, $this, $controller . '/' . $action);
    }

}
