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

namespace Tests\YooKassa\Request\Payouts\PayoutDestinationData;

use YooKassa\Helpers\Random;
use YooKassa\Model\PaymentMethodType;
use YooKassa\Request\Payouts\PayoutDestinationData\PayoutDestinationDataYooMoney;

class PayoutDestinationDataYooMoneyTest extends AbstractPayoutDestinationDataTest
{
    /**
     * @return PayoutDestinationDataYooMoney
     */
    protected function getTestInstance()
    {
        return new PayoutDestinationDataYooMoney();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return PaymentMethodType::YOO_MONEY;
    }

    /**
     * @dataProvider validAccountNumberDataProvider
     * @param string $value
     */
    public function testGetSetAccountNumber($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getAccountNumber());
        self::assertNull($instance->accountNumber);
        self::assertNull($instance->account_number);

        $instance->setAccountNumber($value);
        if ($value === null || $value === '' || $value === array()) {
            self::assertNull($instance->getAccountNumber());
            self::assertNull($instance->accountNumber);
            self::assertNull($instance->account_number);
        } else {
            $expected = $value;
            self::assertEquals($expected, $instance->getAccountNumber());
            self::assertEquals($expected, $instance->accountNumber);
            self::assertEquals($expected, $instance->account_number);
        }

        $instance = $this->getTestInstance();
        $instance->account_number = $value;
        if ($value === null || $value === '' || $value === array()) {
            self::assertNull($instance->getAccountNumber());
            self::assertNull($instance->accountNumber);
            self::assertNull($instance->account_number);
        } else {
            $expected = $value;
            self::assertEquals($expected, $instance->getAccountNumber());
            self::assertEquals($expected, $instance->accountNumber);
            self::assertEquals($expected, $instance->account_number);
        }
    }

    /**
     * @dataProvider invalidAccountNumberDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidAccountNumber($value)
    {
        $this->getTestInstance()->setAccountNumber($value);
    }

    /**
     * @dataProvider invalidAccountNumberDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidAccountNumber($value)
    {
        $this->getTestInstance()->account_number = $value;
    }

    public function validAccountNumberDataProvider()
    {
        return array(
            array(1234567894560),
            array('0123456789456'),
            array(Random::str(11, 33, '0123456789')),
        );
    }

    public function invalidAccountNumberDataProvider()
    {
        return array(
            array(0),
            array(array()),
            array(''),
            array(null),
            array(Random::str(34, 50, '0123456789')),
            array(true),
            array(new \stdClass()),
            array(new \DateTime()),
            array(Random::str(1, 10, '0123456789')),
        );
    }
}
