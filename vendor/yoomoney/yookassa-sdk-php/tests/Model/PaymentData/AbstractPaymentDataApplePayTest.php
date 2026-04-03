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

namespace Tests\YooKassa\Model\PaymentData;

use YooKassa\Helpers\Random;
use YooKassa\Model\PaymentData\PaymentDataApplePay;

abstract class AbstractPaymentDataApplePayTest extends AbstractPaymentDataTest
{
    /**
     * @dataProvider validPaymentDataDataProvider
     * @param $value
     */
    public function testGetSetPaymentData($value)
    {
        /** @var PaymentDataApplePay $instance */
        $instance = $this->getTestInstance();

        self::assertNull($instance->getPaymentData());
        self::assertNull($instance->paymentData);
        self::assertNull($instance->payment_data);

        $instance->setPaymentData($value);
        if ($value === null || $value === '') {
            self::assertNull($instance->getPaymentData());
            self::assertNull($instance->paymentData);
            self::assertNull($instance->payment_data);
        } else {
            self::assertEquals($value, $instance->getPaymentData());
            self::assertEquals($value, $instance->paymentData);
            self::assertEquals($value, $instance->payment_data);
        }

        $instance = $this->getTestInstance();
        $instance->paymentData = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getPaymentData());
            self::assertNull($instance->paymentData);
            self::assertNull($instance->payment_data);
        } else {
            self::assertEquals($value, $instance->getPaymentData());
            self::assertEquals($value, $instance->paymentData);
            self::assertEquals($value, $instance->payment_data);
        }

        $instance = $this->getTestInstance();
        $instance->payment_data = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getPaymentData());
            self::assertNull($instance->paymentData);
            self::assertNull($instance->payment_data);
        } else {
            self::assertEquals($value, $instance->getPaymentData());
            self::assertEquals($value, $instance->paymentData);
            self::assertEquals($value, $instance->payment_data);
        }
    }

    /**
     * @dataProvider invalidPaymentDataDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidPaymentData($value)
    {
        /** @var PaymentDataApplePay $instance */
        $instance = $this->getTestInstance();
        $instance->setPaymentData($value);
    }

    /**
     * @dataProvider invalidPaymentDataDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidPaymentData($value)
    {
        /** @var PaymentDataApplePay $instance */
        $instance = $this->getTestInstance();
        $instance->paymentData = $value;
    }

    /**
     * @dataProvider invalidPaymentDataDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidPayment_data($value)
    {
        /** @var PaymentDataApplePay $instance */
        $instance = $this->getTestInstance();
        $instance->payment_data = $value;
    }

    public function validPaymentDataDataProvider()
    {
        return array(
            array('https://test.ru'),
            array(Random::str(256)),
            array(Random::str(1024)),
        );
    }

    public function invalidPaymentDataDataProvider()
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
