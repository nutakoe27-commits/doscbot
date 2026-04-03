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
use YooKassa\Common\Exceptions\ExtensionNotFoundException;

class ExtensionNotFoundExceptionTest extends TestCase
{
    /**
     * @param string $name
     * @param int $code
     * @return ExtensionNotFoundException
     */
    protected function getTestInstance($name, $code = 0)
    {
        return new ExtensionNotFoundException($name, $code);
    }

    /**
     * @dataProvider messageDataProvider
     * @param $name
     * @param $excepted
     */
    public function testGetMessage($name, $excepted)
    {
        $instance = $this->getTestInstance($name);

        self::assertEquals($excepted, $instance->getMessage());
    }

    public function messageDataProvider()
    {
        return array(
            array("json", "json extension is not loaded!"),
            array("curl", "curl extension is not loaded!"),
            array("gd",   "gd extension is not loaded!"),
        );
    }
}
