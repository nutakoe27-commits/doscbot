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

namespace Tests\YooKassa\Request\Payouts;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\Payout\SbpParticipantBank;
use YooKassa\Request\Payouts\SbpBanksResponse;

class SbpBanksResponseTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     * @param array $options
     */
    public function testGetItems($options)
    {
        $instance = new SbpBanksResponse($options);

        self::assertEquals($options['type'], $instance->getType());
        self::assertEquals(count($options['items']), count($instance->getItems()));

        foreach ($instance->getItems() as $index => $item) {
            self::assertTrue($item instanceof SbpParticipantBank);
            self::assertArrayHasKey($index, $options['items']);
            self::assertEquals($options['items'][$index]['bank_id'], $item->getBankId());
            self::assertEquals($options['items'][$index]['name'], $item->getName());
            self::assertEquals($options['items'][$index]['bic'], $item->getBic());
        }
    }

    public function validDataProvider()
    {
        return array(
            array(
                array(
                    'type' => 'list',
                    'items' => array(),
                ),
            ),
            array(
                array(
                    'type' => 'list',
                    'items' => array(
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'type' => 'list',
                    'items' => array(
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'type' => 'list',
                    'items' => array(
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                        array(
                            'bank_id' => Random::str(36),
                            'name' => Random::str(36),
                            'bic' => Random::str(36),
                        ),
                    ),
                ),
            ),
        );
    }

}
