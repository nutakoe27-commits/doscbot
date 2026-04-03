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
use YooKassa\Model\Deal\DealStatus;
use YooKassa\Model\Deal\DealType;
use YooKassa\Model\Deal\FeeMoment;
use YooKassa\Model\Notification\NotificationDealClosed;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\NotificationType;
use YooKassa\Model\DealInterface;

class NotificationDealClosedTest extends AbstractNotificationTest
{
    /**
     * @param array $source
     * @return NotificationDealClosed
     * @throws \Exception
     */
    protected function getTestInstance(array $source)
    {
        return new NotificationDealClosed($source);
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
        return NotificationEventType::DEAL_CLOSED;
    }

    /**
     * @dataProvider validDataProvider
     * @param array $value
     */
    public function testGetObject(array $value)
    {
        $instance = $this->getTestInstance($value);
        self::assertTrue($instance->getObject() instanceof DealInterface);
        self::assertEquals($value['object']['id'], $instance->getObject()->getId());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validDataProvider()
    {
        $result = array();
        $statuses = DealStatus::getValidValues();
        $types = DealType::getValidValues();

        for ($i = 0; $i < 10; $i++) {
            $deal = array(
                'id' => Random::str(36),
                'type' => Random::value($types),
                'status' => Random::value($statuses),
                'description' => Random::str(128),
                'balance' => array(
                    'value' => Random::float(0.01, 1000000.0),
                    'currency' => Random::value(CurrencyCode::getEnabledValues()),
                ),
                'payout_balance' => array(
                    'value' => Random::float(0.01, 1000000.0),
                    'currency' => Random::value(CurrencyCode::getValidValues()),
                ),
                'created_at' => date(YOOKASSA_DATE, Random::int(1, time())),
                'expires_at' => date(YOOKASSA_DATE, Random::int(1, time())),
                'fee_moment' => Random::value(FeeMoment::getEnabledValues()),
                'test' => (bool)($i % 2),
                'metadata' => array(
                    'value' => Random::float(0.01, 1000000.0),
                    'currency' => Random::str(1, 256),
                ),
            );
            $result[] = array(
                array(
                    'type' => $this->getExpectedType(),
                    'event' => $this->getExpectedEvent(),
                    'object' => $deal,
                ),
            );
        }

        $trueFalse = Random::bool();
        $result[] = array(
            array(
                'type' => $this->getExpectedType(),
                'event' => $this->getExpectedEvent(),
                    'object' => array(
                    'id' => Random::str(36),
                    'type' => Random::value($types),
                    'status' => Random::value($statuses),
                    'description' => Random::str(128),
                    'balance' => array(
                        'value' => Random::float(0.01, 1000000.0),
                        'currency' => Random::value(CurrencyCode::getValidValues()),
                    ),
                    'payout_balance' => array(
                        'value' => Random::float(0.01, 1000000.0),
                        'currency' => Random::value(CurrencyCode::getValidValues()),
                    ),
                    'created_at' => date(YOOKASSA_DATE, Random::int(1, time())),
                    'expires_at' => date(YOOKASSA_DATE, Random::int(1, time())),
                    'fee_moment' => Random::value(FeeMoment::getEnabledValues()),
                    'test' => $trueFalse,
                    'metadata' => array(),
                ),
            ),
        );

        return $result;
    }
}
