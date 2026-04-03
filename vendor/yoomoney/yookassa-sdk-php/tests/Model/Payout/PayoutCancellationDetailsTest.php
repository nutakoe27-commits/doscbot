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

namespace Tests\YooKassa\Model\Payout;

use PHPUnit\Framework\TestCase;
use YooKassa\Model\Payout\PayoutCancellationDetailsPartyCode;
use YooKassa\Model\Payout\PayoutCancellationDetailsReasonCode;
use YooKassa\Model\Payout\PayoutCancellationDetails;

class PayoutCancellationDetailsTest extends TestCase
{
    /**
     * @param null $value
     * @return PayoutCancellationDetails
     */
    protected static function getInstance($value = null)
    {
        return new PayoutCancellationDetails($value);
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $value
     */
    public function testConstructor($value = null)
    {
        $instance = self::getInstance($value);

        self::assertEquals($value['party'], $instance->getParty());
        self::assertEquals($value['reason'], $instance->getReason());
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $value
     */
    public function testGetSetParty($value = null)
    {
        $instance = self::getInstance($value);
        self::assertEquals($value['party'], $instance->getParty());

        $instance = self::getInstance();
        $instance->setParty($value['party']);
        self::assertEquals($value['party'], $instance->getParty());
        self::assertEquals($value['party'], $instance->party);
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param null $value
     */
    public function testGetSetReason($value = null)
    {
        $instance = self::getInstance($value);
        self::assertEquals($value['reason'], $instance->getReason());

        $instance = self::getInstance();
        $instance->setReason($value['reason']);
        self::assertEquals($value['reason'], $instance->getReason());
        self::assertEquals($value['reason'], $instance->reason);
    }

    /**
     * @dataProvider invalidValueDataProvider
     * @param mixed $value
     * @param string $exceptionClassName
     */
    public function testSetInvalidParty($value, $exceptionClassName)
    {
        $instance = self::getInstance();
        try {
            $instance->setParty($value);
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassName, $e);
        }
    }

    /**
     * @dataProvider invalidValueDataProvider
     * @param mixed $value
     * @param string $exceptionClassName
     */
    public function testSetterInvalidReason($value, $exceptionClassName)
    {
        $instance = self::getInstance();
        try {
            $instance->reason = $value;
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassName, $e);
        }
    }

    /**
     * @return array
     */
    public function validDataProvider()
    {
        $result                          = array();
        $cancellationDetailsParties      = PayoutCancellationDetailsPartyCode::getValidValues();
        $countCancellationDetailsParties = count($cancellationDetailsParties);
        $cancellationDetailsReasons      = PayoutCancellationDetailsReasonCode::getValidValues();
        $countCancellationDetailsReasons = count($cancellationDetailsReasons);
        for ($i = 0; $i < 20; $i++) {
            $result[] = array(
                array(
                    'party'  => $cancellationDetailsParties[$i % $countCancellationDetailsParties],
                    'reason' => $cancellationDetailsReasons[$i % $countCancellationDetailsReasons]
                )
            );
        }
        return $result;
    }

    public function invalidValueDataProvider()
    {
        $exceptionNamespace = 'YooKassa\\Common\\Exceptions\\';
        return array(
            array(null, $exceptionNamespace . 'EmptyPropertyValueException'),
            array('', $exceptionNamespace . 'EmptyPropertyValueException'),
            array(array(), $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(fopen(__FILE__, 'r'), $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(true, $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(false, $exceptionNamespace . 'InvalidPropertyValueTypeException'),
        );
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param null $value
     */
    public function testJsonSerialize($value = null)
    {
        $instance = new PayoutCancellationDetails($value);
        $expected = array(
            'party'  => $value['party'],
            'reason' => $value['reason'],
        );
        self::assertEquals($expected, $instance->jsonSerialize());
    }
}
