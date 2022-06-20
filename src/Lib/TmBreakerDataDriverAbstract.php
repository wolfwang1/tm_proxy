<?php
/**
 * -------------------------------------------------------------------------------------
 * Copyright (c) 2014-2017 Beijing Chinaway Technologies Co., Ltd. All rights reserved.
 * -------------------------------------------------------------------------------------
 * TmProxy通用简易熔断器TmBreaker数据存储抽象类
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
/**
 * TmBreaker数据存储抽象类
 *
 * @category TmBreaker
 * @package  TmProxy
 * @author   Wolf Wang <wangyu@g7.com.cn>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
abstract class TmBreakerDataDriverAbstract
{
    /**
     * 服务键名
     *
     * @var string
     */
    protected $key;
    
    /**
     * 熔断器是否打开
     *
     * @return boolean
     */
    abstract public function isOpen();
    
    /**
     * 获得成功请求数
     *
     * @return int
     */
    abstract public function getSuccessfulRequests();
    
    /**
     * 获得失败请求数
     *
     * @return int
     */
    abstract public function getFailedRequests();
    
    /**
     * 重置成功请求数
     *
     * @return none
     */
    abstract public function resetSuccessfulRequests();
    
    /**
     * 重置失败请求数
     *
     * @return none
     */
    abstract public function resetFailedRequests();
    
    /**
     * 增加成功请求数
     *
     * @return none
     */
    abstract public function markSuccess();
    
    /**
     * 增加失败请求数
     *
     * @return none
     */
    abstract public function markFailure();
    
    /**
     * 设置熔断器状态
     * 
     * @param boolean $isOpen 熔断器打开状态
     *
     * @return none
     */
    abstract public function setIsOpen($isOpen);
    
    /**
     * 设置熔断器打开时间戳
     *
     * @param float $microtime 微秒时间戳
     *
     * @return none
     */
    abstract public function setOpenTimestamp($microtime);
    
    /**
     * 获取熔断器打开时间戳
     *
     * @return float 微秒时间戳
     */
    abstract public function getOpenTimestamp();
    
    /**
     * 设置bucket时间戳
     *
     * @param float $microtime 微秒时间戳
     *
     * @return none
     */
    abstract public function setBucketTimestamp($microtime);
    
    /**
     * 获取bucket时间戳
     *
     * @return float 微秒时间戳
     */
    abstract public function getBucketTimestamp();
    
    /**
     * 设置熔断器的服务键名
     *
     * @param string $key 服务键名
     *
     * @return object
     */
    public function setKey($key)
    {
        $hashKey = md5($key);
        $this->key = $hashKey;
        return $this;
    }
    
    /**
     * 获得总请求数
     *
     * @return int 总请求数
     */
    public function getTotalRequests()
    {
        $failures = $this->getFailedRequests();
        $successes = $this->getSuccessfulRequests();
        $total = $failures + $successes;
        return $total;
    }
    
    /**
     * 获得错误请求比例
     *
     * @return mixed 失败请求比例
     */
    public function getErrorPercentage()
    {
        $total = $this->getTotalRequests();
        if ($total == 0) {
            return 0;
        }
        $failures = $this->getFailedRequests();
        $errorPercentage = ($failures/$total) * 100;
        return $errorPercentage;
    }
    
    /**
     * 重置所有请求计数器
     *
     * @return none
     */
    public function resetCounter()
    {
        $this->resetSuccessfulRequests();
        $this->resetFailedRequests();
    }
}