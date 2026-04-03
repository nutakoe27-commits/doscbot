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

namespace Tests\YooKassa\Model\PaymentMethod;

use InvalidArgumentException;
use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\PaymentMethod\AbstractPaymentMethod;
use YooKassa\Model\PaymentMethod\PaymentMethodSberLoan;
use YooKassa\Model\PaymentMethodType;

class PaymentMethodSberLoanTest extends AbstractPaymentMethodTest
{
    /**
     * @return AbstractPaymentMethod
     */
    protected function getTestInstance()
    {
        return new PaymentMethodSberLoan();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return PaymentMethodType::SBER_LOAN;
    }


    /**
     * @dataProvider validLoanOptionDataProvider
     *
     * @param mixed $value
     */
    public function testGetSetLoanOption($value)
    {
        $instance = $this->getTestInstance();

        $instance->setLoanOption($value);
        self::assertEquals($value, $instance->getLoanOption());
        self::assertEquals($value, $instance->loan_option);

        $instance = $this->getTestInstance();
        $instance->loan_option = $value;
        self::assertEquals($value, $instance->getLoanOption());
        self::assertEquals($value, $instance->loan_option);
    }

    /**
     * @dataProvider invalidLoanOptionDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidLoanOption($value)
    {
        $instance = $this->getTestInstance();
        $instance->setLoanOption($value);
    }

    /**
     * @dataProvider invalidLoanOptionDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidLoanOption($value)
    {
        $instance = $this->getTestInstance();
        $instance->loan_option = $value;
    }

    /**
     * @dataProvider validDiscountAmountDataProvider
     */
    public function testGetSetDiscountAmount($value)
    {
        $instance = $this->getTestInstance();

        $instance->setDiscountAmount($value);
        self::assertSame($value, $instance->getDiscountAmount());
        self::assertSame($value, $instance->discount_amount);
        self::assertSame($value, $instance->discountAmount);

        $instance = $this->getTestInstance();
        $instance->discount_amount = $value;
        self::assertSame($value, $instance->getDiscountAmount());
        self::assertSame($value, $instance->discount_amount);
        self::assertSame($value, $instance->discountAmount);

        $instance = $this->getTestInstance();
        $instance->discountAmount = $value;
        self::assertSame($value, $instance->getDiscountAmount());
        self::assertSame($value, $instance->discount_amount);
        self::assertSame($value, $instance->discountAmount);
    }

    /**
     * @dataProvider invalidDiscountAmountDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidDiscountAmount($value)
    {
        $instance = $this->getTestInstance();
        $instance->setDiscountAmount($value);
    }

    /**
     * @dataProvider invalidDiscountAmountDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidDiscountAmount($value)
    {
        $instance = $this->getTestInstance();
        $instance->discount_amount = $value;
    }

    public function validLoanOptionDataProvider()
    {
        return array(
            array(null),
            array(''),
            array('loan'),
            array('installments_1'),
            array('installments_12'),
            array('installments_36'),
        );
    }

    public function invalidLoanOptionDataProvider()
    {
        return array(
            array(true),
            array('2345678901234567'),
            array('installments_'),
        );
    }

    public function validDiscountAmountDataProvider()
    {
        $result = array(
            array(null),
        );
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(new MonetaryAmount(array('value' => Random::int(1, 10000), 'currency' => Random::value(CurrencyCode::getEnabledValues()))));
        }
        return $result;
    }

    public function invalidDiscountAmountDataProvider()
    {
        return array(
            array(true),
            array('2345678901234567'),
            array('installments_'),
        );
    }
}
