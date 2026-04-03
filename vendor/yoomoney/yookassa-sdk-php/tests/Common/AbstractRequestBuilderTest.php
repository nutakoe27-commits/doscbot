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
use YooKassa\Common\AbstractRequest;
use YooKassa\Common\AbstractRequestBuilder;
use YooKassa\Common\Exceptions\InvalidRequestException;

class AbstractRequestBuilderTest extends TestCase
{
    public function testBuild()
    {
        $builder = new TestAbstractRequestBuilder();
        try {
            $builder->build(array());
        } catch (InvalidRequestException $e) {
            $request = $builder->build(array(
                'isValid' => true,
            ));
            self::assertTrue($request->isValid());

            $mess = 'test message';
            try {
                $builder->build(array(
                    'throwException' => new \Exception($mess),
                ));
            } catch (\Exception $e) {
                self::assertEquals($mess, $e->getPrevious()->getMessage());
                return;
            }
            self::fail('Exception not thrown in setThrowException method');
            return;
        }
        self::fail('Exception not thrown in build method');
    }

    public function testSetOptions()
    {
        $builder = new TestAbstractRequestBuilder();

        $builder->setOptions(array());
        try {
            $builder->build();
        } catch (InvalidRequestException $e) {
            $builder->setOptions(array(
                'is_valid' => true,
            ));
            self::assertTrue($builder->build()->isValid());

            try {
                $builder->setOptions('test');
            } catch (\Exception $e) {
                self::assertTrue($e instanceof \InvalidArgumentException);
                return;
            }
            self::fail('Exception not thrown in setOptions method');
            return;
        }
        self::fail('Exception not thrown in build method');
    }
}

class TestAbstractRequestBuilder extends AbstractRequestBuilder
{
    /**
     * Инициализирует пустой запрос
     * @return TestAbstractRequestClass Инстанс запроса, который будем собирать
     */
    protected function initCurrentObject()
    {
        return new TestAbstractRequestClass();
    }

    public function setIsValid($value)
    {
        $this->currentObject->setIsValid($value);
        return $this;
    }

    /**
     * @param \Exception $e
     * @return TestAbstractRequestBuilder
     * @throws \Exception
     */
    public function setThrowException(\Exception $e)
    {
        $this->currentObject->setThrowException($e);
        return $this;
    }
}

class TestAbstractRequestClass extends AbstractRequest
{
    private $valid = false;

    /**
     * @var \Exception|null
     */
    private $exception = null;

    /**
     * @param \Exception $e
     * @throws \Exception
     */
    public function setThrowException(\Exception $e)
    {
        $this->exception = $e;
    }

    /**
     * @param bool $value
     */
    public function setIsValid($value)
    {
        $this->valid = $value ? true : false;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Валидирует текущий запрос, проверяет все ли нужные свойства установлены
     * @return bool True если запрос валиден, false если нет
     */
    public function validate()
    {
        if ($this->exception !== null) {
            $this->setValidationError($this->exception->getMessage());
            throw $this->exception;
        }
        return $this->valid;
    }
}
