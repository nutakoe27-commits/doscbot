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

namespace Tests\YooKassa\Common\Exceptions;

use YooKassa\Common\Exceptions\InvalidPropertyException;
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;

class InvalidPropertyValueTypeExceptionTest extends InvalidPropertyExceptionTest
{
    /**
     * @param string $message
     * @param string $property
     * @param null $value
     * @return InvalidPropertyValueTypeException
     */
    protected function getTestInstance($message, $property, $value = null)
    {
        return new InvalidPropertyValueTypeException($message, 0, $property, $value);
    }

    /**
     * @dataProvider validTypeDataProvider
     * @param mixed $value
     * @param string $type
     */
    public function testGetType($value, $type)
    {
        $instance = $this->getTestInstance('', '', $value);
        self::assertEquals($type, $instance->getType());
    }

    public function validTypeDataProvider()
    {
        return array(
            array(null, 'null'),
            array('', 'string'),
            array('value', 'string'),
            array(array('test'), 'array'),
            array(new \stdClass(), 'stdClass'),
            array(new \DateTime(), 'DateTime'),
            array(new InvalidPropertyException(), 'YooKassa\\Common\\Exceptions\\InvalidPropertyException'),
            array(fopen(__FILE__, 'r'), 'resource'),
            array(true, 'boolean'),
            array(false, 'boolean'),
            array(0, 'integer'),
            array(0.01, 'double'),
        );
    }
}
