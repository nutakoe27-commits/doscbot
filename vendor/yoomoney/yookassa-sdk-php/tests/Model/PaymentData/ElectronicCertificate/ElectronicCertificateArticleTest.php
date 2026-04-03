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

namespace Tests\YooKassa\Model\PaymentData\ElectronicCertificate;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Metadata;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle;

class ElectronicCertificateArticleTest extends TestCase
{
    /**
     * @return ElectronicCertificateArticle
     */
    protected function getTestInstance()
    {
        return new ElectronicCertificateArticle();
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
     * @dataProvider validArticleNameDataProvider
     * @param string $value
     */
    public function testGetSetArticleName($value)
    {
        $this->getAndSetTest($value, 'articleName', 'article_name');
    }

    /**
     * @dataProvider invalidArticleNameDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidArticleName($value)
    {
        $this->getTestInstance()->setArticleName($value);
    }

    /**
     * @dataProvider invalidArticleNameDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticleName($value)
    {
        $this->getTestInstance()->articleName = $value;
    }

    /**
     * @dataProvider invalidArticleNameDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidArticleNameSnakeCase($value)
    {
        $this->getTestInstance()->article_name = $value;
    }

    /**
     * @dataProvider validQuantityDataProvider
     * @param int $value
     */
    public function testGetSetQuantity($value)
    {
        $this->getAndSetTest($value, 'quantity');
    }

    /**
     * @dataProvider invalidQuantityDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidQuantity($value)
    {
        $this->getTestInstance()->setQuantity($value);
    }

    /**
     * @dataProvider invalidQuantityDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidQuantity($value)
    {
        $this->getTestInstance()->quantity = $value;
    }

    /**
     * @dataProvider validPriceDataProvider
     * @param mixed $value
     */
    public function testGetSetPrice($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getPrice());
        self::assertNull($instance->price);

        $instance->setPrice($value);
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getPrice()->getValue());
            self::assertEquals($value['currency'], $instance->price->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getPrice()->getValue());
            self::assertEquals($value->getCurrency(), $instance->price->getCurrency());
        }

        $instance = $this->getTestInstance();
        $instance->price = $value;
        if (is_array($value)) {
            self::assertEquals($value['value'], $instance->getPrice()->getValue());
            self::assertEquals($value['currency'], $instance->price->getCurrency());
        } elseif ($value instanceof MonetaryAmount) {
            self::assertEquals($value->getValue(), $instance->getPrice()->getValue());
            self::assertEquals($value->getCurrency(), $instance->price->getCurrency());
        }
    }

    /**
     * @dataProvider invalidPriceDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidPrice($value)
    {
        $this->getTestInstance()->setPrice($value);
    }

    /**
     * @dataProvider invalidPriceDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetterInvalidPrice($value)
    {
        $this->getTestInstance()->price = $value;
    }

    /**
     * @dataProvider validMetadataDataProvider
     * @param mixed $value
     */
    public function testGetSetMetadata($value)
    {
        $instance = $this->getTestInstance();

        self::assertNull($instance->getMetadata());
        self::assertNull($instance->metadata);

        $instance->setMetadata($value);
        if (is_array($value) && !empty($value)) {
            self::assertEquals($value, $instance->getMetadata()->toArray());
            self::assertEquals($value, $instance->metadata->toArray());
        } elseif ($value instanceof Metadata) {
            self::assertEquals($value->toArray(), $instance->getMetadata()->toArray());
            self::assertEquals($value->toArray(), $instance->metadata->toArray());
        } else {
            self::assertNull($instance->getMetadata());
        }

        $instance = $this->getTestInstance();
        $instance->metadata = $value;
        if (is_array($value) && !empty($value)) {
            self::assertEquals($value, $instance->getMetadata()->toArray());
            self::assertEquals($value, $instance->metadata->toArray());
        } elseif ($value instanceof Metadata) {
            self::assertEquals($value->toArray(), $instance->getMetadata()->toArray());
            self::assertEquals($value->toArray(), $instance->metadata->toArray());
        } else {
            self::assertNull($instance->getMetadata());
        }
    }

    /**
     * @dataProvider invalidMetadataDataProvider
     * @expectedException InvalidArgumentException
     * @param mixed $value
     */
    public function testSetInvalidMetadata($value)
    {
        $this->getTestInstance()->setMetadata($value);
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
            array('5'),
            array(array()),
            array(new \stdClass()),
            array(Random::str(1, '0123456789')),
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
    public function validArticleNameDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $result[] = array(Random::str(1, 128));
        }
        return $result;
    }

    /**
     * @return array
     */
    public function invalidArticleNameDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(array()),
            array(new \stdClass()),
            array(Random::str(129)),
        );
    }

    /**
     * @return array
     */
    public function validQuantityDataProvider()
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
    public function invalidQuantityDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(-1),
            array('5'),
            array(array()),
            array(new \stdClass()),
            array(Random::str(1, '0123456789')),
        );
    }

    /**
     * @return array
     */
    public function validPriceDataProvider()
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
    public function invalidPriceDataProvider()
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
    public function validMetadataDataProvider()
    {
        $result = array(
            array(null),
            array(array()),
            array(array('key' => 'value')),
            array(new Metadata()),
            array(new Metadata(array('test' => 'test'))),
        );
        return $result;
    }

    /**
     * @return array
     */
    public function invalidMetadataDataProvider()
    {
        return array(
            array(0),
            array(1),
            array('string'),
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
