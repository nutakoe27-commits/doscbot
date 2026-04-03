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

namespace Tests\YooKassa\Model\PaymentMethod\ElectronicCertificate;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData;

/**
 * ElectronicCertificatePaymentDataTest
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 */
class ElectronicCertificatePaymentDataTest extends TestCase
{
    /**
     * @return ElectronicCertificatePaymentData
     */
    protected function getTestInstance()
    {
        return new ElectronicCertificatePaymentData();
    }

    /**
     * @dataProvider validAmountDataProvider
     * @param mixed $value
     */
    public function testGetSetAmount($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getAmount());
        self::assertNull($instance->amount);

        $instance->setAmount($value);
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAmount()->getValue());
            self::assertEquals($value['currency'], $instance->amount->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAmount()->getValue());
            self::assertEquals($value->getCurrency(), $instance->amount->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->amount = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAmount()->getValue());
            self::assertEquals($value['currency'], $instance->amount->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAmount()->getValue());
            self::assertEquals($value->getCurrency(), $instance->amount->getCurrency());
        }
    }

    /**
     * @dataProvider invalidAmountDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidAmount($value)
    {
        $this->getTestInstance()->setAmount($value);
    }

    /**
     * @dataProvider invalidAmountDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidAmount($value)
    {
        $this->getTestInstance()->amount = $value;
    }

    /**
     * @dataProvider validBasketIdDataProvider
     * @param string $value
     */
    public function testGetSetBasketId($value)
    {
        $this->getAndSetTest($value, 'basketId', 'basket_id');
    }

    /**
     * @dataProvider invalidBasketIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidBasketId($value)
    {
        $this->getTestInstance()->setBasketId($value);
    }

    /**
     * @dataProvider invalidBasketIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidBasketId($value)
    {
        $this->getTestInstance()->basketId = $value;
    }

    /**
     * @dataProvider invalidBasketIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidBasketIdSnakeCase($value)
    {
        $this->getTestInstance()->basket_id = $value;
    }

    /**
     * @return array
     */
    public function validAmountDataProvider()
    {
        $result = array(
            array(
                array(
                    'value' => Random::int(1, 10000),
                    'currency' => CurrencyCode::RUB,
                )
            ),
            array(
                array(
                    'value' => Random::int(1, 10000),
                    'currency' => CurrencyCode::USD,
                )
            ),
            array(new MonetaryAmount(Random::int(1, 10000), CurrencyCode::RUB)),
            array(new MonetaryAmount(Random::int(1, 10000), CurrencyCode::EUR)),
        );

        return $result;
    }

    /**
     * @return array
     */
    public function invalidAmountDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(1.0),
            array(1),
            array(true),
            array(false),
            array(new \stdClass()),
        );
    }

    /**
     * @return array
     */
    public function validBasketIdDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::str(1, 50));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidBasketIdDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(array()),
            array(new \stdClass()),
        );
    }

    /**
     * @param mixed $value
     * @param string $property
     * @param string|null $snakeCase
     */
    protected function getAndSetTest($value, $property, $snakeCase = null)
    {
        $getter = 'get' . ucfirst($property);
        $setter = 'set' . ucfirst($property);

        $instance = $this->getTestInstance();

        self::assertNull($instance->{$getter}());
        self::assertNull($instance->{$property});
        if ($snakeCase !== null) {
            self::assertNull($instance->{$snakeCase});
        }

        $instance->{$setter}($value);

        self::assertEquals($value, $instance->{$getter}());
        self::assertEquals($value, $instance->{$property});
        if ($snakeCase !== null) {
            self::assertEquals($value, $instance->{$snakeCase});
        }

        $instance = $this->getTestInstance();

        $instance->{$property} = $value;

        self::assertEquals($value, $instance->{$getter}());
        self::assertEquals($value, $instance->{$property});
        if ($snakeCase !== null) {
            self::assertEquals($value, $instance->{$snakeCase});
        }

        if ($snakeCase !== null) {
            $instance = $this->getTestInstance();

            $instance->{$snakeCase} = $value;

            self::assertEquals($value, $instance->{$getter}());
            self::assertEquals($value, $instance->{$property});
            self::assertEquals($value, $instance->{$snakeCase});
        }
    }
}
