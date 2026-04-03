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

use PHPUnit\Framework\TestCase;
use YooKassa\Common\Exceptions\InvalidPropertyException;
use YooKassa\Helpers\StringObject;

class InvalidPropertyExceptionTest extends TestCase
{
    /**
     * @param string $message
     * @param string $property
     * @return InvalidPropertyException
     */
    protected function getTestInstance($message, $property)
    {
        return new InvalidPropertyException($message, 0, $property);
    }

    /**
     * @dataProvider validPropertyDataProvider
     * @param $property
     */
    public function testGetProperty($property)
    {
        $instance = $this->getTestInstance('', $property);
        self::assertEquals((string)$property, $instance->getProperty());
    }

    public function validPropertyDataProvider()
    {
        return array(
            array(''),
            array('property'),
            array(new StringObject('property')),
        );
    }
}
