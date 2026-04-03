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

namespace Tests\YooKassa\Model\Notification;

use YooKassa\Helpers\Random;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Notification\NotificationRefundSucceeded;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\NotificationType;
use YooKassa\Model\ReceiptRegistrationStatus;
use YooKassa\Model\RefundInterface;
use YooKassa\Model\RefundStatus;

class NotificationRefundSucceededTest extends AbstractNotificationTest
{
    /**
     * @param array $source
     *
     * @return NotificationRefundSucceeded
     */
    protected function getTestInstance(array $source)
    {
        return new NotificationRefundSucceeded($source);
    }

    /**
     * @return string
     */
    protected function getExpectedType()
    {
        return NotificationType::NOTIFICATION;
    }

    /**
     * @return string
     */
    protected function getExpectedEvent()
    {
        return NotificationEventType::REFUND_SUCCEEDED;
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param array $value
     */
    public function testGetObject(array $value)
    {
        $instance = $this->getTestInstance($value);
        self::assertTrue($instance->getObject() instanceof RefundInterface);
        self::assertEquals($value['object']['id'], $instance->getObject()->getId());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validDataProvider()
    {
        $result               = array();
        $statuses             = RefundStatus::getValidValues();
        $receiptRegistrations = ReceiptRegistrationStatus::getValidValues();

        for ($i = 0; $i < 10; $i++) {
            $refund   = array(
                'id'                   => Random::str(36),
                'payment_id'           => Random::str(36),
                'status'               => Random::value($statuses),
                'amount'               => array(
                    'value'    => Random::float(0.01, 1000000.0),
                    'currency' => Random::value(CurrencyCode::getValidValues()),
                ),
                'created_at'           => date(YOOKASSA_DATE, Random::int(1, time())),
                'receipt_registration' => Random::value($receiptRegistrations),
                'description'          => Random::str(1, 128),
            );
            $result[] = array(
                array(
                    'type'   => $this->getExpectedType(),
                    'event'  => $this->getExpectedEvent(),
                    'object' => $refund,
                ),
            );
        }

        return $result;
    }
}
