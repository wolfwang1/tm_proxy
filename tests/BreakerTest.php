<?php
declare(strict_types=1);
namespace TmProxy\TmBreaker;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../src/Lib/TmBreakerDataDriverAbstract.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../src/Lib/TmBreakerRedisDriver.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../src/TmBreaker.php';
use TmProxy\TmBreaker\Lib\TmBreakerRedisDriver;
use PHPUnit\Framework\TestCase;
use Exception;
/**
 * -------------------------------------------------------------------------------------
 * Copyright (c) 2014-2017 Beijing Chinaway Technologies Co., Ltd. All rights reserved.
 * -------------------------------------------------------------------------------------
 * 熔断器单元测试
 *
 * PHP version 5.4.0 or above
 *
 * @category Test
 * @package  BreakerTest
 * @author   Wangyu <wangyu@huoyunren.com>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */

/**
 * 熔断器功能测试用例
 *
 * @category Test
 * @package  BreakerTest
 * @author   Wangyu <wangyu@huoyunren.com>
 * @license  http://www.huoyunren.com/ None
 * @link     http://www.huoyunren.com/
 */
class BreakerTest extends TestCase
{
    /**
     * 数据存储驱动对象
     *
     * @var private object
     */
    private $_dataDriver;
    
    /**
     * 熔断器对象
     *
     * @var private object
     */
    private $_breaker;
    
    /**
     * 测试前置信息初始化
     *
     * @return void
     */
    protected function setUp()
    {
        $redisDriver = new TmBreakerRedisDriver('localhost', 6379, '', 0.04);
        $breaker = new TmBreaker('phpunit', $redisDriver);
        $this->_dataDriver = $redisDriver;
        $this->_breaker = $breaker;
    }
    
    /**
     * 测试数据回收
     *
     * @return void
     */
    protected function tearDown()
    {
        $this->_dataDriver->setIsOpen(false);
        $this->_dataDriver->setBucketTimestamp();
        $this->_dataDriver->resetCounter();
    }
    
    /**
     * 测试请求成功计数
     *
     * @return void
     */
    public function testMarkSuccess()
    {
        //初始状态测试
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->assertEquals(0, $this->_dataDriver->getErrorPercentage());
        for ($i = 1; $i <= 5; $i++) {
            $this->_breaker->markSuccess();
        }
        $this->assertEquals(5, $this->_dataDriver->getSuccessfulRequests());
        //模拟时间桶未过期情况
        $this->_breaker->markSuccess();
        $this->assertEquals(6, $this->_dataDriver->getSuccessfulRequests());
        //模拟时间桶10s过期情况
        $now = microtime(true);
        $this->_dataDriver->setBucketTimestamp($now - 11);
        $this->assertEquals(true, $this->_breaker->allowRequest());
        for ($i = 1; $i <= 10; $i++) {
            $this->_breaker->markSuccess();
        }
        $this->assertEquals(10, $this->_dataDriver->getSuccessfulRequests());
    }
    
    /**
     * 测试请求失败计数
     *
     * @return void
     */
    public function testMarkFailure()
    {
        $this->_breaker->markFailure();
        //模拟时间桶10s过期情况
        $now = microtime(true);
        $this->_dataDriver->setBucketTimestamp($now - 11);
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->_breaker->markFailure();
        $this->assertEquals(1, $this->_dataDriver->getFailedRequests());
        //模拟时间桶未过期情况
        for ($i = 1; $i <= 5; $i++) {
            $this->_breaker->markFailure();
        }
        $this->assertEquals(6, $this->_dataDriver->getFailedRequests());
    }
    
    
    /**
     * 测试请求失败计数
     *
     * @return void
     */
    public function testAllowRequest()
    {
        $this->testMarkSuccess();
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->testMarkFailure();
        $this->assertEquals(true, $this->_breaker->allowRequest());
    }
    
    /**
     * 测试熔断器打开状态
     *
     * @return void
     */
    public function testOpen()
    {
        $this->_dataDriver->setIsOpen(true);
        $this->_dataDriver->setOpenTimestamp();
        
        //熔断器打开状态且没有超过休眠期
        for ($i = 1; $i <= 10; $i++) {
            $this->_breaker->markFailure();
        }
        $this->assertEquals(true, $this->_breaker->isOpen());
        $this->assertEquals(false, $this->_breaker->allowRequest());
        $this->assertEquals(10, $this->_dataDriver->getFailedRequests());
    }
    
    /**
     * 测试熔断器半开状态
     *
     * @return void
     */
    public function testHalfOpen()
    {
        $this->_dataDriver->setIsOpen(true);
        //熔断器打开状态且超过休眠期
        $now = microtime(true);
        //强制设置休眠时间+5秒
        $this->_dataDriver->setOpenTimestamp($now - 6);
        $this->assertEquals(true, $this->_breaker->isOpen());
        $this->assertEquals(true, $this->_breaker->allowRequest());
        //模拟尝试请求成功
        $this->_breaker->markSuccess();
        $this->assertEquals(false, $this->_breaker->isOpen());
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->assertEquals(0, $this->_dataDriver->getFailedRequests());
        $this->assertEquals(1, $this->_dataDriver->getSuccessfulRequests());
    }
    
    /**
     * 测试熔断器关闭状态
     *
     * @return void
     */
    public function testClosed()
    {
        $this->_dataDriver->setIsOpen(false);
        $this->assertEquals(false, $this->_breaker->isOpen());
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->assertEquals(0, $this->_dataDriver->getFailedRequests());
        $this->assertEquals(0, $this->_dataDriver->getSuccessfulRequests());
        //测试非熔断下的正常请求
        for ($i = 1; $i <= 5; $i++) {
            $this->_breaker->markSuccess();
        }
        $this->assertEquals(false, $this->_breaker->isOpen());
        $this->assertEquals(true, $this->_breaker->allowRequest());
        $this->assertEquals(0, $this->_dataDriver->getFailedRequests());
        $this->assertEquals(5, $this->_dataDriver->getSuccessfulRequests());
        //通过错误请求触发熔断
        for ($i = 1; $i <= 10; $i++) {
            $this->_breaker->markFailure();
        }
        $this->assertEquals(true, $this->_breaker->isOpen());
        $this->assertEquals(false, $this->_breaker->allowRequest());
        $this->assertEquals(10, $this->_dataDriver->getFailedRequests());
        $this->assertEquals(5, $this->_dataDriver->getSuccessfulRequests());
    }
    
    /**
     * 测试熔断器常开状态
     *
     * @return void
     */
    public function testForceOpen()
    {
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, ['forceOpen' => true]);
        $this->assertEquals(false, $breaker->allowRequest());
    }
    
    /**
     * 测试熔断器常关状态
     *
     * @return void
     */
    public function testForceClosed()
    {
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, ['forceClosed' => true]);
        //通过错误请求触发熔断
        for ($i = 1; $i <= 10; $i++) {
            $breaker->markFailure();
        }
        $this->assertEquals(true, $breaker->isOpen());
        $this->assertEquals(10, $this->_dataDriver->getFailedRequests());
        //熔断状态打开但仍允许访问, 熔断器常关
        $this->assertEquals(true, $breaker->allowRequest());
    }
    
    /**
     * 测试熔断器激活状态
     *
     * @return void
     */
    public function testEnabled()
    {
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, ['enabled' => false]);
        $this->assertEquals(true, $breaker->allowRequest());
        $breaker->markSuccess();
        //通过错误请求触发熔断
        for ($i = 1; $i <= 10; $i++) {
            $breaker->markFailure();
        }
        $this->assertEquals(false, $breaker->isOpen());
        $this->assertEquals(0, $this->_dataDriver->getSuccessfulRequests());
        $this->assertEquals(0, $this->_dataDriver->getFailedRequests());
        //熔断器关闭, 即使错误请求也不会计数, 始终允许访问
        $this->assertEquals(true, $breaker->allowRequest());
        //测试熔断器未激活状态的回调函数响应
        $result = $breaker->run(function() {
        });
        $this->assertEquals(true, $result);
    }
    
    /**
     * 测试熔断器常关状态
     *
     * @return void
     */
    public function testRun()
    {
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, []);
        $breaker->run(function() {
            return;
        });
        $this->assertEquals(1, $this->_dataDriver->getSuccessfulRequests());
        try{
            $this->_dataDriver->setIsOpen(true);
            $breaker->run(function() {
            });
        }catch(Exception $e){
            $this->assertEquals('TmBreaker for phpunit is actived and no fallback is available.', $e->getMessage());
        }
    }
    
    /**
     * 测试熔断器常关状态
     *
     * @return void
     */
    public function testRunFallback()
    {
        $fallback = function($e) {
            $this->assertEquals('error', $e->getMessage());
        };
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, [], $fallback);
        $breaker->run(function() {
            throw new Exception('error');
        });
        $this->assertEquals(1, $this->_dataDriver->getFailedRequests());
        //测试没有抛出异常的情况
        $fallback = function($e) {
            $this->assertEquals('TmBreaker for phpunit is actived', $e->getMessage());
        };
        $breaker = new TmBreaker('phpunit', $this->_dataDriver, [], $fallback);
        $this->_dataDriver->setIsOpen(true);
        $breaker->run(function() {
        });
    }
}