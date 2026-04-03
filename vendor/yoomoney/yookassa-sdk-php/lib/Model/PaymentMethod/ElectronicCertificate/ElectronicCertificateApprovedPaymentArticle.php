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

namespace YooKassa\Model\PaymentMethod\ElectronicCertificate;

use YooKassa\Common\AbstractObject;
use YooKassa\Common\Exceptions\EmptyPropertyValueException;
use YooKassa\Common\Exceptions\InvalidPropertyValueException;
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Helpers\TypeCast;

/**
 * Класс, представляющий модель ElectronicCertificateApprovedPaymentArticle.
 *
 * Товарная позиция в одобренной корзине покупки при оплате по электронному сертификату.
 *
 * @property int $article_number Порядковый номер товара в корзине. От 1 до 999 включительно.
 * @property int $articleNumber Порядковый номер товара в корзине. От 1 до 999 включительно.
 * @property string $tru_code Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)
 * @property string $truCode Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)
 * @property string|null $article_code Код товара в вашей системе. Максимум 128 символов.
 * @property string|null $articleCode Код товара в вашей системе. Максимум 128 символов.
 * @property ElectronicCertificate[] $certificates Список электронных сертификатов, которые используются для оплаты покупки.
 */
class ElectronicCertificateApprovedPaymentArticle extends AbstractObject
{
    /** @var int Минимальное значение номера товара в корзине */
    const ARTICLE_NUMBER_MIN_VALUE = 1;
    /** @var int Максимальное значение номера товара в корзине */
    const ARTICLE_NUMBER_MAX_VALUE = 999;
    /** @var int Длина кода ТРУ */
    const TRU_CODE_LENGTH = 30;
    /** @var int Максимальная длина кода товара в вашей системе */
    const ARTICLE_CODE_MAX_LENGTH = 128;

    /**
     * Порядковый номер товара в корзине. От 1 до 999 включительно.
     *
     * @var int
     */
    private $_article_number;

    /**
     * Код ТРУ. 30 символов, две группы цифр, разделенные точкой.
     *
     * @var string
     */
    private $_tru_code;

    /**
     * Код товара в вашей системе. Максимум 128 символов.
     *
     * @var string|null
     */
    private $_article_code;

    /**
     * Список электронных сертификатов, которые используются для оплаты покупки.
     *
     * @var array
     */
    private $_certificates;

    /**
     * Возвращает article_number.
     *
     * @return int Порядковый номер товара в корзине
     */
    public function getArticleNumber()
    {
        return $this->_article_number;
    }

    /**
     * Устанавливает article_number.
     *
     * @param int $value Порядковый номер товара в корзине. От 1 до 999 включительно.
     *
     * @return self
     */
    public function setArticleNumber($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty articleNumber value',
                0,
                'ElectronicCertificateApprovedPaymentArticle.articleNumber'
            );
        }

        if (!is_numeric($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid articleNumber value type',
                0,
                'ElectronicCertificateApprovedPaymentArticle.articleNumber',
                $value
            );
        }

        $value = (int)$value;
        if ($value < self::ARTICLE_NUMBER_MIN_VALUE || $value > self::ARTICLE_NUMBER_MAX_VALUE) {
            throw new InvalidPropertyValueException(
                'Invalid articleNumber value: must be between ' . self::ARTICLE_NUMBER_MIN_VALUE
                . ' and ' . self::ARTICLE_NUMBER_MAX_VALUE,
                0,
                'ElectronicCertificateApprovedPaymentArticle.articleNumber',
                $value
            );
        }

        $this->_article_number = $value;

        return $this;
    }

    /**
     * Возвращает tru_code.
     *
     * @return string Код ТРУ
     */
    public function getTruCode()
    {
        return $this->_tru_code;
    }

    /**
     * Устанавливает tru_code.
     *
     * @param string|null $value Код ТРУ. 30 символов
     *
     * @return self
     */
    public function setTruCode($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty truCode value',
                0,
                'ElectronicCertificateApprovedPaymentArticle.truCode'
            );
        }

        if (!TypeCast::canCastToString($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid truCode value type',
                0,
                'ElectronicCertificateApprovedPaymentArticle.truCode',
                $value
            );
        }

        $value = (string)$value;
        if (mb_strlen($value, 'utf-8') !== self::TRU_CODE_LENGTH) {
            throw new InvalidPropertyValueException(
                'Invalid truCode value: length must be exactly ' . self::TRU_CODE_LENGTH . ' characters',
                0,
                'ElectronicCertificateApprovedPaymentArticle.truCode',
                $value
            );
        }

        $this->_tru_code = $value;

        return $this;
    }

    /**
     * Возвращает article_code.
     *
     * @return string|null Код товара в вашей системе
     */
    public function getArticleCode()
    {
        return $this->_article_code;
    }

    /**
     * Устанавливает article_code.
     *
     * @param string|null $value Код товара в вашей системе. Максимум 128 символов.
     *
     * @return self
     */
    public function setArticleCode($value = null)
    {
        if ($value === null || $value === '') {
            $this->_article_code = null;
        } elseif (!TypeCast::canCastToString($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid articleCode value type',
                0,
                'ElectronicCertificateApprovedPaymentArticle.articleCode',
                $value
            );
        } elseif (mb_strlen((string)$value, 'utf-8') > self::ARTICLE_CODE_MAX_LENGTH) {
            throw new InvalidPropertyValueException(
                'Invalid articleCode value: length must not exceed ' . self::ARTICLE_CODE_MAX_LENGTH . ' characters',
                0,
                'ElectronicCertificateApprovedPaymentArticle.articleCode',
                $value
            );
        } else {
            $this->_article_code = (string)$value;
        }

        return $this;
    }

    /**
     * Возвращает certificates.
     *
     * @return array Список электронных сертификатов
     */
    public function getCertificates()
    {
        return $this->_certificates;
    }

    /**
     * Устанавливает certificates.
     *
     * @param array $value Список электронных сертификатов, которые используются для оплаты покупки.
     *
     * @return self
     */
    public function setCertificates($value = null)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            throw new EmptyPropertyValueException(
                'Empty certificates value',
                0,
                'ElectronicCertificateApprovedPaymentArticle.certificates'
            );
        }

        if (!is_array($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid certificates value type: expected array',
                0,
                'ElectronicCertificateApprovedPaymentArticle.certificates',
                $value
            );
        }

        $certificates = array();
        foreach ($value as $item) {
            if (is_array($item)) {
                $certificates[] = new ElectronicCertificate($item);
            } elseif ($item instanceof ElectronicCertificate) {
                $certificates[] = $item;
            } else {
                throw new InvalidPropertyValueTypeException(
                    'ElectronicCertificate must be instance of ElectronicCertificate',
                    0,
                    'ElectronicCertificateApprovedPaymentArticle.certificates',
                    $item
                );
            }
        }

        $this->_certificates = $certificates;

        return $this;
    }
}

