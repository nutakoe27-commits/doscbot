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
use YooKassa\Model\ThreeDSecure;

class ThreeDSecureTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     *
     * @param $threeDSecure
     */
    public function testConstructor($threeDSecure)
    {
        $instance = new ThreeDSecure($threeDSecure);

        self::assertEquals($threeDSecure['applied'], $instance->getApplied());
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $threeDSecure
     */
    public function testGetSetApplied($threeDSecure)
    {
        $instance = new ThreeDSecure($threeDSecure);

        self::assertEquals($threeDSecure['applied'], $instance->getApplied());

        $instance = new ThreeDSecure();

        $instance->setApplied($threeDSecure['applied']);
        self::assertEquals($threeDSecure['applied'], $instance->getApplied());
        self::assertEquals($threeDSecure['applied'], $instance->applied);
    }

    /**
     * @dataProvider invalidValueDataProvider
     * @param mixed $value
     * @param string $exceptionClassName
     */
    public function testSetInvalidApplied($value, $exceptionClassName)
    {
        $instance = new ThreeDSecure();
        try {
            $instance->setApplied($value);
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassName, $e);
        }
    }

    /**
     * @return array
     */
    public function validDataProvider()
    {
        return array(
            array(
                'threeDSecure' => array(
                    'applied' => true
                )
            ),
            array(
                'threeDSecure' => array(
                    'applied' => false
                )
            ),
            array(
                'threeDSecure' => new ThreeDSecure(array('applied' => false))
            )
        );
    }

    public function invalidValueDataProvider()
    {
        $exceptionNamespace = 'YooKassa\\Common\\Exceptions\\';
        return array(
            array(array(), $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(fopen(__FILE__, 'r'), $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(-1, $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(-0.01, $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array(0.0, $exceptionNamespace . 'InvalidPropertyValueTypeException'),
            array('', $exceptionNamespace . 'EmptyPropertyValueException'),
            array(null, $exceptionNamespace . 'EmptyPropertyValueException'),
        );
    }


    /**
     * @dataProvider validDataProvider
     *
     * @param array $threeDSecure
     */
    public function testJsonSerialize($threeDSecure)
    {
        if (is_object($threeDSecure)) {
            $threeDSecure = $threeDSecure->jsonSerialize();
        }

        $instance = new ThreeDSecure($threeDSecure);

        self::assertEquals($threeDSecure, $instance->jsonSerialize());
    }
}
