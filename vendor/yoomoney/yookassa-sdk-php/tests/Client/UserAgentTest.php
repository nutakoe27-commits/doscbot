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
use ReflectionClass;
use ReflectionException;
use YooKassa\Client\UserAgent;

class UserAgentTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testHeaderString()
    {
        $agent = new UserAgent();
        $reflector = new ReflectionClass('\YooKassa\Client\UserAgent');
        $method    = $reflector->getMethod('setOs');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'CentOS', 'version' => '6.7'));
        $method    = $reflector->getMethod('setPhp');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'PHP', 'version' => '5.4.45'));
        $method    = $reflector->getMethod('setSdk');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'YooKassa.PHP', 'version' => '1.4.1'));
        $agent->setCms('Wordpress', '2.0.4');
        $agent->setModule('Woocommerce', '1.2.3');
        $this->assertEquals('CentOS/6.7 PHP/5.4.45 Wordpress/2.0.4 Woocommerce/1.2.3 YooKassa.PHP/1.4.1', $agent->getHeaderString());
    }

    /**
     * @throws ReflectionException
     */
    public function testSetGetOs()
    {
        $agent = new UserAgent();
        $reflector = new ReflectionClass('\YooKassa\Client\UserAgent');
        $method    = $reflector->getMethod('setOs');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'CentOS', 'version' => '6.7'));
        $this->assertEquals('CentOS/6.7', $agent->getOs());
    }

    /**
     * @throws ReflectionException
     */
    public function testSetGetPhp()
    {
        $agent = new UserAgent();
        $reflector = new ReflectionClass('\YooKassa\Client\UserAgent');
        $method    = $reflector->getMethod('setPhp');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'PHP', 'version' => '5.4.45'));
        $this->assertEquals('PHP/5.4.45', $agent->getPhp());
    }

    public function testSetGetFramework()
    {
        $agent = new UserAgent();
        $agent->setFramework('Yii', '2.4.1');
        $this->assertEquals('Yii/2.4.1', $agent->getFramework());
    }

    public function testSetGetCms()
    {
        $agent = new UserAgent();
        $agent->setCms('Wordpress', '2.0.4');
        $this->assertEquals('Wordpress/2.0.4', $agent->getCms());
    }

    public function testSetGetModule()
    {
        $agent = new UserAgent();
        $agent->setModule('Woocommerce', '1.2.3');
        $this->assertEquals('Woocommerce/1.2.3', $agent->getModule());
    }

    /**
     * @throws ReflectionException
     */
    public function testSetGetSdk()
    {
        $agent     = new UserAgent();
        $reflector = new ReflectionClass('\YooKassa\Client\UserAgent');
        $method    = $reflector->getMethod('setSdk');
        $method->setAccessible(true);
        $method->invokeArgs($agent, array('name' => 'YooKassa.PHP', 'version' => '1.4.1'));
        $this->assertEquals('YooKassa.PHP/1.4.1', $agent->getSdk());
    }

    /**
     * @dataProvider validVersionDataProvider
     * @param $input
     * @param $output
     */
    public function testCreateVersion($input, $output)
    {
        $agent     = new UserAgent();
        $this->assertEquals($agent->createVersion($input['name'], $input['version']), $output);
    }

    public function validVersionDataProvider()
    {
        return array(
            array(
                array('name' => 'PHP ', 'version' => '1.2.3 '),
                'PHP/1.2.3'
            ),
            array(
                array('name' => 'Ubuntu GNU/Linux', 'version' => ' 14.04'),
                'Ubuntu.GNU.Linux/14.04'
            ),
            array(
                array('name' => 'YooKassa PHP', 'version' => '1/4.3'),
                'YooKassa.PHP/1.4.3'
            ),
        );
    }
}
