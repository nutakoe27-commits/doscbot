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

namespace Tests\YooKassa\Model\PaymentMethod\ElectronicCertificate;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate;

class ElectronicCertificateTest extends TestCase
{
    /**
     * @return ElectronicCertificate
     */
    protected function getTestInstance()
    {
        return new ElectronicCertificate();
    }

    /**
     * @dataProvider validCertificateIdDataProvider
     * @param string $value
     */
    public function testGetSetCertificateId($value)
    {
        $this->getAndSetTest($value, 'certificateId', 'certificate_id');
    }

    /**
     * @dataProvider invalidCertificateIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidCertificateId($value)
    {
        $this->getTestInstance()->setCertificateId($value);
    }

    /**
     * @dataProvider invalidCertificateIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidCertificateId($value)
    {
        $this->getTestInstance()->certificateId = $value;
    }

    /**
     * @dataProvider invalidCertificateIdDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidCertificateIdSnakeCase($value)
    {
        $this->getTestInstance()->certificate_id = $value;
    }

    /**
     * @dataProvider validTruQuantityDataProvider
     * @param int $value
     */
    public function testGetSetTruQuantity($value)
    {
        $this->getAndSetTest($value, 'truQuantity', 'tru_quantity');
    }

    /**
     * @dataProvider invalidTruQuantityDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidTruQuantity($value)
    {
        $this->getTestInstance()->setTruQuantity($value);
    }

    /**
     * @dataProvider invalidTruQuantityDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidTruQuantity($value)
    {
        $this->getTestInstance()->truQuantity = $value;
    }

    /**
     * @dataProvider invalidTruQuantityDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidTruQuantitySnakeCase($value)
    {
        $this->getTestInstance()->tru_quantity = $value;
    }

    /**
     * @dataProvider validAvailableCompensationDataProvider
     * @param mixed $value
     */
    public function testGetSetAvailableCompensation($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getAvailableCompensation());
        self::assertNull($instance->availableCompensation);

        $instance->setAvailableCompensation($value);
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->availableCompensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->availableCompensation->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->availableCompensation = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->availableCompensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->availableCompensation->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->available_compensation = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->available_compensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAvailableCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->available_compensation->getCurrency());
        }
    }

    /**
     * @dataProvider invalidAvailableCompensationDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidAvailableCompensation($value)
    {
        $this->getTestInstance()->setAvailableCompensation($value);
    }

    /**
     * @dataProvider invalidAvailableCompensationDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidAvailableCompensation($value)
    {
        $this->getTestInstance()->availableCompensation = $value;
    }

    /**
     * @dataProvider validAppliedCompensationDataProvider
     * @param mixed $value
     */
    public function testGetSetAppliedCompensation($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getAppliedCompensation());
        self::assertNull($instance->appliedCompensation);

        $instance->setAppliedCompensation($value);
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->appliedCompensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->appliedCompensation->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->appliedCompensation = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->appliedCompensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->appliedCompensation->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->applied_compensation = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value['currency'], $instance->applied_compensation->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getAppliedCompensation()->getValue());
            self::assertEquals($value->getCurrency(), $instance->applied_compensation->getCurrency());
        }
    }

    /**
     * @dataProvider invalidAppliedCompensationDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidAppliedCompensation($value)
    {
        $this->getTestInstance()->setAppliedCompensation($value);
    }

    /**
     * @dataProvider invalidAppliedCompensationDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidAppliedCompensation($value)
    {
        $this->getTestInstance()->appliedCompensation = $value;
    }

    /**
     * @return array
     */
    public function validCertificateIdDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::str(20, 30));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidCertificateIdDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(1),
            array(-1),
            array(array()),
            array(new \stdClass()),
            array(Random::str(19)),
            array(Random::str(31)),
        );
    }

    /**
     * @return array
     */
    public function validTruQuantityDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::int(1, 100));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidTruQuantityDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(array()),
            array(new \stdClass()),
        );
    }

    /**
     * @return array
     */
    public function validAvailableCompensationDataProvider()
    {
        $result = array(
            array(
                array(
                    'value' => Random::int(1, 10000),
                    'currency' => CurrencyCode::RUB,
                )
            ),
            array(
                array(
                    'value' => Random::int(1, 10000),
                    'currency' => CurrencyCode::USD,
                )
            ),
            array(new MonetaryAmount(Random::int(1, 10000), CurrencyCode::RUB)),
            array(new MonetaryAmount(Random::int(1, 10000), CurrencyCode::EUR)),
        );

        return $result;
    }

    /**
     * @return array
     */
    public function invalidAvailableCompensationDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(1.0),
            array(1),
            array(true),
            array(false),
            array(new \stdClass()),
        );
    }

    /**
     * @return array
     */
    public function validAppliedCompensationDataProvider()
    {
        $result = array(
            array(
                array(
                    'value' => Random::int(0, 10000),
                    'currency' => CurrencyCode::RUB,
                )
            ),
            array(
                array(
                    'value' => Random::int(0, 10000),
                    'currency' => CurrencyCode::USD,
                )
            ),
            array(new MonetaryAmount(Random::int(0, 10000), CurrencyCode::RUB)),
            array(new MonetaryAmount(Random::int(0, 10000), CurrencyCode::EUR)),
        );

        return $result;
    }

    /**
     * @return array
     */
    public function invalidAppliedCompensationDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(1.0),
            array(1),
            array(true),
            array(false),
            array(new \stdClass()),
        );
    }

    /**
     * @param mixed $value
     * @param string $property
     * @param string|null $snakeCase
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
