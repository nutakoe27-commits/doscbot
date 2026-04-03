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

namespace Model\Confirmation;

use Tests\YooKassa\Model\Confirmation\AbstractConfirmationTest;
use YooKassa\Model\Confirmation\AbstractConfirmation;
use YooKassa\Model\Confirmation\ConfirmationEmbedded;
use YooKassa\Model\ConfirmationType;

class ConfirmationEmbeddedTest extends AbstractConfirmationTest
{
    /**
     * @return AbstractConfirmation
     */
    protected function getTestInstance()
    {
        return new ConfirmationEmbedded();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return ConfirmationType::EMBEDDED;
    }

    /**
     * @dataProvider validConfirmationTokenDataProvider
     *
     * @param $value
     */
    public function testGetSetConfirmationToken($value)
    {
        /** @var ConfirmationEmbedded $instance */
        $instance = $this->getTestInstance();

        self::assertNull($instance->getConfirmationToken());
        self::assertNull($instance->confirmationToken);

        $instance->setConfirmationToken($value);
        if ($value === null || $value === '') {
            self::assertNull($instance->getConfirmationToken());
            self::assertNull($instance->confirmationToken);
        } else {
            self::assertEquals($value, $instance->getConfirmationToken());
            self::assertEquals($value, $instance->confirmationToken);
        }

        $instance                    = $this->getTestInstance();
        $instance->confirmationToken = $value;
        if ($value === null || $value === '') {
            self::assertNull($instance->getConfirmationToken());
            self::assertNull($instance->confirmationToken);
        } else {
            self::assertEquals($value, $instance->getConfirmationToken());
            self::assertEquals($value, $instance->confirmationToken);
        }
    }

    /**
     * @dataProvider invalidConfirmationTokenDataProvider
     * @expectedException \InvalidArgumentException
     * @param $value
     */
    public function testIvalidSetConfirmationToken($value)
    {
        $instance = $this->getTestInstance();
        $instance->setConfirmationToken($value);
    }

    public function validConfirmationTokenDataProvider()
    {
        return array(
            array(null),
            array(''),
            array('ct-2454fc2d-000f-5000-9000-12a816bfbb35'),
        );
    }

    public function invalidConfirmationTokenDataProvider()
    {
        return array(
            array(new \stdClass())
        );
    }
}
