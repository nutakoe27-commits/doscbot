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

namespace Tests\YooKassa\Model\PaymentMethod;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\PaymentData\B2b\Sberbank\VatData;
use YooKassa\Model\PaymentMethod\BankCard;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData;
use YooKassa\Model\PaymentMethod\PaymentMethodElectronicCertificate;
use YooKassa\Model\PaymentMethodType;

class PaymentMethodElectronicCertificateTest extends TestCase
{
    /**
     * @return PaymentMethodElectronicCertificate
     */
    protected function getTestInstance()
    {
        return new PaymentMethodElectronicCertificate();
    }

    /**
     * @dataProvider validCardDataProvider
     * @param mixed $value
     */
    public function testGetSetCard($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getCard());
        self::assertNull($instance->card);

        $instance->setCard($value);

        if ($value instanceof BankCard) {
            self::assertSame($value, $instance->getCard());
            self::assertSame($value, $instance->card);
        } elseif (is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\BankCard', $instance->getCard());
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\BankCard', $instance->card);
        } else {
            self::assertNull($instance->getCard());
        }

        $instance = $this->getTestInstance();
        $instance->card = $value;

        if ($value instanceof BankCard) {
            self::assertSame($value, $instance->getCard());
            self::assertSame($value, $instance->card);
        } elseif (is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\BankCard', $instance->getCard());
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\BankCard', $instance->card);
        } else {
            self::assertNull($instance->getCard());
        }
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidCard($value)
    {
        $this->getTestInstance()->setCard($value);
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidCard($value)
    {
        $this->getTestInstance()->card = $value;
    }

    /**
     * @dataProvider validElectronicCertificateDataProvider
     * @param mixed $value
     */
    public function testGetSetElectronicCertificate($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getElectronicCertificate());
        self::assertNull($instance->electronicCertificate);

        $instance->setElectronicCertificate($value);

        if ($value instanceof ElectronicCertificatePaymentData) {
            self::assertSame($value, $instance->getElectronicCertificate());
            self::assertSame($value, $instance->electronicCertificate);
        } elseif (is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->getElectronicCertificate());
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->electronicCertificate);
        } else {
            self::assertNull($instance->getElectronicCertificate());
        }

        $instance = $this->getTestInstance();
        $instance->electronicCertificate = $value;

        if ($value instanceof ElectronicCertificatePaymentData) {
            self::assertSame($value, $instance->getElectronicCertificate());
            self::assertSame($value, $instance->electronicCertificate);
        } elseif (is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->getElectronicCertificate());
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->electronicCertificate);
        } else {
            self::assertNull($instance->getElectronicCertificate());
        }

        $instance = $this->getTestInstance();
        $instance->electronic_certificate = $value;

        if ($value instanceof ElectronicCertificatePaymentData) {
            self::assertSame($value, $instance->getElectronicCertificate());
            self::assertSame($value, $instance->electronic_certificate);
        } elseif (is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->getElectronicCertificate());
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->electronic_certificate);
        } else {
            self::assertNull($instance->getElectronicCertificate());
        }
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidElectronicCertificate($value)
    {
        $this->getTestInstance()->setElectronicCertificate($value);
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidElectronicCertificate($value)
    {
        $this->getTestInstance()->electronicCertificate = $value;
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidElectronicCertificateSnakeCase($value)
    {
        $this->getTestInstance()->electronic_certificate = $value;
    }

    /**
     * @dataProvider validArticlesDataProvider
     * @param array $value
     */
    public function testGetSetArticles($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getArticles());
        self::assertNull($instance->articles);

        $instance->setArticles($value);

        if ($value !== null) {
            self::assertNotNull($instance->getArticles());
            self::assertNotNull($instance->articles);
            self::assertCount(count($value), $instance->getArticles());
        } else {
            self::assertNull($instance->getArticles());
            self::assertNull($instance->articles);
        }


        $instance = $this->getTestInstance();
        $instance->articles = $value;

        if ($value !== null) {
            self::assertNotNull($instance->getArticles());
            self::assertNotNull($instance->articles);
            self::assertCount(count($value), $instance->getArticles());
        } else {
            self::assertNull($instance->getArticles());
            self::assertNull($instance->articles);
        }
    }

    /**
     * @dataProvider invalidArticlesDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidArticles($value)
    {
        $this->getTestInstance()->setArticles($value);
    }

    /**
     * @dataProvider invalidArticlesDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticles($value)
    {
        $this->getTestInstance()->articles = $value;
    }

    /**
     * @return array
     */
    public function validCardDataProvider()
    {
        $result = array(
            array(null),
            array(new BankCard()),
        );

        $result[] = array(array());

        return $result;
    }

    /**
     * @return array
     */
    public function invalidCardDataProvider()
    {
        return array(
            array(0),
            array(1),
            array(-1),
            array('5'),
            array(new \stdClass()),
            array(Random::str(20)),
        );
    }

    /**
     * @return array
     */
    public function validElectronicCertificateDataProvider()
    {
        $result = array(
            array(null),
            array(new ElectronicCertificatePaymentData()),
        );

        $result[] = array(array());

        return $result;
    }

    /**
     * @return array
     */
    public function invalidElectronicCertificateDataProvider()
    {
        return array(
            array(0),
            array(1),
            array(-1),
            array('string'),
            array(new \stdClass()),
            array(Random::str(20)),
        );
    }

    /**
     * @return array
     */
    public function validArticlesDataProvider()
    {
        $result = array();

        $result[] = array(null);

        $certificate1 = new ElectronicCertificate();
        $certificate1->setCertificateId(Random::str(20, 30));
        $certificate1->setTruQuantity(Random::int(1, 10));
        $certificate1->setAvailableCompensation(new MonetaryAmount(Random::int(100, 1000), CurrencyCode::RUB));
        $certificate1->setAppliedCompensation(new MonetaryAmount(Random::int(0, 500), CurrencyCode::RUB));

        $certificate2 = new ElectronicCertificate();
        $certificate2->setCertificateId(Random::str(20, 30));
        $certificate2->setTruQuantity(Random::int(1, 10));
        $certificate2->setAvailableCompensation(new MonetaryAmount(Random::int(100, 1000), CurrencyCode::RUB));
        $certificate2->setAppliedCompensation(new MonetaryAmount(Random::int(0, 500), CurrencyCode::RUB));

        $article1 = new ElectronicCertificateApprovedPaymentArticle();
        $article1->setArticleNumber(Random::int(1, 999));
        $article1->setTruCode(Random::str(30, '0123456789.'));
        $article1->setCertificates(array($certificate1));

        $article2 = new ElectronicCertificateApprovedPaymentArticle();
        $article2->setArticleNumber(Random::int(1, 999));
        $article2->setTruCode(Random::str(30, '0123456789.'));
        $article2->setCertificates(array($certificate2));

        $result[] = array(array($article1, $article2));

        $certificateData1 = array(
            'certificate_id' => Random::str(20, 30),
            'tru_quantity' => Random::int(1, 10),
            'available_compensation' => array(
                'value' => Random::int(100, 1000),
                'currency' => CurrencyCode::RUB
            ),
            'applied_compensation' => array(
                'value' => Random::int(0, 500),
                'currency' => CurrencyCode::RUB
            )
        );

        $certificateData2 = array(
            'certificate_id' => Random::str(20, 30),
            'tru_quantity' => Random::int(1, 10),
            'available_compensation' => array(
                'value' => Random::int(100, 1000),
                'currency' => CurrencyCode::RUB
            ),
            'applied_compensation' => array(
                'value' => Random::int(0, 500),
                'currency' => CurrencyCode::RUB
            )
        );

        $articleData1 = array(
            'article_number' => Random::int(1, 999),
            'tru_code' => Random::str(30, '0123456789.'),
            'certificates' => array($certificateData1),
        );

        $articleData2 = array(
            'article_number' => Random::int(1, 999),
            'tru_code' => Random::str(30, '0123456789.'),
            'certificates' => array($certificateData2),
        );

        $result[] = array(array($articleData1, $articleData2));

        return $result;
    }

    /**
     * @return array
     */
    public function invalidArticlesDataProvider()
    {
        return array(
            array(1),
            array('string'),
            array(new \stdClass()),
            array(array(new \stdClass())),
            array(array(1, 2, 3)),
            array(array('invalid_data')),
        );
    }
}
