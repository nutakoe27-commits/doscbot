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

namespace Tests\YooKassa\Common;

use PHPUnit\Framework\TestCase;
use YooKassa\Common\AbstractEnum;

class AbstractEnumTest extends TestCase
{
    /**
     * @dataProvider enumDataProvider
     * @param $value
     * @param $exists
     */
    public function testValueExists($value, $exists)
    {
        if ($exists) {
            self::assertTrue(TestAbstractEnum::valueExists($value));
        } else {
            self::assertFalse(TestAbstractEnum::valueExists($value));
        }
    }

    public function testGetValidValues()
    {
        foreach (TestAbstractEnum::getValidValues() as $value) {
            self::assertTrue(TestAbstractEnum::valueExists($value));
        }
    }

    public function enumDataProvider()
    {
        return array(
            array(TestAbstractEnum::ENUM_VALUE_1, true),
            array(TestAbstractEnum::ENUM_VALUE_2, true),
            array(TestAbstractEnum::ENUM_DISABLED_VALUE_1, true),
            array(TestAbstractEnum::ENUM_DISABLED_VALUE_2, true),
            array('invalid_value', false),
            array(0, false),
        );
    }

    public function testGetEnabledValues()
    {
        $values = TestAbstractEnum::getEnabledValues();
        foreach ($values as $value) {
            self::assertTrue(TestAbstractEnum::valueExists($value));
        }
        self::assertFalse(in_array(TestAbstractEnum::ENUM_DISABLED_VALUE_1, $values));
        self::assertFalse(in_array(TestAbstractEnum::ENUM_DISABLED_VALUE_2, $values));
    }
}

class TestAbstractEnum extends AbstractEnum
{
    const ENUM_VALUE_1 = 'enum_value_1';
    const ENUM_VALUE_2 = 'enum_value_2';
    const ENUM_DISABLED_VALUE_1 = 'enum_disabled_value_1';
    const ENUM_DISABLED_VALUE_2 = 'enum_disabled_value_2';

    protected static $validValues = array(
        self::ENUM_VALUE_1 => true,
        self::ENUM_VALUE_2  => true,
        self::ENUM_DISABLED_VALUE_1 => false,
        self::ENUM_DISABLED_VALUE_2 => false,
    );
}
