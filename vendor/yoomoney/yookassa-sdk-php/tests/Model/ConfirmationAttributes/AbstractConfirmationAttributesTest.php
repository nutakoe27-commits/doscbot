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

namespace Tests\YooKassa\Model\ConfirmationAttributes;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\ConfirmationAttributes\AbstractConfirmationAttributes;

abstract class AbstractConfirmationAttributesTest extends TestCase
{
    /**
     * @return AbstractConfirmationAttributes
     */
    abstract protected function getTestInstance();

    /**
     * @return string
     */
    abstract protected function getExpectedType();

    /**
     *
     */
    public function testGetType()
    {
        $instance = $this->getTestInstance();
        self::assertEquals($this->getExpectedType(), $instance->getType());
    }

    /**
     * @dataProvider invalidTypeDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testInvalidType($value)
    {
        new TestConfirmation($value);
    }

    public function invalidTypeDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(Random::str(40)),
            array(0),
            array(array()),
            array(new \stdClass()),
        );
    }


    /**
     * @dataProvider validLocaleDataProvider
     *
     * @param $value
     */
    public function testSetterLocale($value)
    {
        $instance = $this->getTestInstance();
        $instance->setLocale($value);
        self::assertEquals((string)$value, $instance->getLocale());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validLocaleDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(
                Random::str(2, 2, 'abcdefghijklmnopqrtuvwxyz')
                .  '_' .
                Random::str(2, 2, 'ABCDEFGHIJKLMNOPQRTUVWXYZ')
            ),
            array('ru_RU'),
            array('en_US'),
        );
    }

    /**
     * @dataProvider invalidLocaleDataProvider
     * @expectedException \InvalidArgumentException
     *
     * @param $value
     */
    public function testSetInvalidLocale($value)
    {
        $this->getTestInstance()->setLocale($value);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function invalidLocaleDataProvider()
    {
        return array(
            array(Random::str(4)),
            array(Random::str(6)),
            array(0),
            array(array()),
            array(new \stdClass()),
            array(
                Random::str(2, 2, 'ABCDEFGHIJKLMNOPQRTUVWXYZ')
                .  '_' .
                Random::str(2, 2, 'abcdefghijklmnopqrtuvwxyz')
            ),
        );
    }
}

class TestConfirmation extends AbstractConfirmationAttributes
{
    public function __construct($type)
    {
        $this->setType($type);
    }
}
