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

namespace Tests\YooKassa\Request\Receipts;

use YooKassa\Common\Exceptions\InvalidPropertyValueException;
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Helpers\Random;
use YooKassa\Model\Airline;
use YooKassa\Model\SettlementInterface;
use YooKassa\Request\Receipts\PaymentReceiptResponse;
use YooKassa\Request\Receipts\ReceiptResponseItemInterface;

class PaymentReceiptResponseTest extends AbstractReceiptResponseTest
{
    protected $type = 'payment';

    protected function getTestInstance($options)
    {
        return new PaymentReceiptResponse($options);
    }

    protected function addSpecificProperties($options, $i)
    {
        $array = array(
            Random::str(30),
            new \stdClass(),
            array(),
            new \stdClass(),
            new \Exception(),
            new Airline(),
            Random::str(40),
            array(new Airline()),
        );
        $options['payment_id'] = !$this->valid
            ? (Random::value($array))
            : Random::value(array( null, '', Random::str(PaymentReceiptResponse::LENGTH_PAYMENT_ID)));
        return $options;
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testSpecificProperties($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['payment_id'], $instance->getPaymentId());
    }

    /**
     * @dataProvider invalidDataProvider
     * @param array $options
     */
    public function testInvalidSpecificProperties($options)
    {
        $this->valid = false;
        $catch = false;
        try {
            $instance = $this->getTestInstance($options);
            $instance->setPaymentId($options['payment_id']);
        } catch (InvalidPropertyValueException $e) {
            $catch = true;
        } catch (InvalidPropertyValueTypeException $e) {
            $catch = true;
        }
        self::assertTrue($catch);

        $catch = false;
        try {
            $paymentId = $options['payment_id'];
            $options['payment_id'] = Random::str(PaymentReceiptResponse::LENGTH_PAYMENT_ID);
            $instance = $this->getTestInstance($options);
            $instance->setPaymentId($paymentId);
        } catch (InvalidPropertyValueException $e) {
            $catch = true;
        } catch (InvalidPropertyValueTypeException $e) {
            $catch = true;
        }
        self::assertTrue($catch);
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetsValidData($options)
    {
        $instance = $this->getTestInstance($options);

        if (!is_null($options['fiscal_document_number'])) {
            self::assertNotNull($instance->getFiscalDocumentNumber());
        }
        self::assertEquals($options['fiscal_document_number'], $instance->getFiscalDocumentNumber());

        self::assertNotNull($instance->getFiscalStorageNumber());
        self::assertEquals($options['fiscal_storage_number'], $instance->getFiscalStorageNumber());

        self::assertNotNull($instance->getFiscalAttribute());
        self::assertEquals($options['fiscal_attribute'], $instance->getFiscalAttribute());

        self::assertNotNull($instance->getFiscalProviderId());
        self::assertEquals($options['fiscal_provider_id'], $instance->getFiscalProviderId());

        self::assertNotNull($instance->getRegisteredAt());
        self::assertEquals($options['registered_at'], $instance->getRegisteredAt()->format(YOOKASSA_DATE));

        self::assertNotNull($instance->getItems());
        foreach ($instance->getItems() as $item) {
            self::assertTrue($item instanceof ReceiptResponseItemInterface);
        }

        self::assertNotNull($instance->getSettlements());
        foreach ($instance->getSettlements() as $settlements) {
            self::assertTrue($settlements instanceof SettlementInterface);
        }

        self::assertNotNull($instance->getOnBehalfOf());
        self::assertEquals($options['on_behalf_of'], $instance->getOnBehalfOf());

        self::assertTrue($instance->notEmpty());
    }

    public function testSetFiscalDocumentNumber()
    {
        $instance = $this->getTestInstance(null);
        $instance->setFiscalDocumentNumber(null);
    }

    public function testSetTaxSystemCode()
    {
        $instance = $this->getTestInstance(null);
        $instance->setTaxSystemCode(null);
    }

    /**
     * @dataProvider invalidAllDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidIdData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setId($options);
    }

    /**
     * @dataProvider invalidAllDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidTypeData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setType($options);
    }

    /**
     * @dataProvider invalidBoolDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidObjectIdData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setObjectId($options);
    }

    /**
     * @dataProvider invalidAllDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidStatusIdData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setStatus($options);
    }

    /**
     * @dataProvider invalidBoolDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidFiscalDocumentNumberData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setFiscalDocumentNumber($options);
    }

    /**
     * @dataProvider invalidItemsSettlementsDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidItemsData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setItems($options);
    }

    /**
     * @dataProvider invalidItemsSettlementsDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidSettlementsData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setSettlements($options);
    }

    /**
     * @dataProvider invalidAllDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidTaxSystemCodeData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setTaxSystemCode($options);
    }

    /**
     * @dataProvider invalidBoolNullDataProvider
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidOnBehalfOfData($options)
    {
        $instance = $this->getTestInstance(null);
        $instance->setOnBehalfOf($options);
    }

    /**
     * @dataProvider invalidFromArray
     * @param array $options
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidFromArray($options)
    {
        $this->getTestInstance($options);
    }
}
