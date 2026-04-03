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

namespace YooKassa\Model\PaymentData\ElectronicCertificate;

use YooKassa\Common\AbstractObject;
use YooKassa\Common\Exceptions\EmptyPropertyValueException;
use YooKassa\Common\Exceptions\InvalidPropertyValueException;
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Helpers\TypeCast;
use YooKassa\Model\AmountInterface;
use YooKassa\Model\Metadata;
use YooKassa\Model\MonetaryAmount;

/**
 * Класс, представляющий модель ElectronicCertificateArticle.
 *
 * Товарная позиция в корзине покупки при оплате по электронному сертификату.
 *
 * @property int $article_number Порядковый номер товара в корзине. От 1 до 999 включительно.
 * @property int $articleNumber Порядковый номер товара в корзине. От 1 до 999 включительно.
 * @property string $tru_code Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)
 * @property string $truCode Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)
 * @property string|null $article_code Код товара в вашей системе. Максимум 128 символов.
 * @property string|null $articleCode Код товара в вашей системе. Максимум 128 символов.
 * @property string $article_name Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.
 * @property string $articleName Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.
 * @property int $quantity Количество единиц товара. Формат: целое положительное число.
 * @property AmountInterface $price Цена за единицу товара.
 * @property Metadata|null $metadata Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8.
 */
class ElectronicCertificateArticle extends AbstractObject
{
    /** @var int Минимальная длина номера товара в корзине */
    const ARTICLE_NUMBER_MIN_VALUE = 1;
    /** @var int Максимальная длина номера товара в корзине */
    const ARTICLE_NUMBER_MAX_VALUE = 999;
    /** @var int Длина кода ТРУ */
    const TRU_CODE_LENGTH = 30;
    /** @var int Максимальная длина кода товара в вашей системе */
    const ARTICLE_CODE_MAX_LENGTH = 128;
    /** @var int Максимальная длина названия товара в вашей системе */
    const ARTICLE_NAME_MAX_LENGTH = 128;

    /**
     * Порядковый номер товара в корзине. От 1 до 999 включительно.
     *
     * @var int
     */
    private $_article_number;

    /**
     * Код ТРУ. 30 символов, две группы цифр, разделенные точкой.
     *
     * Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя.
     *
     * Пример: ~`329921120.06001010200080001643`
     *
     * [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)
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
     * Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.
     *
     * @var string
     */
    private $_article_name;

    /**
     * Количество единиц товара. Формат: целое положительное число.
     *
     * @var int
     */
    private $_quantity;

    /**
     * Цена за единицу товара.
     *
     * @var AmountInterface
     */
    private $_price;

    /**
     * Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8.
     *
     * @var Metadata|null
     */
    private $_metadata;

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
            throw new EmptyPropertyValueException('Empty articleNumber value', 0, 'ElectronicCertificateArticle.articleNumber');
        }
        if (!is_int($value)) {
            throw new InvalidPropertyValueTypeException('Invalid articleNumber value type', 0, 'ElectronicCertificateArticle.articleNumber', $value);
        }
        if ((int)$value < self::ARTICLE_NUMBER_MIN_VALUE || (int)$value > self::ARTICLE_NUMBER_MAX_VALUE) {
            throw new InvalidPropertyValueException('Invalid articleNumber value: "' . $value . '"', 0, 'ElectronicCertificateArticle.articleNumber', $value);
        }

        $this->_article_number = (int)$value;

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
     * @param string $value Код ТРУ. 30 символов
     *
     * @return self
     */
    public function setTruCode($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException('Empty truCode value', 0, 'ElectronicCertificateArticle.truCode');
        }
        if (!TypeCast::canCastToString($value)) {
            throw new InvalidPropertyValueTypeException('Invalid truCode value type', 0, 'ElectronicCertificateArticle.truCode', $value);
        }
        if (mb_strlen($value, 'utf-8') !== self::TRU_CODE_LENGTH) {
            throw new InvalidPropertyValueException('Invalid truCode value: "' . $value . '"', 0, 'ElectronicCertificateArticle.truCode', $value);
        }

        $this->_tru_code = (string)$value;

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
            throw new InvalidPropertyValueTypeException('Invalid articleCode value type', 0, 'ElectronicCertificateArticle.articleCode', $value );
        } elseif (mb_strlen((string)$value, 'utf-8') > self::ARTICLE_CODE_MAX_LENGTH) {
            throw new InvalidPropertyValueException(
                'The value of the articleCode parameter is too long. Max length is ' . self::ARTICLE_CODE_MAX_LENGTH,
                0,
                'ElectronicCertificateArticle.articleCode',
                $value
            );
        } else {
            $this->_article_code = (string)$value;
        }

        return $this;
    }

    /**
     * Возвращает article_name.
     *
     * @return string Название товара в вашей системе
     */
    public function getArticleName()
    {
        return $this->_article_name;
    }

    /**
     * Устанавливает article_name.
     *
     * @param string $value Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.
     *
     * @return self
     */
    public function setArticleName($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException('Empty articleName value', 0, 'ElectronicCertificateArticle.articleName');
        }

        if (!TypeCast::canCastToString($value)) {
            throw new InvalidPropertyValueTypeException('Invalid articleName value type', 0, 'ElectronicCertificateArticle.articleName', $value);
        }

        if (mb_strlen($value, 'utf-8') > self::ARTICLE_NAME_MAX_LENGTH) {
            throw new InvalidPropertyValueException('Invalid articleName value: "' . $value . '"', 0, 'ElectronicCertificateArticle.articleName', $value);
        }

        $this->_article_name = (string)$value;

        return $this;
    }

    /**
     * Возвращает quantity.
     *
     * @return int Количество единиц товара
     */
    public function getQuantity()
    {
        return $this->_quantity;
    }

    /**
     * Устанавливает quantity.
     *
     * @param int $value Количество единиц товара. Формат: целое положительное число.
     *
     * @return self
     */
    public function setQuantity($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException('Empty quantity value', 0, 'ElectronicCertificateArticle.quantity');
        }

        if (!is_int($value)) {
            throw new InvalidPropertyValueTypeException('Invalid quantity value type', 0, 'ElectronicCertificateArticle.quantity', $value);
        }

        if ($value <= 0) {
            throw new InvalidPropertyValueException('Invalid quantity value type', 0, 'ElectronicCertificateArticle.quantity', $value);
        }

        $this->_quantity = (int)$value;

        return $this;
    }

    /**
     * Возвращает price.
     *
     * @return AmountInterface Цена за единицу товара
     */
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * Устанавливает price
     *
     * @param AmountInterface|array $value Цена товара
     *
     * @return self
     */
    public function setPrice($value)
    {
        if (is_array($value)) {
            $this->_price = new MonetaryAmount($value);
        } elseif ($value instanceof AmountInterface) {
            $this->_price = $value;
        } else {
            throw new InvalidPropertyValueTypeException('Invalid price value type in ElectronicCertificateArticle', 0, 'ElectronicCertificateArticle.price', $value);
        }

        return $this;
    }

    /**
     * Возвращает metadata.
     *
     * @return Metadata|null Любые дополнительные данные
     */
    public function getMetadata()
    {
        return $this->_metadata;
    }

    /**
     * Устанавливает metadata.
     *
     * @param Metadata|array|null $value Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8.
     *
     * @return self
     */
    public function setMetadata($value)
    {
        if ($value === null || (is_array($value) && empty($value))) {
            $this->_metadata = null;
        } elseif ($value instanceof Metadata) {
            $this->_metadata = $value;
        } elseif (is_array($value)) {
            $this->_metadata = new Metadata($value);
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid metadata value type in ElectronicCertificateArticle',
                0,
                'ElectronicCertificateArticle.metadata',
                $value
            );
        }

        return $this;
    }
}

