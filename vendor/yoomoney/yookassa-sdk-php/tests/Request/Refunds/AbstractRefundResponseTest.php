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

namespace Tests\YooKassa\Request\Refunds;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\AmountInterface;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Deal\RefundDealInfo;
use YooKassa\Model\Deal\SettlementPayoutPaymentType;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\ReceiptRegistrationStatus;
use YooKassa\Model\RefundStatus;
use YooKassa\Model\Source;
use YooKassa\Request\Refunds\AbstractRefundResponse;

abstract class AbstractRefundResponseTest extends TestCase
{
    /**
     * @param array $options
     * @return AbstractRefundResponse
     */
    abstract protected function getTestInstance($options);

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetId($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['id'], $instance->getId());
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetPaymentId($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['payment_id'], $instance->getPaymentId());
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetStatus($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['status'], $instance->getStatus());
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetCreatedAt($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertTrue($instance->getCreatedAt() instanceof \DateTime);
        self::assertEquals($options['created_at'], $instance->getCreatedAt()->format(YOOKASSA_DATE));
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetAmount($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertTrue($instance->getAmount() instanceof AmountInterface);
        self::assertEquals($options['amount']['value'], $instance->getAmount()->getValue());
        self::assertEquals($options['amount']['currency'], $instance->getAmount()->getCurrency());
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetReceiptRegistered($options)
    {
        $instance = $this->getTestInstance($options);
        if (empty($options['receipt_registration'])) {
            self::assertNull($instance->getReceiptRegistration());
        } else {
            self::assertEquals($options['receipt_registration'], $instance->getReceiptRegistration());
        }
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetDescription($options)
    {
        $instance = $this->getTestInstance($options);
        if (empty($options['description'])) {
            self::assertNull($instance->getDescription());
        } else {
            self::assertEquals($options['description'], $instance->getDescription());
        }
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetSources($options)
    {
        $instance = $this->getTestInstance($options);
        if (empty($options['sources'])) {
            self::assertEmpty($instance->getSources());
        } else {
            foreach ($instance->getSources() as $sources) {
                self::assertInstanceOf('\YooKassa\Model\Source', $sources);
            }
        }
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetDeal($options)
    {
        $instance = $this->getTestInstance($options);
        self::assertTrue($instance->getDeal() instanceof RefundDealInfo);

        self::assertEquals($options['deal']['id'], $instance->getDeal()->getId());
        $settlements = $instance->getDeal()->getRefundSettlements();
        if (!empty($settlements)) {
            self::assertEquals($options['deal']['refund_settlements'][0], $settlements[0]->toArray());
        }
    }

    public function validDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $payment = array(
                'id' => Random::str(36),
                'payment_id' => Random::str(36),
                'status' => Random::value(RefundStatus::getValidValues()),
                'created_at' => date(YOOKASSA_DATE, mt_rand(1, time())),
                'authorized_at' => date(YOOKASSA_DATE, mt_rand(1, time())),
                'amount' => array(
                    'value' => mt_rand(100, 100000),
                    'currency' => Random::value(CurrencyCode::getValidValues()),
                ),
                'receipt_registration' => Random::value(ReceiptRegistrationStatus::getValidValues()),
                'description' => uniqid(),
                'sources' => array(
                    new Source(array(
                        'account_id' => Random::str(36),
                        'amount' => new MonetaryAmount(Random::int(1, 1000), 'RUB'),
                        'platform_fee_amount' => new MonetaryAmount(Random::int(1, 1000), 'RUB'),
                    )),
                ),
                'deal' => array(
                    'id' => 'dl-285e5ee7-0022-5000-8000-01516a44b147',
                    'refund_settlements' => array(
                        array(
                            'type' => SettlementPayoutPaymentType::PAYOUT,
                            'amount' => array(
                                'value' => 123.00,
                                'currency' => 'RUB',
                            ),
                        )
                    ),
                ),
            );
            $result[] = array($payment);
        }
        return $result;
    }
}
