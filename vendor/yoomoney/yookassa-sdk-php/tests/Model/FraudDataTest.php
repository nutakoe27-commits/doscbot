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
use YooKassa\Helpers\Random;
use YooKassa\Model\FraudData;

class FraudDataTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetSetToppedUpPhone($options)
    {
        $instance = new FraudData();

        self::assertNull($instance->getToppedUpPhone());
        self::assertNull($instance->topped_up_phone);

        $instance->setToppedUpPhone($options['topped_up_phone']);
        self::assertEquals($options['topped_up_phone'], $instance->getToppedUpPhone());
        self::assertEquals($options['topped_up_phone'], $instance->topped_up_phone);

        $instance = new FraudData();
        $instance->topped_up_phone = $options['topped_up_phone'];
        self::assertEquals($options['topped_up_phone'], $instance->getToppedUpPhone());
        self::assertEquals($options['topped_up_phone'], $instance->topped_up_phone);
    }

    /**
     * @dataProvider invalidDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testSetInvalidToppedUpPhone($value)
    {
        $instance = new FraudData();
        $instance->setToppedUpPhone($value);
    }

    public function validDataProvider()
    {
        $result = array();
        $result[] = array(array('topped_up_phone' => null));
        $result[] = array(array('topped_up_phone' => ''));
        for ($i = 0; $i < 10; $i++) {
            $payment = array(
                'topped_up_phone' => Random::str(11, 15, '0123456789'),
            );
            $result[] = array($payment);
        }
        return $result;
    }

    public function invalidDataProvider()
    {
        return array(
            array(new \stdClass()),
            array(true),
            array(false),
            array(array(123)),
            array(Random::str(16, 30, '0123456789')),
        );
    }
}
