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

use PHPUnit\Framework\TestCase;
use YooKassa\Common\Exceptions\ApiException;

class ApiExceptionTest extends TestCase
{
    public function getTestInstance($message = '', $code = 0, $responseHeaders = array(), $responseBody = null)
    {
        return new ApiException($message, $code, $responseHeaders, $responseBody);
    }

    /**
     * @dataProvider responseHeadersDataProvider
     * @param array $headers
     */
    public function testGetResponseHeaders($headers)
    {
        $instance = $this->getTestInstance('', 0, $headers);
        self::assertEquals($headers, $instance->getResponseHeaders());
    }

    public function responseHeadersDataProvider()
    {
        return array(
            array(
                array(),
            ),
            array(
                array('HTTP/1.1 200 Ok'),
            ),
            array(
                array(
                    'HTTP/1.1 200 Ok',
                    'Content-length: 0',
                ),
            ),
            array(
                array(
                    'HTTP/1.1 200 Ok',
                    'Content-length: 0',
                    'Connection: close',
                ),
            ),
        );
    }

    /**
     * @dataProvider responseBodyDataProvider
     * @param string $body
     */
    public function testGetResponseBody($body)
    {
        $instance = $this->getTestInstance('', 0, array(), $body);
        self::assertEquals($body, $instance->getResponseBody());
    }

    public function responseBodyDataProvider()
    {
        return array(
            array(
                '',
            ),
            array(
                '{"success":true}',
            ),
            array(
                '<html></html>',
            ),
        );
    }
}
