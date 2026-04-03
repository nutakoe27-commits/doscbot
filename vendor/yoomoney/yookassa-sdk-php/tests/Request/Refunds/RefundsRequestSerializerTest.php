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
use YooKassa\Model\RefundStatus;
use YooKassa\Request\Refunds\RefundsRequest;
use YooKassa\Request\Refunds\RefundsRequestSerializer;

class RefundsRequestSerializerTest extends TestCase
{
    private $fieldMap = array(
        'paymentId'      => 'payment_id',
        'createdAtGte'   => 'created_at.gte',
        'createdAtGt'    => 'created_at.gt',
        'createdAtLte'   => 'created_at.lte',
        'createdAtLt'    => 'created_at.lt',
        'status'         => 'status',
        'cursor'         => 'cursor',
        'limit'          => 'limit',
    );

    /**
     * @dataProvider validDataProvider
     * @param $options
     */
    public function testSerialize($options)
    {
        $serializer = new RefundsRequestSerializer();
        $data = $serializer->serialize(RefundsRequest::builder()->build($options));

        $expected = array();
        foreach ($this->fieldMap as $field => $mapped) {
            if (isset($options[$field])) {
                $value = $options[$field];
                if (!empty($value)) {
                    $expected[$mapped] = $value instanceof \DateTime ? $value->format(YOOKASSA_DATE) : $value;
                }
            }
        }
        self::assertEquals($expected, $data);
    }

    public function validDataProvider()
    {
        $result = array(
            array(
                array(
                    'accountId' => uniqid(),
                ),
            ),
            array(
                array(
                    'paymentId' => '',
                    'createdAtGte' => '',
                    'createdAtGt' => '',
                    'createdAtLte' => '',
                    'createdAtLt' => '',
                    'status' => '',
                    'cursor' => '',
                    'limit' => '',
                ),
            ),
        );
        $statuses = RefundStatus::getValidValues();
        for ($i = 0; $i < 10; $i++) {
            $request = array(
                'paymentId' => $this->randomString(36),
                'createdAtGte' => ($i == 0 ? null : ($i == 1 ? '' : date(YOOKASSA_DATE, mt_rand(1, time())))),
                'createdAtGt' => ($i == 0 ? null : ($i == 1 ? '' : date(YOOKASSA_DATE, mt_rand(1, time())))),
                'createdAtLte' => ($i == 0 ? null : ($i == 1 ? '' : date(YOOKASSA_DATE, mt_rand(1, time())))),
                'createdAtLt' => ($i == 0 ? null : ($i == 1 ? '' : date(YOOKASSA_DATE, mt_rand(1, time())))),
                'status' => $statuses[mt_rand(0, count($statuses) - 1)],
                'cursor' => uniqid(),
                'limit' => mt_rand(1, RefundsRequest::MAX_LIMIT_VALUE),
            );
            $result[] = array($request);
        }
        return $result;
    }

    private function randomString($length, $any = true)
    {
        static $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-+_.';

        $result = '';
        for ($i = 0; $i < $length; $i++) {
            if ($any) {
                $char = chr(mt_rand(32, 126));
            } else {
                $rnd = mt_rand(0, strlen($chars) - 1);
                $char = substr($chars, $rnd, 1);
            }
            $result .= $char;
        }
        return $result;
    }
}
