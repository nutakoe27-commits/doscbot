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

namespace Model\Confirmation;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Model\Confirmation\AbstractConfirmation;
use YooKassa\Model\Confirmation\ConfirmationFactory;
use YooKassa\Model\ConfirmationType;

class ConfirmationFactoryTest extends TestCase
{
    /**
     * @return ConfirmationFactory
     */
    protected function getTestInstance()
    {
        return new ConfirmationFactory();
    }

    /**
     * @dataProvider validTypeDataProvider
     * @param string $type
     */
    public function testFactory($type)
    {
        $instance = $this->getTestInstance();
        $confirmation = $instance->factory($type);
        self::assertNotNull($confirmation);
        self::assertTrue($confirmation instanceof AbstractConfirmation);
        self::assertEquals($type, $confirmation->getType());
    }

    /**
     * @dataProvider invalidTypeDataProvider
     * @expectedException \InvalidArgumentException
     * @param $type
     */
    public function testInvalidFactory($type)
    {
        $instance = $this->getTestInstance();
        $instance->factory($type);
    }

    /**
     * @dataProvider validArrayDataProvider
     * @param array $options
     */
    public function testFactoryFromArray($options)
    {
        $instance = $this->getTestInstance();
        $confirmation = $instance->factoryFromArray($options);
        self::assertNotNull($confirmation);
        self::assertTrue($confirmation instanceof AbstractConfirmation);

        foreach ($options as $property => $value) {
            self::assertEquals($confirmation->{$property}, $value);
        }

        $type = $options['type'];
        unset($options['type']);
        $confirmation = $instance->factoryFromArray($options, $type);
        self::assertNotNull($confirmation);
        self::assertTrue($confirmation instanceof AbstractConfirmation);

        self::assertEquals($type, $confirmation->getType());
        foreach ($options as $property => $value) {
            self::assertEquals($confirmation->{$property}, $value);
        }
    }

    /**
     * @dataProvider invalidDataArrayDataProvider
     * @expectedException \InvalidArgumentException
     * @param $options
     */
    public function testInvalidFactoryFromArray($options)
    {
        $instance = $this->getTestInstance();
        $instance->factoryFromArray($options);
    }

    public function validTypeDataProvider()
    {
        $result = array();
        foreach (ConfirmationType::getValidValues() as $value) {
            $result[] = array($value);
        }
        return $result;
    }

    public function invalidTypeDataProvider()
    {
        return array(
            array(''),
            array(null),
            array(0),
            array(1),
            array(-1),
            array('5'),
            array(array()),
            array(new \stdClass()),
            array(Random::str(10)),
        );
    }

    public function validArrayDataProvider()
    {
        $result = array(
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'enforce' => false,
                    'returnUrl' => Random::str(10),
                    'confirmationUrl' => Random::str(10),
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'enforce' => true,
                    'returnUrl' => Random::str(10),
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'returnUrl' => Random::str(10),
                    'confirmationUrl' => Random::str(10),
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'confirmationUrl' => Random::str(10),
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'returnUrl' => Random::str(10),
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                    'enforce' => true,
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::REDIRECT,
                ),
            ),
            array(
                array(
                    'type' => ConfirmationType::QR,
                    'confirmation_data' => Random::str(30),
                ),
            ),
        );
        foreach (ConfirmationType::getValidValues() as $value) {
            $result[] = array(array('type' => $value));
        }
        return $result;
    }

    public function invalidDataArrayDataProvider()
    {
        return array(
            array(array()),
            array(array('type' => 'test')),
        );
    }
}
