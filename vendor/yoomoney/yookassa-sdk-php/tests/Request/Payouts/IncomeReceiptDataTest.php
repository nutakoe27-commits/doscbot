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

namespace Tests\YooKassa\Request\Payouts;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Payout\IncomeReceipt;
use YooKassa\Request\Payouts\IncomeReceiptData;

class IncomeReceiptDataTest extends TestCase
{
    /**
     * @param $options
     * @return IncomeReceiptData
     */
    protected function getTestInstance($options)
    {
        return new IncomeReceiptData($options);
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetServiceName($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['service_name'], $instance->getServiceName());
    }

    public function validDataProvider()
    {
        $result = array();

        for ($i = 0; $i < 10; $i++) {
            $deal = array(
                'service_name' => Random::str(36, IncomeReceipt::MAX_LENGTH_SERVICE_NAME),
                'amount' => new MonetaryAmount(Random::int(1, 1000000)),
            );
            $result[] = array($deal);
        }

        return $result;
    }

    /**
     * @dataProvider invalidServiceNameDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidServiceName($value)
    {
        $instance = new IncomeReceiptData();
        $instance->setServiceName($value);
    }

    public function invalidServiceNameDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(false),
            array(true),
            array(array()),
            array(Random::str(IncomeReceipt::MAX_LENGTH_SERVICE_NAME + 1, 60)),
        );
    }

    /**
     * @dataProvider invalidAmountDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidAmountToken($value)
    {
        $instance = new IncomeReceiptData();
        $instance->setAmount($value);
    }

    public function invalidAmountDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(false),
            array(true),
            array(new \stdClass()),
        );
    }
}
