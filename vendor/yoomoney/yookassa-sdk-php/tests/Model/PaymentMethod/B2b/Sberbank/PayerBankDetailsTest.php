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

namespace Tests\YooKassa\Model\PaymentData;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\PaymentMethod\B2b\Sberbank\PayerBankDetails;

class PayerBankDetailsTest extends TestCase
{
    /**
     * @return PayerBankDetails
     */
    protected function getTestInstance()
    {
        return new PayerBankDetails();
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetFullName($value)
    {
        $this->getAndSetTest($value, 'fullName');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetShortName($value)
    {
        $this->getAndSetTest($value, 'shortName');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetAddress($value)
    {
        $this->getAndSetTest($value, 'address');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetInn($value)
    {
        $this->getAndSetTest($value, 'inn');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetKpp($value)
    {
        $this->getAndSetTest($value, 'kpp');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetBankName($value)
    {
        $this->getAndSetTest($value, 'bankName');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetBankBranch($value)
    {
        $this->getAndSetTest($value, 'bankBranch');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetBankBik($value)
    {
        $this->getAndSetTest($value, 'bankBik');
    }

    /**
     * @dataProvider validStringDataProvider
     * @param string $value
     */
    public function testGetSetAccount($value)
    {
        $this->getAndSetTest($value, 'account');
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validStringDataProvider()
    {
        $result = array(array(Random::str(10)));

        return $result;
    }

    /**
     * @param $value
     * @param string $property
     * @param null $snakeCase
     */
    protected function getAndSetTest($value, $property, $snakeCase = null)
    {
        $getter = 'get' . ucfirst($property);
        $setter = 'set' . ucfirst($property);

        $instance = $this->getTestInstance();

        self::assertNull($instance->{$getter}());
        self::assertNull($instance->{$property});
        if ($snakeCase !== null) {
            self::assertNull($instance->{$snakeCase});
        }

        $instance->{$setter}($value);

        self::assertEquals($value, $instance->{$getter}());
        self::assertEquals($value, $instance->{$property});
        if ($snakeCase !== null) {
            self::assertEquals($value, $instance->{$snakeCase});
        }

        $instance = $this->getTestInstance();

        $instance->{$property} = $value;

        self::assertEquals($value, $instance->{$getter}());
        self::assertEquals($value, $instance->{$property});
        if ($snakeCase !== null) {
            self::assertEquals($value, $instance->{$snakeCase});
        }

        if ($snakeCase !== null) {
            $instance = $this->getTestInstance();

            $instance->{$snakeCase} = $value;

            self::assertEquals($value, $instance->{$getter}());
            self::assertEquals($value, $instance->{$property});
            self::assertEquals($value, $instance->{$snakeCase});
        }
    }
}
