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

namespace Tests\YooKassa\Helpers;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\StringObject;
use YooKassa\Helpers\TypeCast;

class TypeCastTest extends TestCase
{
    /**
     * @dataProvider canCastToStringDataProvider
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToString($value, $can)
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToString($value));
        } else {
            self::assertFalse(TypeCast::canCastToString($value));
        }
    }

    /**
     * @dataProvider canCastToEnumStringDataProvider
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToEnumString($value, $can)
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToEnumString($value));
        } else {
            self::assertFalse(TypeCast::canCastToEnumString($value));
        }
    }

    /**
     * @dataProvider canCastToDateTimeDataProvider
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToDateTime($value, $can)
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToDateTime($value));
        } else {
            self::assertFalse(TypeCast::canCastToDateTime($value));
        }
    }

    /**
     * @dataProvider castToDateTimeDataProvider
     * @param mixed $value
     * @param int $expected
     * @param bool $valud
     */
    public function testCastToDateTime($value, $expected, $valid)
    {
        $instance = TypeCast::castToDateTime($value);
        if ($valid) {
            if ($value instanceof \DateTime) {
                self::assertEquals($value->getTimestamp(), $instance->getTimestamp());
                self::assertNotSame($value, $instance);
            } else {
                self::assertEquals($expected, $instance->getTimestamp());
            }
        } else {
            self::assertNull($instance);
        }
    }

    public function canCastToStringDataProvider()
    {
        return array(
            array('', true),
            array('test_string', true),
            array(0, true),
            array(1, true),
            array(-1, true),
            array(0.0, true),
            array(-0.001, true),
            array(0.001, true),
            array(true, false),
            array(false, false),
            array(null, false),
            array(array(), false),
            array(new \stdClass(), false),
            array(fopen(__FILE__, 'r'), false),
            array(new StringObject('test'), true),
        );
    }

    public function canCastToEnumStringDataProvider()
    {
        return array(
            array('', false),
            array('test_string', true),
            array(0, false),
            array(1, false),
            array(-1, false),
            array(0.0, false),
            array(-0.001, false),
            array(0.001, false),
            array(true, false),
            array(false, false),
            array(null, false),
            array(array(), false),
            array(new \stdClass(), false),
            array(fopen(__FILE__, 'r'), false),
            array(new StringObject('test'), true),
        );
    }

    public function canCastToDateTimeDataProvider()
    {
        return array(
            array('', false),
            array('test_string', true),
            array(0, true),
            array(1, true),
            array(-1, false),
            array(0.0, true),
            array(-0.001, false),
            array(0.001, true),
            array(true, false),
            array(false, false),
            array(null, false),
            array(array(), false),
            array(new \stdClass(), false),
            array(fopen(__FILE__, 'r'), false),
            array(new StringObject('test'), true),
            array(new \DateTime(), true),
        );
    }

    public function castToDateTimeDataProvider()
    {
        $result = array();

        $time = time();
        $result[] = array($time, $time, true);
        $result[] = array(date(YOOKASSA_DATE, $time), $time, true);
        $result[] = array(new \DateTime(date(YOOKASSA_DATE, $time)), $time, true);
        $result[] = array('3234-234-23', $time, false);
        $result[] = array(array(), $time, false);

        return $result;
    }
}
