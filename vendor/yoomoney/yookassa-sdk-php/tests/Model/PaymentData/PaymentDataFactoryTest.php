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

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\PaymentData\AbstractPaymentData;
use YooKassa\Model\PaymentData\PaymentDataBankCardCard;
use YooKassa\Model\PaymentData\PaymentDataFactory;
use YooKassa\Model\PaymentMethodType;

class PaymentDataFactoryTest extends TestCase
{
    protected function getTestInstance()
    {
        return new PaymentDataFactory();
    }

    /**
     * @dataProvider validTypeDataProvider
     * @param string $type
     */
    public function testFactory($type)
    {
        $instance = $this->getTestInstance();
        $paymentData = $instance->factory($type);
        self::assertNotNull($paymentData);
        self::assertTrue($paymentData instanceof AbstractPaymentData);
        self::assertEquals($type, $paymentData->getType());
    }

    /**
     * @dataProvider invalidTypeDataProvider
     * @expectedException \InvalidArgumentException
     * @param $type
     */
    public function testInvalidFactory($type)
    {
        $instance = $this->getTestInstance();
        $instance->factory($type);
    }

    /**
     * @dataProvider validArrayDataProvider
     * @param array $options
     */
    public function testFactoryFromArray($options)
    {
        $instance = $this->getTestInstance();
        $paymentData = $instance->factoryFromArray($options);
        self::assertNotNull($paymentData);
        self::assertTrue($paymentData instanceof AbstractPaymentData);

        foreach ($options as $property => $value) {
            self::assertEquals($paymentData->{$property}, $value);
        }

        $type = $options['type'];
        unset($options['type']);
        $paymentData = $instance->factoryFromArray($options, $type);
        self::assertNotNull($paymentData);
        self::assertTrue($paymentData instanceof AbstractPaymentData);

        self::assertEquals($type, $paymentData->getType());
        foreach ($options as $property => $value) {
            self::assertEquals($paymentData->{$property}, $value);
        }
    }

    /**
     * @dataProvider invalidDataArrayDataProvider
     * @expectedException \InvalidArgumentException
     * @param $options
     */
    public function testInvalidFactoryFromArray($options)
    {
        $instance = $this->getTestInstance();
        $instance->factoryFromArray($options);
    }

    public function validTypeDataProvider()
    {
        $result = array();
        foreach (PaymentMethodType::getEnabledValues() as $value) {
            $result[] = array($value);
        }
        return $result;
    }

    public function invalidTypeDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(1),
            array(-1),
            array('5'),
            array(array()),
            array(new \stdClass()),
            array(Random::str(10)),
        );
    }

    public function validArrayDataProvider()
    {
        $result = array(
            array(
                array(
                    'type' => PaymentMethodType::GOOGLE_PAY,
                    'paymentMethodToken' => Random::str(10, 20),
                    'googleTransactionId' => Random::str(10, 20),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::APPLE_PAY,
                    'paymentData' => Random::str(10, 20),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::BANK_CARD,
                    'card' => new PaymentDataBankCardCard(),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::CASH,
                    'phone' => Random::str(4, 15, '0123456789'),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::MOBILE_BALANCE,
                    'phone' => Random::str(4, 15, '0123456789'),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::SBERBANK,
                    'phone' => Random::str(4, 15, '0123456789'),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::YOO_MONEY,
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::TINKOFF_BANK,
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::SBP,
                ),
            ),
        );
        foreach (PaymentMethodType::getEnabledValues() as $value) {
            $result[] = array(array('type' => $value));
        }
        return $result;
    }

    public function invalidDataArrayDataProvider()
    {
        return array(
            array(array()),
            array(array('type' => 'test')),
            array(
                array(
                    'type' => PaymentMethodType::ALFABANK,
                    'login' => Random::str(10, 20),
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::INSTALLMENTS,
                ),
            ),
            array(
                array(
                    'type' => PaymentMethodType::UNKNOWN,
                ),
            ),
        );
    }
}
