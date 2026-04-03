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

namespace Tests\YooKassa\Request\Webhook;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\Webhook\Webhook;
use YooKassa\Request\Webhook\WebhookListResponse;

class WebhookListResponseTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     * @param array $options
     * @throws \Exception
     */
    public function testGetType($options)
    {
        $instance = new WebhookListResponse($options);

        self::assertEquals($options['type'], $instance->getType());
    }

    /**
     * @dataProvider validDataProvider
     * @param array $options
     * @throws \Exception
     */
    public function testGetItems($options)
    {
        $instance = new WebhookListResponse($options);

        self::assertEquals(count($options['items']), count($instance->getItems()));

        foreach ($instance->getItems() as $index => $item) {
            self::assertTrue($item instanceof Webhook);
            self::assertArrayHasKey($index, $options['items']);
            self::assertEquals($options['items'][$index]['id'], $item->getId());
            self::assertEquals($options['items'][$index]['event'], $item->getEvent());
            self::assertEquals($options['items'][$index]['url'], $item->getUrl());
        }
    }

    public function validDataProvider()
    {
        return array(
            array(
                array(
                    'type' => 'list',
                    'items' => $this->generateWebhooks(),
                ),
            ),
        );
    }

    private function generateWebhooks()
    {
        $return = array();
        $count = Random::int(1, 10);

        for ($i = 0; $i < $count; $i++) {
            $return[] = $this->generateWebhook();
        }

        return $return;
    }

    private function generateWebhook()
    {
        return array(
            'id' => Random::str(39),
            'event' => Random::value(NotificationEventType::getValidValues()),
            'url' => Random::str(20)
        );
    }
}
