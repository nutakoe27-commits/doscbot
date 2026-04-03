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

namespace Tests\YooKassa\Model;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Helpers\StringObject;
use YooKassa\Model\Recipient;

class RecipientTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     *
     * @param $value
     */
    public function testGetSetAccountId($value)
    {
        $instance = new Recipient();

        self::assertEquals(null, $instance->getAccountId());
        self::assertEquals(null, $instance->accountId);
        self::assertEquals(null, $instance->account_id);
        $instance->setAccountId($value);
        self::assertEquals((string)$value, $instance->getAccountId());
        self::assertEquals((string)$value, $instance->accountId);
        self::assertEquals((string)$value, $instance->account_id);

        $instance = new Recipient();
        $instance->accountId = $value;
        self::assertEquals((string)$value, $instance->getAccountId());
        self::assertEquals((string)$value, $instance->accountId);
        self::assertEquals((string)$value, $instance->account_id);

        $instance = new Recipient();
        $instance->account_id = $value;
        self::assertEquals((string)$value, $instance->getAccountId());
        self::assertEquals((string)$value, $instance->accountId);
        self::assertEquals((string)$value, $instance->account_id);
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidAccountId($value)
    {
        $instance = new Recipient();
        $instance->setAccountId($value);
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidAccountId($value)
    {
        $instance = new Recipient();
        $instance->accountId = $value;
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidAccount_id($value)
    {
        $instance = new Recipient();
        $instance->account_id = $value;
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $value
     */
    public function testGetSetGatewayId($value)
    {
        $instance = new Recipient();

        self::assertEquals(null, $instance->getGatewayId());
        self::assertEquals(null, $instance->gatewayId);
        self::assertEquals(null, $instance->gateway_id);
        $instance->setGatewayId($value);
        self::assertEquals((string)$value, $instance->getGatewayId());
        self::assertEquals((string)$value, $instance->gatewayId);
        self::assertEquals((string)$value, $instance->gateway_id);

        $instance = new Recipient();
        $instance->gatewayId = $value;
        self::assertEquals((string)$value, $instance->getGatewayId());
        self::assertEquals((string)$value, $instance->gatewayId);
        self::assertEquals((string)$value, $instance->gateway_id);

        $instance = new Recipient();
        $instance->gateway_id = $value;
        self::assertEquals((string)$value, $instance->getGatewayId());
        self::assertEquals((string)$value, $instance->gatewayId);
        self::assertEquals((string)$value, $instance->gateway_id);
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidGatewayId($value)
    {
        $instance = new Recipient();
        $instance->setGatewayId($value);
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidGatewayId($value)
    {
        $instance = new Recipient();
        $instance->gatewayId = $value;
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidGateway_id($value)
    {
        $instance = new Recipient();
        $instance->gateway_id = $value;
    }

    public function validDataProvider()
    {
        $result = array(
            array(Random::str(1)),
            array(Random::str(2, 64)),
            array(new StringObject(Random::str(1, 32))),
            array(0),
            array(123),
        );
        return $result;
    }

    public function invalidDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(true),
            array(false),
            array(array()),
            array(new \stdClass()),
        );
    }
}
