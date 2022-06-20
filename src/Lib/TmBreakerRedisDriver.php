<?php
/**
 * -------------------------------------------------------------------------------------
 * Copyright (c) 2014-2017 Beijing Chinaway Technologies Co., Ltd. All rights reserved.
 * -------------------------------------------------------------------------------------
 * TmProxy通用简易熔断器TmBreaker基于Redis存储操作类
 *
 * PHP version 5.4.0 or above
 *
 * @category TmBreaker
 * @package  TmProxy
 * @author   Wolf Wang <wangyu@g7.com.cn>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
namespace TmProxy\TmBreaker\Lib;
use Exception;
use Redis;
use TmProxy\TmBreaker\Lib\TmBreakerDataDriverAbstract;
/**
 * TmBreaker基于Redis存储操作类
 *
 * @category TmBreaker
 * @package  TmProxy
 * @author   Wolf Wang <wangyu@g7.com.cn>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
class TmBreakerRedisDriver extends TmBreakerDataDriverAbstract
{
    /**
     * 服务键名前缀
     *
     * @var const string
     */
    const KEY_PREFIX = 'tmb_';
    
    /**
     * Redis客户端
     *
     * @var private object
     */
    private $_client;
    
    /**
     * Redis驱动构造函数
     *
     * @param string $host     redis地址
     * @param int    $port     redis端口号
     * @param string $password redis密码
     * @param float  $timeout  redis连接超时时间
     *
     * @return none
     */
    public function __construct($host, $port = 6379, $password = null, $timeout = 0.04)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('redis extension not found');
        }
        
        $this->_client = new Redis;
        $this->_client->connect($host, $port, $timeout);
        $this->_client->auth($password);
    }
    
    /**
     * 熔断器是否打开
     *
     * @return boolean
     */
    public function isOpen()
    {
        $key = $this->_getPrefixKey($this->key . '_isOpen');
        return (boolean)$this->_client->get($key);
    }
    
    /**
     * 获得成功请求数
     *
     * @return int
     */
    public function getSuccessfulRequests()
    {
        $key = $this->_getPrefixKey($this->key . '_successfulRequests');
        return $this->_client->get($key);
    }
    
    /**
     * 获得失败请求数
     *
     * @return int
     */
    public function getFailedRequests()
    {
        $key = $this->_getPrefixKey($this->key . '_failedRequests');
        return $this->_client->get($key);
    }
    
    /**
     * 重置成功请求数
     *
     * @return none
     */
    public function resetSuccessfulRequests()
    {
        $key = $this->_getPrefixKey($this->key . '_successfulRequests');
        $this->_client->set($key, 0);
    }
    
    /**
     * 重置失败请求数
     *
     * @return none
     */
    public function resetFailedRequests()
    {
        $key = $this->_getPrefixKey($this->key . '_failedRequests');
        $this->_client->set($key, 0);
    }
    
    /**
     * 增加成功请求数
     *
     * @return none
     */
    public function markSuccess()
    {
        $key = $this->_getPrefixKey($this->key . '_successfulRequests');
        $this->_client->incr($key);
    }
    
    /**
     * 增加失败请求数
     *
     * @return none
     */
    public function markFailure()
    {
        $key = $this->_getPrefixKey($this->key . '_failedRequests');
        $this->_client->incr($key);
    }
    
    /**
     * 设置熔断器状态
     *
     * @param boolean $isOpen 熔断器打开状态
     *
     * @return none
     */
    public function setIsOpen($isOpen)
    {
        $key = $this->_getPrefixKey($this->key . '_isOpen');
        return $this->_client->set($key, (int)$isOpen);
    }
    
    /**
     * 设置熔断器打开时间戳
     *
     * @param float $microtime 微秒时间戳
     *
     * @return none
     */
    public function setOpenTimestamp($microtime = null)
    {
        if ($microtime == null) {
            $microtime = microtime(true);
        }
        $key = $this->_getPrefixKey($this->key . '_openTimestamp');
        $this->_client->set($key, (string)$microtime);
    }
    

    /**
     * 获取熔断器打开时间戳
     *
     * @return float 微秒时间戳
     */
    public function getOpenTimestamp()
    {
        $key = $this->_getPrefixKey($this->key . '_openTimestamp');
        return (float)$this->_client->get($key);
    }
    
    /**
     * 设置bucket时间戳
     *
     * @param float $microtime 微秒时间戳
     *
     * @return none
     */
    public function setBucketTimestamp($microtime = null)
    {
        if ($microtime == null) {
            $microtime = microtime(true);
        }
        $key = $this->_getPrefixKey($this->key . '_bucketTimestamp');
        $this->_client->set($key, (string)$microtime);
    }
    
    
    /**
     * 获取bucket时间戳
     *
     * @return float 微秒时间戳
     */
    public function getBucketTimestamp()
    {
        $key = $this->_getPrefixKey($this->key . '_bucketTimestamp');
        return (float)$this->_client->get($key);
    }
    
    /**
     * 获取带前缀的服务键名
     *
     * @param string $key 服务键名
     *
     * @return string 带前缀的服务键名
     */
    private function _getPrefixKey($key)
    {
        return self::KEY_PREFIX . $key;
    }
}