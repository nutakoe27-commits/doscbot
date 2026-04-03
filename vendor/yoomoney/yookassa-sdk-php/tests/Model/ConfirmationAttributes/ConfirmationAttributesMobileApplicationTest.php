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

use YooKassa\Model\ConfirmationAttributes\ConfirmationAttributesMobileApplication;
use YooKassa\Model\ConfirmationType;

class ConfirmationAttributesMobileApplicationTest extends AbstractConfirmationAttributesTest
{
    /**
     * @return ConfirmationAttributesMobileApplication
     */
    protected function getTestInstance()
    {
        return new ConfirmationAttributesMobileApplication();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return ConfirmationType::MOBILE_APPLICATION;
    }

    /**
     * @dataProvider validUrlDataProvider
     * @param $value
     */
    public function testGetSetReturnUrl($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getReturnUrl());
        self::assertNull($instance->returnUrl);
        self::assertNull($instance->return_url);

        $instance->setReturnUrl($value);
        if ($value === null || $value === '') {
            self::assertNull($instance->getReturnUrl());
            self::assertNull($instance->returnUrl);
            self::assertNull($instance->return_url);
        } else {
            self::assertEquals($value, $instance->getReturnUrl());
            self::assertEquals($value, $instance->returnUrl);
            self::assertEquals($value, $instance->return_url);
        }

        $instance->setReturnUrl(null);
        self::assertNull($instance->getReturnUrl());
        self::assertNull($instance->returnUrl);
        self::assertNull($instance->return_url);

        $instance->returnUrl = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getReturnUrl());
            self::assertNull($instance->returnUrl);
            self::assertNull($instance->return_url);
        } else {
            self::assertEquals($value, $instance->getReturnUrl());
            self::assertEquals($value, $instance->returnUrl);
            self::assertEquals($value, $instance->return_url);
        }

        $instance->setReturnUrl(null);
        self::assertNull($instance->getReturnUrl());
        self::assertNull($instance->returnUrl);
        self::assertNull($instance->return_url);

        $instance->return_url = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getReturnUrl());
            self::assertNull($instance->returnUrl);
            self::assertNull($instance->return_url);
        } else {
            self::assertEquals($value, $instance->getReturnUrl());
            self::assertEquals($value, $instance->returnUrl);
            self::assertEquals($value, $instance->return_url);
        }
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidReturnUrl($value)
    {
        $instance = $this->getTestInstance();
        $instance->setReturnUrl($value);
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidReturnUrl($value)
    {
        $instance = $this->getTestInstance();
        $instance->returnUrl = $value;
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidReturn_url($value)
    {
        $instance = $this->getTestInstance();
        $instance->return_url = $value;
    }

    public function validEnforceDataProvider()
    {
        return array(
            array(true),
            array(false),
            array(null),
            array(''),
            array(0),
            array(1),
            array(100),
        );
    }

    public function invalidEnforceDataProvider()
    {
        return array(
            array('true'),
            array('false'),
            array(array()),
            array(new \stdClass()),
        );
    }

    public function validUrlDataProvider()
    {
        return array(
            array('https://test.ru'),
            array(null),
            array(''),
        );
    }

    public function invalidUrlDataProvider()
    {
        return array(
            array(true),
            array(false),
            array(array()),
            array(new \stdClass()),
        );
    }
}
