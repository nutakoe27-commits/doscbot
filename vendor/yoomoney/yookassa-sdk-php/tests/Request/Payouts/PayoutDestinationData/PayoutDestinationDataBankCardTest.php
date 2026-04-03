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
use YooKassa\Request\Payouts\PayoutDestinationData\PayoutDestinationDataBankCard;
use YooKassa\Request\Payouts\PayoutDestinationData\PayoutDestinationDataBankCardCard;

class PayoutDestinationDataBankCardTest extends AbstractPayoutDestinationDataTest
{
    /**
     * @return PayoutDestinationDataBankCard
     */
    protected function getTestInstance()
    {
        return new PayoutDestinationDataBankCard();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return PaymentMethodType::BANK_CARD;
    }

    /**
     * @dataProvider validCardDataProvider
     * @param PayoutDestinationDataBankCardCard $value
     */
    public function testGetSetBankCard($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getCard());
        self::assertNull($instance->card);

        $instance->setCard($value);
        if ($value === null || $value === '' || $value === array()) {
            self::assertNull($instance->getCard());
            self::assertNull($instance->card);
        } else {
            if (is_array($value)) {
                $expected = new PayoutDestinationDataBankCardCard();
                foreach ($value as $property => $val) {
                    $expected->offsetSet($property, $val);
                }
            } else {
                $expected = $value;
            }
            self::assertEquals($expected, $instance->getCard());
            self::assertEquals($expected, $instance->card);
        }

        $instance = $this->getTestInstance();
        $instance->card = $value;
        if ($value === null || $value === '' || $value === array()) {
            self::assertNull($instance->getCard());
            self::assertNull($instance->card);
        } else {
            if (is_array($value)) {
                $expected = new PayoutDestinationDataBankCardCard($value);
            } else {
                $expected = $value;
            }
            self::assertEquals($expected, $instance->getCard());
            self::assertEquals($expected, $instance->card);

            self::assertEquals($expected['number'], $instance->getCard()->getNumber());
            self::assertEquals($expected['number'], $instance->card->number);
        }
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidCard($value)
    {
        $this->getTestInstance()->setCard($value);
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidCard($value)
    {
        $this->getTestInstance()->card = $value;
    }

    public function validCardDataProvider()
    {
        return array(
            array(null),
            array(new PayoutDestinationDataBankCardCard()),
            array(array()),
            array(''),
            array(array(
                'number' => Random::str(16, '0123456789'),
            )),
        );
    }

    public function invalidCardDataProvider()
    {
        return array(
            array(0),
            array(1),
            array(-1),
            array('5'),
            array(true),
            array(new \stdClass()),
            array(new \DateTime()),
            array(array('number' => '')),
            array(array('number' => null)),
            array(array('number' => new \DateTime())),
            array(array('number' => Random::str(16))),
        );
    }
}
