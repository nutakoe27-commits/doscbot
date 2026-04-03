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

namespace Tests\YooKassa\Model\Deal;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\Deal\RefundDealData;
use YooKassa\Model\Deal\SettlementPayoutPayment;
use YooKassa\Model\Deal\SettlementPayoutPaymentType;

class RefundDealDataTest extends TestCase
{
    /**
     * @dataProvider fromArrayDataProvider
     * @param array $source
     * @param RefundDealData $expected
     */
    public function testFromArray($source, $expected)
    {
        $deal = new RefundDealData($source);
        $dealArray = $expected->toArray();

        if (!empty($deal)) {
            foreach ($deal->toArray() as $property => $value) {
                self::assertEquals($value, $dealArray[$property]);
            }
        }
    }

    public function validDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $payment = array(
                'refund_settlements' => $this->generateRefundSettlements(),
            );
            $result[] = array($payment);
        }
        return $result;
    }

    public function invalidDataProvider()
    {
        $result = array(
            array(
                array(
                    'refund_settlements' => null,
                )
            ),
            array(
                array(
                    'refund_settlements' => '',
                ),
            ),
        );
        $invalidData = array(
            array(null),
            array(''),
            array(new \stdClass()),
            array('invalid_value'),
            array(0),
            array(3234),
            array(true),
            array(false),
            array(0.43),
        );
        for ($i = 0; $i < 9; $i++) {
            $payment = array(
                'refund_settlements' => Random::value($invalidData),
            );
            $result[] = array($payment);
        }
        return $result;
    }

    public function fromArrayDataProvider()
    {
        $deal = new RefundDealData();
        $settlements = array();
        $settlements[] = new SettlementPayoutPayment(array(
            'type' => SettlementPayoutPaymentType::PAYOUT,
            'amount' => array(
                'value' => 123.00,
                'currency' => 'RUB',
            ),
        ));
        $deal->setRefundSettlements($settlements);

        return array(
            array(
                array(
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
                $deal
            ),
        );
    }

    private function generateRefundSettlements()
    {
        $return = array();
        $count = Random::int(1, 10);

        for ($i = 0; $i < $count; $i++) {
            $return[] = $this->generateRefundSettlement();
        }

        return $return;
    }

    private function generateRefundSettlement()
    {
        return array(
            'type' => Random::value(SettlementPayoutPaymentType::getValidValues()),
            'amount' => array(
                'value' => round(Random::float(1.00, 100.00), 2),
                'currency' => 'RUB',
            ),
        );
    }
}
