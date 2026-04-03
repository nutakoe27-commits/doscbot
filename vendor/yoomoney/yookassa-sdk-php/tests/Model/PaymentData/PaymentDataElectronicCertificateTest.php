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

use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle;
use YooKassa\Model\PaymentData\PaymentDataBankCardCard;
use YooKassa\Model\PaymentData\PaymentDataElectronicCertificate;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData;
use YooKassa\Model\PaymentMethodType;

class PaymentDataElectronicCertificateTest extends AbstractPaymentDataTest
{
    /**
     * @return PaymentDataElectronicCertificate
     */
    protected function getTestInstance()
    {
        return new PaymentDataElectronicCertificate();
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return PaymentMethodType::ELECTRONIC_CERTIFICATE;
    }

    /**
     * @dataProvider validCardDataProvider
     * @param mixed $value
     */
    public function testGetSetCard($value)
    {
        $this->getAndSetTest($value, 'card');
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidCard($value)
    {
        $this->getTestInstance()->setCard($value);
    }

    /**
     * @dataProvider invalidCardDataProvider
     * @expectedException \InvalidArgumentException
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
        $this->getAndSetTest($value, 'electronicCertificate', 'electronic_certificate');
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidElectronicCertificate($value)
    {
        $this->getTestInstance()->setElectronicCertificate($value);
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidElectronicCertificate($value)
    {
        $this->getTestInstance()->electronicCertificate = $value;
    }

    /**
     * @dataProvider invalidElectronicCertificateDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidElectronicCertificateSnakeCase($value)
    {
        $this->getTestInstance()->electronic_certificate = $value;
    }

    /**
     * @dataProvider validArticlesDataProvider
     * @param mixed $value
     */
    public function testGetSetArticles($value)
    {
        $this->getAndSetTest($value, 'articles');
    }

    /**
     * @dataProvider invalidArticlesDataProvider
     * @expectedException \InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidArticles($value)
    {
        $this->getTestInstance()->setArticles($value);
    }

    /**
     * @dataProvider invalidArticlesDataProvider
     * @expectedException \InvalidArgumentException
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
            array(new PaymentDataBankCardCard()),
        );

        $card = new PaymentDataBankCardCard();
        $card->setNumber('4111111111111111');
        $card->setExpiryYear('2025');
        $card->setExpiryMonth('12');
        $result[] = array($card);

        $result[] = array(array(
            'number' => '5555555555554444',
            'expiry_year' => '2026',
            'expiry_month' => '09',
        ));

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

        $paymentData = new ElectronicCertificatePaymentData();
        $paymentData->setAmount(array(
            'value' => 1000.00,
            'currency' => CurrencyCode::RUB
        ));
        $paymentData->setBasketId('basket_' . Random::str(10));
        $result[] = array($paymentData);

        $result[] = array(array(
            'amount' => array(
                'value' => 500.50,
                'currency' => CurrencyCode::EUR
            ),
            'basket_id' => 'test_basket_' . Random::str(5),
        ));

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
        $result = array(
            array(null),
            array(array()),
        );

        $article1 = new ElectronicCertificateArticle();
        $article1->setArticleNumber(1);
        $article1->setTruCode('329921120.06001010200080001643');
        $article1->setArticleName('Test Product 1');
        $article1->setQuantity(2);
        $article1->setPrice(array(
            'value' => 1000.00,
            'currency' => CurrencyCode::RUB
        ));

        $article2 = new ElectronicCertificateArticle();
        $article2->setArticleNumber(2);
        $article2->setTruCode('329921120.06001010200080001644');
        $article2->setArticleName('Test Product 2');
        $article2->setQuantity(1);
        $article2->setPrice(array(
            'value' => 500.00,
            'currency' => CurrencyCode::RUB
        ));

        $result[] = array(array($article1, $article2));

        $result[] = array(array(
            array(
                'article_number' => 3,
                'tru_code' => '329921120.06001010200080001645',
                'article_name' => 'Test Product 3',
                'quantity' => 3,
                'price' => array(
                    'value' => 300.00,
                    'currency' => CurrencyCode::RUB
                ),
                'article_code' => 'ART-001',
            ),
            array(
                'article_number' => 4,
                'tru_code' => '329921120.06001010200080001646',
                'article_name' => 'Test Product 4',
                'quantity' => 1,
                'price' => array(
                    'value' => 1200.00,
                    'currency' => CurrencyCode::RUB
                ),
            ),
        ));

        return $result;
    }

    /**
     * @return array
     */
    public function invalidArticlesDataProvider()
    {
        return array(
            array(0),
            array(1),
            array(-1),
            array('string'),
            array(new \stdClass()),
            array(Random::str(20)),
            array(array('invalid_element')),
            array(array(new \stdClass())),
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

        if (is_array($value) && !empty($value) && $property === 'articles') {
            self::assertNotNull($instance->{$getter}());
            self::assertNotNull($instance->{$property});
            self::assertCount(count($value), $instance->{$getter}());
        } elseif (is_array($value) && empty($value) && $property === 'articles') {
            self::assertNull($instance->{$getter}());
        } elseif ($property === 'electronicCertificate' && $value instanceof ElectronicCertificatePaymentData) {
            self::assertSame($value, $instance->{$getter}());
            self::assertSame($value, $instance->{$property});
        } elseif ($property === 'electronicCertificate' && is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->{$getter}());
        } elseif ($property === 'card' && $value instanceof PaymentDataBankCardCard) {
            self::assertSame($value, $instance->{$getter}());
            self::assertSame($value, $instance->{$property});
        } elseif ($property === 'card' && is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentData\PaymentDataBankCardCard', $instance->{$getter}());
        } else {
            self::assertEquals($value, $instance->{$getter}());
            self::assertEquals($value, $instance->{$property});
        }

        if ($snakeCase !== null && !is_array($value) && !is_object($value)) {
            self::assertEquals($value, $instance->{$snakeCase});
        }

        $instance = $this->getTestInstance();

        $instance->{$property} = $value;

        if (is_array($value) && !empty($value) && $property === 'articles') {
            self::assertNotNull($instance->{$getter}());
            self::assertNotNull($instance->{$property});
            self::assertCount(count($value), $instance->{$getter}());
        } elseif (is_array($value) && empty($value) && $property === 'articles') {
            self::assertNull($instance->{$getter}());
        } elseif ($property === 'electronicCertificate' && $value instanceof ElectronicCertificatePaymentData) {
            self::assertSame($value, $instance->{$getter}());
            self::assertSame($value, $instance->{$property});
        } elseif ($property === 'electronicCertificate' && is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData', $instance->{$getter}());
        } elseif ($property === 'card' && $value instanceof PaymentDataBankCardCard) {
            self::assertSame($value, $instance->{$getter}());
            self::assertSame($value, $instance->{$property});
        } elseif ($property === 'card' && is_array($value)) {
            self::assertInstanceOf('\YooKassa\Model\PaymentData\PaymentDataBankCardCard', $instance->{$getter}());
        } else {
            self::assertEquals($value, $instance->{$getter}());
            self::assertEquals($value, $instance->{$property});
        }

        if ($snakeCase !== null && !is_array($value) && !is_object($value)) {
            $instance = $this->getTestInstance();
            $instance->{$snakeCase} = $value;

            self::assertEquals($value, $instance->{$getter}());
            self::assertEquals($value, $instance->{$property});
            self::assertEquals($value, $instance->{$snakeCase});
        }
    }
}
