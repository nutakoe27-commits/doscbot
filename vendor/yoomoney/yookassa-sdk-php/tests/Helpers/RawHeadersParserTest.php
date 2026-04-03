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

namespace Tests\YooKassa\Helpers;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\RawHeadersParser;

class RawHeadersParserTest extends TestCase
{
    /**
     * @dataProvider headersDataProvider
     * @param $rawHeaders
     * @param $expected
     */
    public function testParse($rawHeaders, $expected)
    {
        $this->assertEquals($expected, RawHeadersParser::parse($rawHeaders));
    }

    public function headersDataProvider()
    {
        return array(
            array(
                'Server: nginx
                Date: Thu, 03 Dec 2020 16:04:35 GMT
                Content-Type: text/html
                Content-Length: 178
                Connection: keep-alive
                Location: https://yoomoney.ru/',
                array(
                    'Server' => 'nginx',
                    'Date' => 'Thu, 03 Dec 2020 16:04:35 GMT',
                    'Content-Type' => 'text/html',
                    'Content-Length' => '178',
                    'Connection' => 'keep-alive',
                    'Location' => 'https://yoomoney.ru/'
                ),
            ),
            array(
                "HTTP/1.1 200 Ok\r\n" .
                "Server: nginx\r\n" .
                "Date: Thu, 03 Dec 2020 16:04:35 GMT\r\n" .
                "Content-Type: text/html\r\n" .
                "Content-Length: 178\r\n" .
                "Array-Header: value1\r\n" .
                "Connection: keep-alive\r\n" .
                "Array-Header: value2\r\n" .
                "Location: https://yoomoney.ru/\r\n" .
                "\r\n" .
                "Content-Length: 132",
                array(
                    0 => 'HTTP/1.1 200 Ok',
                    'Server' => 'nginx',
                    'Date' => 'Thu, 03 Dec 2020 16:04:35 GMT',
                    'Content-Type' => 'text/html',
                    'Content-Length' => '178',
                    'Array-Header' => array(
                        'value1', 'value2'
                    ),
                    'Connection' => 'keep-alive',
                    'Location' => 'https://yoomoney.ru/'
                ),
            ),
            array(
                "HTTP/1.1 200 Ok\r\n" .
                "Server: nginx\r\n" .
                "\tversion 1.3.4\r\n" .
                "Date: Thu, 03 Dec 2020 16:04:35 GMT\r\n" .
                "Content-Type: text/html\r\n" .
                "Content-Length: 178\r\n" .
                "Array-Header: value1\r\n" .
                "Connection: keep-alive\r\n" .
                "Array-Header: value2\r\n" .
                "Location: https://yoomoney.ru/\r\n" .
                "Array-Header: value3\r\n" .
                "\r\n" .
                "Content-Length: 132",
                array(
                    0 => 'HTTP/1.1 200 Ok',
                    'Server' => "nginx\r\n\tversion 1.3.4",
                    'Date' => 'Thu, 03 Dec 2020 16:04:35 GMT',
                    'Content-Type' => 'text/html',
                    'Content-Length' => '178',
                    'Array-Header' => array(
                        'value1', 'value2', 'value3',
                    ),
                    'Connection' => 'keep-alive',
                    'Location' => 'https://yoomoney.ru/'
                ),
            ),
        );
    }
}
