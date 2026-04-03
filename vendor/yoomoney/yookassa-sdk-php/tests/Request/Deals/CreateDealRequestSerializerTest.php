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

namespace Tests\YooKassa\Request\Deals;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\Deal\DealType;
use YooKassa\Model\Deal\FeeMoment;
use YooKassa\Request\Deals\CreateDealRequest;
use YooKassa\Request\Deals\CreateDealRequestSerializer;

class CreateDealRequestSerializerTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     *
     * @param $options
     */
    public function testSerialize($options)
    {
        $serializer = new CreateDealRequestSerializer();
        $instance   = CreateDealRequest::builder()->build($options);
        $data       = $serializer->serialize($instance);

        $expected = array(
            'type' => $options['type'],
            'fee_moment' => $options['fee_moment'],
        );

        if (!empty($options['metadata'])) {
            $expected['metadata'] = array();
            foreach ($options['metadata'] as $key => $value) {
                $expected['metadata'][$key] = $value;
            }
        }

        if (!empty($options['description'])) {
            $expected['description'] = $options['description'];
        }

        self::assertEquals($expected, $data);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function validDataProvider()
    {
        $result = array(
            array(
                array(
                    'type' => Random::value(DealType::getValidValues()),
                    'fee_moment' => Random::value(FeeMoment::getValidValues()),
                    'description' => null,
                    'metadata' => null,
                ),
            ),
            array(
                array(
                    'type' => Random::value(DealType::getValidValues()),
                    'fee_moment' => Random::value(FeeMoment::getValidValues()),
                    'description' => '',
                    'metadata' => array(),
                ),
            ),
        );
        for ($i = 0; $i < 10; $i++) {
            $request = array(
                'type' => Random::value(DealType::getValidValues()),
                'fee_moment' => Random::value(FeeMoment::getValidValues()),
                'description' => Random::str(1, 128),
                'metadata' => array(Random::str(1, 30) => Random::str(1, 128)),
            );
            $result[] = array($request);
        }

        return $result;
    }
}
