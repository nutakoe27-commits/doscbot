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

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\Webhook\Webhook;

class WebhookTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     *
     * @param $data
     */
    public function testWebhookInstantiate($data)
    {
        $webhook = new Webhook();

        $webhook->setId($data['id']);
        $webhook->setUrl($data['url']);
        $webhook->setEvent($data['event']);

        self::assertEquals($webhook->getId(), $data['id']);
        self::assertEquals($webhook->getUrl(), $data['url']);
        self::assertEquals($webhook->getEvent(), $data['event']);
    }

    /**
     * @dataProvider validDataProvider
     *
     * @param $data
     */
    public function testWebhookConstructorInstantiate($data)
    {
        $webhook = new Webhook($data);

        self::assertEquals($webhook->getId(), $data['id']);
        self::assertEquals($webhook->getUrl(), $data['url']);
        self::assertEquals($webhook->getEvent(), $data['event']);
    }

    /**
     * @return array
     */
    public function validDataProvider()
    {
        return array(
            array(
                array(
                    "id"    => Random::str(20),
                    "event" => NotificationEventType::REFUND_SUCCEEDED,
                    "url"   => Random::str(20),
                ),
            ),
            array(
                array(
                    "id"    => Random::str(20),
                    "event" => NotificationEventType::PAYMENT_SUCCEEDED,
                    "url"   => Random::str(20),
                ),
            ),
            array(
                array(
                    "id"    => Random::str(20),
                    "event" => NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE,
                    "url"   => Random::str(20),
                ),
            ),
        );
    }
}
