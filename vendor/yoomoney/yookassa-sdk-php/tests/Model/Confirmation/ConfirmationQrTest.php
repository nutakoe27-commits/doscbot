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

namespace Tests\YooKassa\Model\Confirmation;

use YooKassa\Model\Confirmation\ConfirmationQr;
use YooKassa\Model\ConfirmationType;

class ConfirmationQrTest extends AbstractConfirmationTest
{
    /**
     * @return ConfirmationQr
     */
    protected function getTestInstance()
    {
        return new ConfirmationQr();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return ConfirmationType::QR;
    }

    /**
     * @dataProvider validUrlDataProvider
     * @param $value
     */
    public function testGetSetConfirmationData($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getConfirmationData());
        self::assertNull($instance->confirmationData);
        self::assertNull($instance->confirmation_data);

        $instance->setConfirmationData($value);
        if ($value === null || $value === '') {
            self::assertNull($instance->getConfirmationData());
            self::assertNull($instance->confirmationData);
            self::assertNull($instance->confirmation_data);
        } else {
            self::assertEquals($value, $instance->getConfirmationData());
            self::assertEquals($value, $instance->confirmationData);
            self::assertEquals($value, $instance->confirmation_data);
        }

        $instance->setConfirmationData(null);
        self::assertNull($instance->getConfirmationData());
        self::assertNull($instance->confirmationData);
        self::assertNull($instance->confirmation_data);

        $instance->confirmationData = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getConfirmationData());
            self::assertNull($instance->confirmationData);
            self::assertNull($instance->confirmation_data);
        } else {
            self::assertEquals($value, $instance->getConfirmationData());
            self::assertEquals($value, $instance->confirmationData);
            self::assertEquals($value, $instance->confirmation_data);
        }

        $instance->confirmation_data = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getConfirmationData());
            self::assertNull($instance->confirmationData);
            self::assertNull($instance->confirmation_data);
        } else {
            self::assertEquals($value, $instance->getConfirmationData());
            self::assertEquals($value, $instance->confirmationData);
            self::assertEquals($value, $instance->confirmation_data);
        }
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetInvalidConfirmationData($value)
    {
        $instance = $this->getTestInstance();
        $instance->setConfirmationData($value);
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidConfirmationData($value)
    {
        $instance = $this->getTestInstance();
        $instance->confirmationData = $value;
    }

    /**
     * @dataProvider invalidUrlDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testSetterInvalidConfirmation_data($value)
    {
        $instance = $this->getTestInstance();
        $instance->confirmation_data = $value;
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
            array('wechat://pay/testurl?pr=xXxXxX'),
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
