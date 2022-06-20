<?php
/**
 * -------------------------------------------------------------------------------------
 * Copyright (c) 2014-2017 Beijing Chinaway Technologies Co., Ltd. All rights reserved.
 * -------------------------------------------------------------------------------------
 * TmProxy通用简易熔断器TmBreaker SDK
 *
 * PHP version 5.4.0 or above
 *
 * @category TmBreaker
 * @package  TmProxy
 * @author   Wolf Wang <wangyu@g7.com.cn>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
namespace TmProxy\TmBreaker;
use Exception;
use TmProxy\TmBreaker\Lib\TmBreakerDataDriverAbstract;
/**
 * TmBreaker主逻辑类
 *
 * @category TmBreaker
 * @package  TmProxy
 * @author   Wolf Wang <wangyu@g7.com.cn>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
class TmBreaker
{
    /**
     * 服务键名
     *
     * @var private string
     */
    private $_key;
    
    /**
     * 数据存储驱动
     *
     * @var private object
     */
    private $_dataDriver;
    
    /**
     * 熔断器配置
     *
     * @var private object
     */
    private $_config;
    
    /**
     * 熔断器异常回调
     *
     * @var private object
     */
    private $_failureFallback;
    
    /**
     * 熔断器是否开启
     *
     * @var const int
     */
    const ENABLED = true;
    
    /**
     * 熔断器是否强制开启
     *
     * @var const int
     */
    const FORCE_OPEN = false;
    
    /**
     * 熔断器是否强制关闭
     *
     * @var const int
     */
    const FORCE_CLOSED = false;
    
    /**
     * 熔断器请求数阈值
     * 请求数大于阈值熔断器才会生效
     *
     * @var const int
     */
    const REQUEST_VOLUME_THRESHOLD = 10;
    
    /**
     * 熔断器错误请求百分比熔断阈值
     *
     * @var const int
     */
    const ERROR_THRESHOLD_PERCENTAGE = 50;
    
    /**
     * 熔断器试探访问休眠期
     *
     * @var const int
     */
    const SLEEP_WINDOW_IN_MILLISECONDS = 5000;
    
    /**
     * 熔断器构造函数
     *
     * @param string   $key             服务键名
     * @param object   $dataDriver      数据存储驱动
     * @param array    $config          熔断器配置
     * @param function $failureFallback 异常回调函数
     *
     * @return none
     */
    public function __construct($key, TmBreakerDataDriverAbstract $dataDriver, $config = [], $failureFallback = null)
    {
        $this->_key = $key;
        $this->_dataDriver = $dataDriver;
        $this->_dataDriver->setKey($key);
        $this->_failureFallback = $failureFallback;
        $this->_config = array(
            'enabled' => self::ENABLED,
            'forceOpen' => self::FORCE_OPEN,
            'forceClosed' => self::FORCE_CLOSED,
            'requestVolumeThreshold' => self::REQUEST_VOLUME_THRESHOLD,
            'errorThresholdPercentage' => self::ERROR_THRESHOLD_PERCENTAGE,
            'sleepWindowInMilliseconds' => self::SLEEP_WINDOW_IN_MILLISECONDS
        );
        $this->_config = array_merge($this->_config, $config);
    }
    
    /**
     * 熔断器是否打开
     *
     * @return boolean
     */
    public function isOpen()
    {
        if ($this->_dataDriver->isOpen()) {
            return true;
        }
        
        $totalRequests = $this->_dataDriver->getTotalRequests();
        if ($totalRequests < $this->_config['requestVolumeThreshold']) {
            $this->checkBucket();
            return false;
        }
        
        $errorPercentage = $this->_dataDriver->getErrorPercentage();
        if ($errorPercentage < $this->_config['errorThresholdPercentage']) {
            $this->checkBucket();
            return false;
        } else {
            $this->_dataDriver->setIsOpen(true);
            $this->_dataDriver->setOpenTimestamp();
            return true;
        }
    }
    
    /**
     * 请求是否允许通过
     *
     * @return boolean
     */
    public function allowRequest()
    {
        if (!$this->_config['enabled']) {
            return true;
        }
        
        if ($this->_config['forceOpen']) {
            return false;
        }
        
        if ($this->_config['forceClosed']) {
            $this->isOpen();
            return true;
        }
        
        return !$this->isOpen() || $this->allowSingleTest();
    }
    
    /**
     * 是否允许试探性访问
     *
     * @return boolean
     */
    public function allowSingleTest()
    {
        $openTimestamp = $this->_dataDriver->getOpenTimestamp();
        if ($openTimestamp) {
            $now = microtime(true);
            $diffMilliseconds = round($now - $openTimestamp, 3) * 1000;
            if ($diffMilliseconds > $this->_config['sleepWindowInMilliseconds']) {
                $this->_dataDriver->setOpenTimestamp($now);
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查bucket时间戳并判断是否需要重置计数器
     *
     * @return none
     */
    public function checkBucket()
    {
        $bucketTimestamp = $this->_dataDriver->getBucketTimestamp();
        if ($bucketTimestamp) {
            $now = microtime(true);
            $diffMilliseconds = round($now - $bucketTimestamp, 3) * 1000;
            if ($diffMilliseconds > 10000) {
                $this->_dataDriver->setBucketTimestamp($now);
                $this->_dataDriver->resetCounter();
            }
        } else {
            $this->_dataDriver->setBucketTimestamp();
        }
    }
    
    /**
     * 通过熔断器执行回调函数
     *
     * @param function $callback 回调函数
     *
     * @return boolean
     */
    public function run($callback)
    {
        if (!$this->_config['enabled']) {
            return true;
        }
        
        if (!$this->allowRequest()) {
            $this->runFallback();
            return false;
        }
        
        try {
            $callback();
            $this->markSuccess();
        } catch (Exception $e) {
            $this->markFailure();
            $this->runFallback($e);
        }
    }
    
    /**
     * 执行异常回调函数
     *
     * @param Exception $e 异常对象
     *
     * @return none
     */
    public function runFallback(Exception $e = null)
    {
        if (empty($e)) {
            $message = "TmBreaker for {$this->_key} is actived";
        } else {
            $message = $e->getMessage();
        }
        if (!is_callable($this->_failureFallback)) {
            $message .= ' and no fallback is available.';
            throw new Exception($message, 0, $e);
        }
        call_user_func($this->_failureFallback, $e ? $e : new Exception($message));
    }
    
    /**
     * 标记请求成功
     *
     * @return none
     */
    public function markSuccess()
    {
        if (!$this->_config['enabled']) {
            return;
        }
        
        if ($this->_dataDriver->isOpen()) {
            $this->_dataDriver->setIsOpen(false);
            $this->_dataDriver->setBucketTimestamp();
            $this->_dataDriver->resetCounter();
        }
        $this->_dataDriver->markSuccess();
    }
    
    /**
     * 标记请求失败
     *
     * @return none
     */
    public function markFailure()
    {
        if (!$this->_config['enabled']) {
            return;
        }
        
        $this->_dataDriver->markFailure();
    }
}