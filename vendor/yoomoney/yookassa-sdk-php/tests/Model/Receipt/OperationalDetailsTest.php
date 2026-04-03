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

namespace Tests\YooKassa\Model\Receipt;

use YooKassa\Helpers\Random;
use YooKassa\Model\Receipt\OperationalDetails;
use PHPUnit\Framework\TestCase;

class OperationalDetailsTest extends TestCase
{
    protected static function getInstance($options = null)
    {
        return new OperationalDetails($options);
    }

    /**
     * @dataProvider validArrayDataProvider
     *
     * @param $options
     */
    public function testConstructor($options)
    {
        $instance = self::getInstance();

        self::assertNull($instance->getOperationId());
        self::assertNull($instance->getCreatedAt());
        self::assertNull($instance->getValue());

        $instance = self::getInstance($options);

        self::assertEquals($options['operation_id'], $instance->getOperationId());
        self::assertEquals($options['created_at'], $instance->getCreatedAt()->format(YOOKASSA_DATE));
        self::assertEquals($options['value'], $instance->getValue());
    }

    /**
     * @dataProvider validArrayDataProvider
     *
     * @param array $options
     */
    public function testGetSetValue($options)
    {
        $expected = $options['value'];

        $instance = self::getInstance();
        self::assertNull($instance->getValue());
        self::assertNull($instance->value);
        $instance->setValue($expected);
        self::assertEquals($expected, $instance->getValue());
        self::assertEquals($expected, $instance->value);

        $instance = self::getInstance();
        $instance->value = $expected;
        self::assertEquals($expected, $instance->getValue());
        self::assertEquals($expected, $instance->value);
    }

    /**
     * @dataProvider invalidValueDataProvider
     * @param mixed $value
     * @param string $exceptionClassDocumentNumber
     */
    public function testSetInvalidValue($value, $exceptionClassDocumentNumber)
    {
        $instance = self::getInstance();
        try {
            $instance->setValue($value);
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassDocumentNumber, $e);
        }
    }

    /**
     * @dataProvider invalidValueDataProvider
     * @param mixed $value
     * @param string $exceptionClassDocumentNumber
     */
    public function testSetterInvalidValue($value, $exceptionClassDocumentNumber)
    {
        $instance = self::getInstance();
        try {
            $instance->value = $value;
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassDocumentNumber, $e);
        }
    }

    /**
     * @dataProvider validArrayDataProvider
     * @param array $options
     */
    public function testGetSetOperationId($options)
    {
        $instance = self::getInstance();

        self::assertNull($instance->getOperationId());
        self::assertNull($instance->operation_id);
        $instance->setOperationId($options['operation_id']);
        self::assertEquals($options['operation_id'], $instance->getOperationId());
        self::assertEquals($options['operation_id'], $instance->operation_id);
    }

    /**
     * @dataProvider invalidOperationIdDataProvider
     * @param string $operation_id
     * @param string $exceptionClassOperationId
     */
    public function testSetInvalidOperationId($operation_id, $exceptionClassOperationId)
    {
        $instance = self::getInstance();
        try {
            $instance->setOperationId($operation_id);
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassOperationId, $e);
        }
    }

    /**
     * @dataProvider invalidOperationIdDataProvider
     * @param string $operation_id
     * @param string $exceptionClassOperationId
     */
    public function testSetterInvalidOperationId($operation_id, $exceptionClassOperationId)
    {
        $instance = self::getInstance();
        try {
            $instance->operation_id = $operation_id;
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassOperationId, $e);
        }
    }

    /**
     * @dataProvider validArrayDataProvider
     * @param array $options
     */
    public function testGetSetCreatedAt($options)
    {
        $instance = self::getInstance();

        self::assertNull($instance->getCreatedAt());
        self::assertNull($instance->created_at);
        $instance->setCreatedAt($options['created_at']);
        self::assertEquals($options['created_at'], $instance->getCreatedAt()->format(YOOKASSA_DATE));
        self::assertEquals($options['created_at'], $instance->created_at->format(YOOKASSA_DATE));
    }

    /**
     * @dataProvider invalidCreatedAtDataProvider
     * @param string $created_at
     * @param string $exceptionClassCreatedAt
     */
    public function testSetInvalidCreatedAt($created_at, $exceptionClassCreatedAt)
    {
        $instance = self::getInstance();
        try {
            $instance->setCreatedAt($created_at);
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassCreatedAt, $e);
        }
    }

    /**
     * @dataProvider invalidCreatedAtDataProvider
     * @param string $created_at
     * @param string $exceptionClassCreatedAt
     */
    public function testSetterInvalidCreatedAt($created_at, $exceptionClassCreatedAt)
    {
        $instance = self::getInstance();
        try {
            $instance->created_at = $created_at;
        } catch (\Exception $e) {
            self::assertInstanceOf($exceptionClassCreatedAt, $e);
        }
    }

    public function validArrayDataProvider()
    {
        $result = array();
        foreach (range(1, 10) as $i) {
            $result[$i][] = array(
                'operation_id' => Random::str(1, OperationalDetails::OPERATION_ID_MAX_LENGTH),
                'created_at' => date(YOOKASSA_DATE, Random::int(10000000, 29999999)),
                'value' => Random::str(1, OperationalDetails::VALUE_MAX_LENGTH),
            );
        }
        return $result;
    }

    public function invalidValueDataProvider()
    {
        $exceptionValueNamespace = 'YooKassa\\Common\\Exceptions\\';
        return array(
            array(null,                 $exceptionValueNamespace . 'EmptyPropertyValueException'),
            array('',                   $exceptionValueNamespace . 'EmptyPropertyValueException'),
            array(array(),              $exceptionValueNamespace . 'InvalidPropertyValueTypeException'),
            array(fopen(__FILE__, 'r'), $exceptionValueNamespace . 'InvalidPropertyValueTypeException'),
            array(Random::str(OperationalDetails::VALUE_MAX_LENGTH + 1), $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(-1,                   $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(-0.01,                $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(0.0,                  $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(0,                    $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(0.001,                $exceptionValueNamespace . 'InvalidPropertyValueException'),
            array(true,                 $exceptionValueNamespace . 'InvalidPropertyValueTypeException'),
            array(false,                $exceptionValueNamespace . 'InvalidPropertyValueTypeException'),
        );
    }

    public function invalidOperationIdDataProvider()
    {
        $exceptionOperationIdNamespace = 'YooKassa\\Common\\Exceptions\\';
        return array(
            array(null,                 $exceptionOperationIdNamespace . 'EmptyPropertyValueException'),
            array('',                   $exceptionOperationIdNamespace . 'EmptyPropertyValueException'),
            array(array(),              $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(fopen(__FILE__, 'r'), $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(Random::str(OperationalDetails::OPERATION_ID_MAX_LENGTH + 1), $exceptionOperationIdNamespace . 'InvalidPropertyValueException'),
            array('III',                $exceptionOperationIdNamespace . 'InvalidPropertyValueException'),
            array(-0.01,                $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(0.0,                  $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(0,                    $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(0.01,                 $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(true,                 $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
            array(false,                $exceptionOperationIdNamespace . 'InvalidPropertyValueTypeException'),
        );
    }

    public function invalidCreatedAtDataProvider()
    {
        $exceptionCreatedAtNamespace = 'YooKassa\\Common\\Exceptions\\';
        return array(
            array(null,                 $exceptionCreatedAtNamespace . 'EmptyPropertyValueException'),
            array('',                   $exceptionCreatedAtNamespace . 'EmptyPropertyValueException'),
            array(array(),              $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(fopen(__FILE__, 'r'), $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(Random::str(1, 20), $exceptionCreatedAtNamespace . 'InvalidPropertyValueException'),
            array('III',                $exceptionCreatedAtNamespace . 'InvalidPropertyValueException'),
            array(-0.01,                $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(0.0,                  $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(0,                    $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(0.01,                 $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(true,                 $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
            array(false,                $exceptionCreatedAtNamespace . 'InvalidPropertyValueTypeException'),
        );
    }

    /**
     * @dataProvider validArrayDataProvider
     *
     * @param array $options
     */
    public function testJsonSerialize($options)
    {
        $instance = self::getInstance($options);
        $expected = $options;
        self::assertEquals($expected, $instance->jsonSerialize());
    }
}
