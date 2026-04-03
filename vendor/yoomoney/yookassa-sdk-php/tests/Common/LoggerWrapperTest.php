<?php
/*
 * The MIT License
 *
 * Copyright (c) 2026 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Tests\YooKassa\Common;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use YooKassa\Common\LoggerWrapper;
use YooKassa\Helpers\Random;

class LoggerWrapperTest extends TestCase
{
    public function testConstruct()
    {
        $logger = new LoggerWrapper(new ArrayLogger());
        self::assertNotNull($logger);
        $logger = new LoggerWrapper(function ($level, $message, $context) {
        });
        self::assertNotNull($logger);
    }

    /**
     * @dataProvider invalidLoggerDataProvider
     * @expectedException \Psr\Log\InvalidArgumentException
     * @param mixed $source
     */
    public function testInvalidConstruct($source)
    {
        new LoggerWrapper($source);
    }

    /**
     * @return array
     */
    public function invalidLoggerDataProvider()
    {
        return array(
            array(new \stdClass()),
            array(true),
            array(false),
            array(array()),
            array(1),
            array('test'),
        );
    }

    /**
     * @dataProvider validLoggerDataProvider
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function testLog($level, $message, $context)
    {
        $wrapped = new ArrayLogger();

        $instance = new LoggerWrapper($wrapped);
        $instance->log($level, $message, $context);
        $expected = array($level, $message, $context);
        self::assertEquals($expected, $wrapped->getLastLog());

        $wrapped = new ArrayLogger();
        $instance = new LoggerWrapper(function ($level, $message, $context) use ($wrapped) {
            $wrapped->log($level, $message, $context);
        });
        $instance->log($level, $message, $context);
        $expected = array($level, $message, $context);
        self::assertEquals($expected, $wrapped->getLastLog());
    }

    /**
     * @dataProvider validLoggerDataProvider
     * @param string $notUsed
     * @param string $message
     * @param array $context
     */
    public function testLogMethods($notUsed, $message, $context)
    {
        $methodsMap = array(
            LogLevel::EMERGENCY => 'emergency',
            LogLevel::ALERT => 'alert',
            LogLevel::CRITICAL => 'critical',
            LogLevel::ERROR => 'error',
            LogLevel::WARNING => 'warning',
            LogLevel::NOTICE => 'notice',
            LogLevel::INFO => 'info',
            LogLevel::DEBUG => 'debug',
        );

        $wrapped = new ArrayLogger();
        $instance = new LoggerWrapper($wrapped);
        foreach ($methodsMap as $level => $method) {
            $instance->{$method}($message, $context);
            $expected = array($level, $message, $context);
            self::assertEquals($expected, $wrapped->getLastLog());
        }
    }

    public function validLoggerDataProvider()
    {
        return array(
            array(LogLevel::EMERGENCY, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::ALERT, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::CRITICAL, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::ERROR, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::WARNING, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::NOTICE, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::INFO, Random::str(10, 20), array(Random::str(10, 20))),
            array(LogLevel::DEBUG, Random::str(10, 20), array(Random::str(10, 20))),
        );
    }
}

class ArrayLogger
{
    private $lastLog;

    public function log($level, $message, $context)
    {
        $this->lastLog = array($level, $message, $context);
    }

    public function getLastLog()
    {
        return $this->lastLog;
    }
}
