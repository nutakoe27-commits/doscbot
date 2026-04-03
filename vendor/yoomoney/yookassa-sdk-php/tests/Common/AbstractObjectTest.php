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

namespace Tests\YooKassa\Common;

use PHPUnit\Framework\TestCase;
use YooKassa\Common\AbstractObject;

class AbstractObjectTest extends TestCase
{
    protected function getTestInstance()
    {
        return new TestAbstractObject();
    }

    /**
     * @dataProvider offsetDataProvider
     * @param $value
     * @param $exists
     */
    public function testOffsetExists($value, $exists)
    {
        $instance = $this->getTestInstance();
        if ($exists) {
            self::assertTrue($instance->offsetExists($value));
            self::assertTrue(isset($instance[$value]));
            self::assertTrue(isset($instance->{$value}));
        } else {
            self::assertFalse($instance->offsetExists($value));
            self::assertFalse(isset($instance[$value]));
            self::assertFalse(isset($instance->{$value}));

            $instance->offsetSet($value, $value);
            self::assertTrue($instance->offsetExists($value));
            self::assertTrue(isset($instance[$value]));
            self::assertTrue(isset($instance->{$value}));
        }
    }

    /**
     * @dataProvider offsetDataProvider
     * @param $value
     */
    public function testOffsetGet($value)
    {
        $instance = $this->getTestInstance();

        $tmp = $instance->offsetGet($value);
        self::assertNull($tmp);
        $tmp = $instance[$value];
        self::assertNull($tmp);
        $tmp = $instance->{$value};
        self::assertNull($tmp);

        $instance->offsetSet($value, $value);

        $tmp = $instance->offsetGet($value);
        self::assertEquals($value, $tmp);
        $tmp = $instance[$value];
        self::assertEquals($value, $tmp);
        $tmp = $instance->{$value};
        self::assertEquals($value, $tmp);
    }

    /**
     * @dataProvider offsetDataProvider
     * @param $value
     * @param $exists
     */
    public function testOffsetUnset($value, $exists)
    {
        $instance = $this->getTestInstance();
        if ($exists) {
            self::assertTrue($instance->offsetExists($value));
            $instance->offsetUnset($value);
            self::assertTrue($instance->offsetExists($value));
            unset($instance[$value]);
            self::assertTrue($instance->offsetExists($value));
            unset($instance->{$value});
            self::assertTrue($instance->offsetExists($value));
        } else {
            self::assertFalse($instance->offsetExists($value));
            $instance->offsetUnset($value);
            self::assertFalse($instance->offsetExists($value));
            unset($instance[$value]);
            self::assertFalse($instance->offsetExists($value));
            unset($instance->{$value});
            self::assertFalse($instance->offsetExists($value));

            $instance->{$value} = $value;
            self::assertTrue($instance->offsetExists($value));
            $instance->offsetUnset($value);
            self::assertFalse($instance->offsetExists($value));

            $instance->{$value} = $value;
            self::assertTrue($instance->offsetExists($value));
            unset($instance[$value]);
            self::assertFalse($instance->offsetExists($value));

            $instance->{$value} = $value;
            self::assertTrue($instance->offsetExists($value));
            unset($instance->{$value});
            self::assertFalse($instance->offsetExists($value));
        }
    }

    public function offsetDataProvider()
    {
        return array(
            array('property', true),
            array('propertyCamelCase', true),
            array('property_camel_case', true),
            array('Property', true),
            array('PropertyCamelCase', true),
            array('Property_Camel_Case', true),
            array('not_exists', false),
        );
    }

    public function testJsonSerialize()
    {
        $instance = $this->getTestInstance();

        $data = $instance->jsonSerialize();
        $expected = array();
        self::assertEquals($expected, $data);

        $instance->setProperty('propertyValue');
        $data = $instance->jsonSerialize();
        $expected = array(
            'property' => 'propertyValue',
        );
        self::assertEquals($expected, $data);

        $instance->setPropertyCamelCase($this->getTestInstance());
        $data = $instance->jsonSerialize();
        $expected = array(
            'property' => 'propertyValue',
            'property_camel_case' => array(),
        );
        self::assertEquals($expected, $data);

        $instance->getPropertyCamelCase()->setProperty(array('test', 1, 2, 3));
        $data = $instance->jsonSerialize();
        $expected = array(
            'property' => 'propertyValue',
            'property_camel_case' => array(
                'property' => array('test', 1, 2, 3),
            ),
        );
        self::assertEquals($expected, $data);

        $date = new \DateTime();
        $instance->getPropertyCamelCase()->setPropertyCamelCase($date);
        $data = $instance->jsonSerialize();
        $expected = array(
            'property' => 'propertyValue',
            'property_camel_case' => array(
                'property' => array('test', 1, 2, 3),
                'property_camel_case' => $date->format(YOOKASSA_DATE),
            ),
        );
        self::assertEquals($expected, $data);

        $instance->getPropertyCamelCase()->unknown_obj = $this->getTestInstance();
        $data = $instance->jsonSerialize();
        $expected = array(
            'property' => 'propertyValue',
            'property_camel_case' => array(
                'property' => array('test', 1, 2, 3),
                'property_camel_case' => $date->format(YOOKASSA_DATE),
                'unknown_obj' => array(),
            ),
        );
        self::assertEquals($expected, $data);

        $instance->unknown_property = true;
        $data = $instance->jsonSerialize();
        $expected['unknown_property'] = true;
        self::assertEquals($expected, $data);

        $instance->unknown_array = array(false);
        $data = $instance->jsonSerialize();
        $expected['unknown_array'] = array(false);
        self::assertEquals($expected, $data);

        $instance->unknown_date = $date;
        $data = $instance->jsonSerialize();
        $expected['unknown_date'] = $date->format(YOOKASSA_DATE);
        self::assertEquals($expected, $data);

        $obj = new \stdClass();
        $obj->test = 'test1';
        $instance->unknown_obj = $obj;
        $data = $instance->jsonSerialize();
        $expected['unknown_obj'] = $obj;
        self::assertEquals($expected, $data);

        $obj = new \stdClass();
        $obj->test = 'test2';
        $instance->property_camel_case = $obj;
        $data = $instance->jsonSerialize();
        $expected['property_camel_case'] = $obj;
        self::assertEquals($expected, $data);

        $instance->property_camel_case = null;
        $data = $instance->jsonSerialize();
        unset($expected['property_camel_case']);
        self::assertEquals($expected, $data);
    }
}

class TestAbstractObject extends AbstractObject
{
    private $_property;
    private $_anotherProperty;

    public function getProperty()
    {
        return $this->_property;
    }

    public function setProperty($value)
    {
        $this->_property = $value;
    }

    public function getPropertyCamelCase()
    {
        return $this->_anotherProperty;
    }

    public function setPropertyCamelCase($value)
    {
        $this->_anotherProperty = $value;
    }
}
