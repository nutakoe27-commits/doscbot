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
use YooKassa\Common\Exceptions\EmptyPropertyValueException;
use YooKassa\Helpers\Random;
use YooKassa\Model\AmountInterface;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Deal\SettlementPayoutPayment;
use YooKassa\Model\Deal\SettlementPayoutPaymentType;
use YooKassa\Model\MonetaryAmount;

class SettlementPayoutPaymentTest extends TestCase
{
    protected function getTestInstance()
    {
        return new SettlementPayoutPayment();
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param array $value
     */
    public function testFromArray($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getType());
        self::assertNull($instance->getAmount());
        self::assertNull($instance->type);
        self::assertNull($instance->amount);

        $instance->fromArray($value);

        self::assertSame($value['type'], $instance->getType());
        self::assertSame($value['type'], $instance->type);
        self::assertSame($value['amount'], $instance->getAmount()->jsonSerialize());
        self::assertSame($value['amount'], $instance->amount->jsonSerialize());

        self::assertSame($value, $instance->jsonSerialize());
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param array $value
     */
    public function testGetSetType($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getType());
        self::assertNull($instance->type);
        $instance->setType($value['type']);
        self::assertSame($value['type'], $instance->getType());
        self::assertSame($value['type'], $instance->type);
    }

    /**
     * @dataProvider invalidTypeDataProvider
     *
     * @expectedException \InvalidArgumentException
     * @expectedException EmptyPropertyValueException
     *
     * @param $value
     */
    public function testSetInvalidType($value)
    {
        $this->getTestInstance()->setType($value);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(
                'type' => Random::value(SettlementPayoutPaymentType::getValidValues()),
                'amount' => array(
                    'value' => sprintf('%.2f', round(Random::float(0.1, 99.99), 2)),
                    'currency' => Random::value(CurrencyCode::getValidValues())
                )
            );
        }
        return array($result);
    }

    /**
     * @dataProvider validAmountDataProvider
     *
     * @param AmountInterface $value
     */
    public function testGetSetAmount($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getAmount());
        self::assertNull($instance->amount);
        $instance->setAmount($value);
        self::assertSame($value, $instance->getAmount());
        self::assertSame($value, $instance->amount);
    }

    /**
     * @dataProvider validAmountDataProvider
     *
     * @param AmountInterface $value
     */
    public function testSetterAmount($value)
    {
        $instance = $this->getTestInstance();
        $instance->amount = $value;
        self::assertSame($value, $instance->getAmount());
        self::assertSame($value, $instance->amount);
    }

    public function validAmountDataProvider()
    {
        return array(
            array(
                new MonetaryAmount(
                    Random::int(1, 100),
                    Random::value(CurrencyCode::getValidValues())
                ),
            ),
            array(
                new MonetaryAmount(),
            ),
        );
    }

    /**
     * @dataProvider invalidAmountDataProvider
     *
     * @expectedException \InvalidArgumentException
     * @expectedException EmptyPropertyValueException
     *
     * @param $value
     */
    public function testSetInvalidAmount($value)
    {
        $this->getTestInstance()->setAmount($value);
    }

    /**
     * @dataProvider invalidAmountDataProvider
     *
     * @expectedException \InvalidArgumentException
     * @expectedException EmptyPropertyValueException
     *
     * @param $value
     */
    public function testSetterInvalidAmount($value)
    {
        $this->getTestInstance()->amount = $value;
    }

    public function invalidAmountDataProvider()
    {
        $result = array(
            array(null),
            array(''),
            array(1.0),
            array(1),
            array(true),
            array(false),
            array(new \stdClass()),
        );

        return $result;
    }

    public function invalidTypeDataProvider()
    {
        $result = array(
            array(null),
            array(''),
            array(1.0),
            array(1),
            array(true),
            array(false),
            array(array()),
            array(new \stdClass()),
            array(Random::str(1, 10)),
        );

        return $result;
    }
}
