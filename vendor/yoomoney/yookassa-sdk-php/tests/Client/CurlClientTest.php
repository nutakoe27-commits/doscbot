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

namespace Tests\YooKassa\Client;

use PHPUnit\Framework\TestCase;
use YooKassa\Client\CurlClient;
use YooKassa\Common\HttpVerb;

class CurlClientTest extends TestCase
{
    public function testConnectionTimeout()
    {
        $client = new CurlClient();
        $client->setConnectionTimeout(10);
        $this->assertEquals(10, $client->getConnectionTimeout());
    }

    public function testTimeout()
    {
        $client = new CurlClient();
        $client->setTimeout(10);
        $this->assertEquals(10, $client->getTimeout());
    }

    public function testProxy()
    {
        $client = new CurlClient();
        $client->setProxy('proxy_url:8889');
        $this->assertEquals('proxy_url:8889', $client->getProxy());
    }

    /**
     * @dataProvider curlErrorCodeProvider
     * @expectedException \YooKassa\Common\Exceptions\ApiConnectionException
     */
    public function testHandleCurlError($error, $errn)
    {
        $client    = new CurlClient();
        $reflector = new \ReflectionClass('\YooKassa\Client\CurlClient');
        $method    = $reflector->getMethod('handleCurlError');
        $method->setAccessible(true);
        $method->invokeArgs($client, array($error, $errn));
    }

    public function testConfig()
    {
        $config = array('url' => 'test:url');
        $client = new CurlClient();
        $client->setConfig($config);
        $this->assertEquals($config, $client->getConfig());
    }

    public function testCloseConnection()
    {
        $wrapped        = new \Tests\YooKassa\Client\ArrayLogger();
        $logger         = new \YooKassa\Common\LoggerWrapper($wrapped);
        $curlClientMock = $this->getMockBuilder('YooKassa\Client\CurlClient')
                               ->setMethods(array('closeCurlConnection', 'sendRequest'))
                               ->getMock();
        $curlClientMock->setLogger($logger);
        $curlClientMock->setConfig(array('url' => 'test:url'));
        $curlClientMock->setKeepAlive(false);
        $curlClientMock->setShopId(123);
        $curlClientMock->setShopPassword(234);
        $curlClientMock->expects($this->once())->method('sendRequest')->willReturn(array(
            array('Header-Name' => 'HeaderValue'),
            '{body:sample}',
            array('http_code' => 200),
        ));
        $curlClientMock->expects($this->once())->method('closeCurlConnection');
        $curlClientMock->call(
            '',
            HttpVerb::HEAD,
            array('queryParam' => 'value'),
            'testBodyValue',
            array('testHeader' => 'testValue')
        );
    }

    public function testAuthorizeException()
    {
        $this->setExpectedException('YooKassa\Common\Exceptions\AuthorizeException');
        $client = new CurlClient();
        $client->call(
            '',
            HttpVerb::HEAD,
            array('queryParam' => 'value'),
            array('httpBody' => 'testValue'),
            array('testHeader' => 'testValue')
        );
    }

    public function curlErrorCodeProvider()
    {
        return array(
            array('error message', CURLE_SSL_CACERT),
            array('error message', CURLE_COULDNT_CONNECT),
            array('error message', 0),
        );
    }
}
