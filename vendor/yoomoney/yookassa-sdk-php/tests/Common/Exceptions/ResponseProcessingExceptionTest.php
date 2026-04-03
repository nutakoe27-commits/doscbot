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

namespace Tests\YooKassa\Common\Exceptions;

use YooKassa\Common\Exceptions\ResponseProcessingException;

class ResponseProcessingExceptionTest extends ApiExceptionTest
{
    public function getTestInstance($message = '', $code = 0, $responseHeaders = array(), $responseBody = null)
    {
        return new ResponseProcessingException($responseHeaders, $responseBody);
    }

    public function expectedHttpCode()
    {
        return ResponseProcessingException::HTTP_CODE;
    }

    /**
     * @dataProvider descriptionDataProvider
     * @param string $body
     */
    public function testDescription($body)
    {
        $instance = $this->getTestInstance('', 0, array(), $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['description'])) {
            self::assertEquals('', $instance->getMessage());
        } else {
            self::assertEquals($tmp['description'] . '.', $instance->getMessage());
        }
    }

    public function descriptionDataProvider()
    {
        return array(
            array('{}'),
            array('{"description":"test"}'),
            array('{"description":"У попа была собака"}'),
        );
    }

    /**
     * @dataProvider retryAfterDataProvider
     * @param string $body
     */
    public function testRetryAfter($body)
    {
        $instance = $this->getTestInstance('', 0, array(), $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['retry_after'])) {
            self::assertNull($instance->retryAfter);
        } else {
            self::assertEquals($tmp['retry_after'], $instance->retryAfter);
        }
    }

    public function retryAfterDataProvider()
    {
        return array(
            array('{}'),
            array('{"retry_after":-20}'),
            array('{"retry_after":123}'),
        );
    }

    /**
     * @dataProvider typeDataProvider
     * @param string $body
     */
    public function testType($body)
    {
        $instance = $this->getTestInstance('', 0, array(), $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['type'])) {
            self::assertNull($instance->type);
        } else {
            self::assertEquals($tmp['type'], $instance->type);
        }
    }

    public function typeDataProvider()
    {
        return array(
            array('{}'),
            array('{"type":"server_error"}'),
            array('{"type":"error"}'),
        );
    }

    /**
     * @dataProvider messageDataProvider
     * @param $body
     */
    public function testMessage($body)
    {
        $instance = $this->getTestInstance('', 0, array(), $body);

        $tmp = json_decode($body, true);
        $message = '';
        if (!empty($tmp['description'])) {
            $message = $tmp['description'] . '.';
        }
        self::assertEquals($message, $instance->getMessage());

        if (empty($tmp['retry_after'])) {
            self::assertNull($instance->retryAfter);
        } else {
            self::assertEquals($tmp['retry_after'], $instance->retryAfter);
        }
        if (empty($tmp['type'])) {
            self::assertNull($instance->type);
        } else {
            self::assertEquals($tmp['type'], $instance->type);
        }
    }

    public function messageDataProvider()
    {
        return array(
            array('{}'),
            array('{"code":"server_error","description":"Internal server error"}'),
            array('{"code":"server_error","description":"Invalid parameter value","parameter":"shop_id"}'),
            array('{"code":"server_error","description":"Invalid parameter value","parameter":"shop_id","type":"test"}'),
            array('{"code":"server_error","description":"Invalid parameter value","parameter":"shop_id","retry_after":333}'),
        );
    }

    public function testExceptionCode()
    {
        $instance = $this->getTestInstance();
        self::assertEquals($this->expectedHttpCode(), $instance->getCode());
    }
}
