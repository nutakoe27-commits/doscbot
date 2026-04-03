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
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle;

class ElectronicCertificateApprovedPaymentArticleTest extends TestCase
{
    /**
     * @return ElectronicCertificateApprovedPaymentArticle
     */
    protected function getTestInstance()
    {
        return new ElectronicCertificateApprovedPaymentArticle();
    }

    /**
     * @dataProvider validArticleNumberDataProvider
     * @param int $value
     */
    public function testGetSetArticleNumber($value)
    {
        $this->getAndSetTest($value, 'articleNumber', 'article_number');
    }

    /**
     * @dataProvider invalidArticleNumberDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidArticleNumber($value)
    {
        $this->getTestInstance()->setArticleNumber($value);
    }

    /**
     * @dataProvider invalidArticleNumberDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticleNumber($value)
    {
        $this->getTestInstance()->articleNumber = $value;
    }

    /**
     * @dataProvider invalidArticleNumberDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticleNumberSnakeCase($value)
    {
        $this->getTestInstance()->article_number = $value;
    }

    /**
     * @dataProvider validTruCodeDataProvider
     * @param string $value
     */
    public function testGetSetTruCode($value)
    {
        $this->getAndSetTest($value, 'truCode', 'tru_code');
    }

    /**
     * @dataProvider invalidTruCodeDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidTruCode($value)
    {
        $this->getTestInstance()->setTruCode($value);
    }

    /**
     * @dataProvider invalidTruCodeDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidTruCode($value)
    {
        $this->getTestInstance()->truCode = $value;
    }

    /**
     * @dataProvider invalidTruCodeDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidTruCodeSnakeCase($value)
    {
        $this->getTestInstance()->tru_code = $value;
    }

    /**
     * @dataProvider validArticleCodeDataProvider
     * @param string|null $value
     */
    public function testGetSetArticleCode($value)
    {
        $this->getAndSetTest($value, 'articleCode', 'article_code');
    }

    /**
     * @dataProvider invalidArticleCodeDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidArticleCode($value)
    {
        $this->getTestInstance()->setArticleCode($value);
    }

    /**
     * @dataProvider invalidArticleCodeDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticleCode($value)
    {
        $this->getTestInstance()->articleCode = $value;
    }

    /**
     * @dataProvider validCertificatesDataProvider
     * @param array $value
     */
    public function testGetSetCertificates($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getCertificates());
        self::assertNull($instance->certificates);

        $instance->setCertificates($value);

        self::assertNotNull($instance->getCertificates());
        self::assertNotNull($instance->certificates);
        self::assertCount(count($value), $instance->getCertificates());

        foreach ($instance->getCertificates() as $index => $certificate) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate', $certificate);
            if (is_array($value[$index])) {
                self::assertEquals($value[$index]['certificate_id'], $certificate->getCertificateId());
            } else {
                self::assertSame($value[$index], $certificate);
            }
        }

        $instance = $this->getTestInstance();
        $instance->certificates = $value;

        self::assertNotNull($instance->getCertificates());
        self::assertNotNull($instance->certificates);
        self::assertCount(count($value), $instance->getCertificates());
    }

    /**
     * @dataProvider invalidCertificatesDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidCertificates($value)
    {
        $this->getTestInstance()->setCertificates($value);
    }

    /**
     * @dataProvider invalidCertificatesDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidCertificates($value)
    {
        $this->getTestInstance()->certificates = $value;
    }

    /**
     * @return array
     */
    public function validArticleNumberDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::int(1, 999));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidArticleNumberDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(1000),
            array(-1),
            array(array()),
            array(new \stdClass()),
        );
    }

    /**
     * @return array
     */
    public function validTruCodeDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::str(30, '0123456789.'));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidTruCodeDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(1),
            array(-1),
            array(array()),
            array(new \stdClass()),
            array(Random::str(29, '0123456789.')),
            array(Random::str(31, '0123456789.')),
        );
    }

    /**
     * @return array
     */
    public function validArticleCodeDataProvider()
    {
        $result = array(
            array(null),
            array(''),
        );
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::str(1, 128));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidArticleCodeDataProvider()
    {
        return array(
            array(array()),
            array(new \stdClass()),
            array(Random::str(129)),
        );
    }

    /**
     * @return array
     */
    public function validCertificatesDataProvider()
    {
        $result = array();

        $certificate1 = new ElectronicCertificate();
        $certificate1->setCertificateId(Random::str(20, 30));
        $certificate1->setTruQuantity(Random::int(1, 10));
        $certificate1->setAvailableCompensation(array(
            'value' => Random::int(100, 1000),
            'currency' => CurrencyCode::RUB
        ));
        $certificate1->setAppliedCompensation(array(
            'value' => Random::int(0, 500),
            'currency' => CurrencyCode::RUB
        ));

        $certificate2 = new ElectronicCertificate();
        $certificate2->setCertificateId(Random::str(20, 30));
        $certificate2->setTruQuantity(Random::int(1, 10));
        $certificate2->setAvailableCompensation(array(
            'value' => Random::int(100, 1000),
            'currency' => CurrencyCode::RUB
        ));
        $certificate2->setAppliedCompensation(array(
            'value' => Random::int(0, 500),
            'currency' => CurrencyCode::RUB
        ));

        $result[] = array(array($certificate1, $certificate2));

        $certificateData1 = array(
            'certificate_id' => Random::str(20, 30),
            'tru_quantity' => Random::int(1, 10),
            'available_compensation' => array(
                'value' => Random::int(100, 1000),
                'currency' => CurrencyCode::USD
            ),
            'applied_compensation' => array(
                'value' => Random::int(0, 500),
                'currency' => CurrencyCode::USD
            )
        );

        $certificateData2 = array(
            'certificate_id' => Random::str(20, 30),
            'tru_quantity' => Random::int(1, 10),
            'available_compensation' => array(
                'value' => Random::int(100, 1000),
                'currency' => CurrencyCode::EUR
            ),
            'applied_compensation' => array(
                'value' => Random::int(0, 500),
                'currency' => CurrencyCode::EUR
            )
        );

        $result[] = array(array($certificateData1, $certificateData2));

        return $result;
    }

    /**
     * @return array
     */
    public function invalidCertificatesDataProvider()
    {
        return array(
            array(null),
            array(''),
            array(array()),
            array(1),
            array('string'),
            array(new \stdClass()),
            array(array(new \stdClass())),
            array(array(1, 2, 3)),
            array(array('invalid_data')),
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
