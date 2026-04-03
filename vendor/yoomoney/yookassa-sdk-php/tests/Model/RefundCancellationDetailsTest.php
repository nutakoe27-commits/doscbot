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
use YooKassa\Model\RefundCancellationDetails;
use YooKassa\Model\RefundCancellationDetailsPartyCode;
use YooKassa\Model\RefundCancellationDetailsReasonCode;

class RefundCancellationDetailsTest extends TestCase
{
    /**
     * @param null $party
     * @param null $reason
     * @return RefundCancellationDetails
     */
    protected static function getInstance($party = null, $reason = null)
    {
        return new RefundCancellationDetails($party, $reason);
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $party
     * @param $reason
     */
    public function testConstructor($party = null, $reason = null)
    {
        $instance = self::getInstance($party, $reason);

        self::assertEquals($party, $instance->getParty());
        self::assertEquals($reason, $instance->getReason());
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $party
     * @param $reason
     */
    public function testGetSetParty($party = null, $reason = null)
    {
        $instance = self::getInstance($party, $reason);
        self::assertEquals($party, $instance->getParty());

        $instance = self::getInstance();
        $instance->setParty($party);
        self::assertEquals($party, $instance->getParty());
        self::assertEquals($party, $instance->party);
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param null $party
     * @param null $reason
     */
    public function testGetSetReason($party = null, $reason = null)
    {
        $instance = self::getInstance($party, $reason);
        self::assertEquals($reason, $instance->getReason());

        $instance = self::getInstance();
        $instance->setReason($reason);
        self::assertEquals($reason, $instance->getReason());
        self::assertEquals($reason, $instance->reason);
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
        $cancellationDetailsParties      = RefundCancellationDetailsPartyCode::getValidValues();
        $countCancellationDetailsParties = count($cancellationDetailsParties);
        $cancellationDetailsReasons      = RefundCancellationDetailsReasonCode::getValidValues();
        $countCancellationDetailsReasons = count($cancellationDetailsReasons);
        for ($i = 0; $i < 20; $i++) {
            $result[] = array(
                'party'  => $cancellationDetailsParties[$i % $countCancellationDetailsParties],
                'reason' => $cancellationDetailsReasons[$i % $countCancellationDetailsReasons]
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
     * @param null $party
     * @param null $reason
     */
    public function testJsonSerialize($party = null, $reason = null)
    {
        $instance = new RefundCancellationDetails($party, $reason);
        $expected = array(
            'party'  => $party,
            'reason' => $reason,
        );
        self::assertEquals($expected, $instance->jsonSerialize());
    }
}
