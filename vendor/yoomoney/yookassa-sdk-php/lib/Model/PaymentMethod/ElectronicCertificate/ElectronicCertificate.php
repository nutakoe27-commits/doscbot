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
use YooKassa\Model\AmountInterface;
use YooKassa\Model\MonetaryAmount;

/**
 * Класс, представляющий модель ElectronicCertificate.
 *
 * Описание используемого электронного сертификата.
 *
 * @property string $certificate_id Идентификатор сертификата. От 20 до 30 символов.
 * @property string $certificateId Идентификатор сертификата. От 20 до 30 символов.
 * @property int $tru_quantity Количество единиц товара, которое одобрили для оплаты по этому электронному сертификату.
 * @property int $truQuantity Количество единиц товара, которое одобрили для оплаты по этому электронному сертификату.
 * @property AmountInterface $available_compensation Максимально допустимая сумма, которую может покрыть электронный сертификат для оплаты одной единицы товара. Пример: сертификат может компенсировать максимум 1000 рублей для оплаты этого товара.
 * @property AmountInterface $availableCompensation Максимально допустимая сумма, которую может покрыть электронный сертификат для оплаты одной единицы товара. Пример: сертификат может компенсировать максимум 1000 рублей для оплаты этого товара.
 * @property AmountInterface $applied_compensation Сумма, которую одобрили для оплаты по сертификату за одну единицу товара. Пример: из 1000 рублей одобрили 500 рублей для оплаты по сертификату.
 * @property AmountInterface $appliedCompensation Сумма, которую одобрили для оплаты по сертификату за одну единицу товара. Пример: из 1000 рублей одобрили 500 рублей для оплаты по сертификату.
 */
class ElectronicCertificate extends AbstractObject
{
    /** @var int Минимальная длина идентификатора сертификата */
    const CERTIFICATE_ID_MIN_LENGTH = 20;
    /** @var int Максимальная длина идентификатора сертификата */
    const CERTIFICATE_ID_MAX_LENGTH = 30;

    /**
     * Идентификатор сертификата. От 20 до 30 символов.
     *
     * @var string
     */
    private $_certificate_id;

    /**
     * Количество единиц товара, которое одобрили для оплаты по этому электронному сертификату.
     *
     * @var int
     */
    private $_tru_quantity;

    /**
     * Максимально допустимая сумма, которую может покрыть электронный сертификат для оплаты одной единицы товара.
     * Пример: сертификат может компенсировать максимум 1000 рублей для оплаты этого товара.
     *
     * @var AmountInterface
     */
    private $_available_compensation;

    /**
     * Сумма, которую одобрили для оплаты по сертификату за одну единицу товара.
     * Пример: из 1000 рублей одобрили 500 рублей для оплаты по сертификату.
     *
     * @var AmountInterface
     */
    private $_applied_compensation;

    /**
     * Возвращает certificate_id.
     *
     * @return string Идентификатор сертификата
     */
    public function getCertificateId()
    {
        return $this->_certificate_id;
    }

    /**
     * Устанавливает certificate_id.
     *
     * @param string $value Идентификатор сертификата. От 20 до 30 символов.
     *
     * @return self
     */
    public function setCertificateId($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty certificateId value',
                0,
                'ElectronicCertificate.certificateId'
            );
        }

        if (!TypeCast::canCastToString($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid certificateId value type',
                0,
                'ElectronicCertificate.certificateId',
                $value
            );
        }

        $length = mb_strlen((string)$value, 'utf-8');
        if ($length < self::CERTIFICATE_ID_MIN_LENGTH || $length > self::CERTIFICATE_ID_MAX_LENGTH) {
            throw new InvalidPropertyValueException(
                'Invalid certificateId value: length must be between ' . self::CERTIFICATE_ID_MIN_LENGTH
                . ' and ' . self::CERTIFICATE_ID_MAX_LENGTH . ' characters',
                0,
                'ElectronicCertificate.certificateId',
                $value
            );
        }

        $this->_certificate_id = (string)$value;

        return $this;
    }

    /**
     * Возвращает tru_quantity.
     *
     * @return int Количество единиц товара
     */
    public function getTruQuantity()
    {
        return $this->_tru_quantity;
    }

    /**
     * Устанавливает tru_quantity.
     *
     * @param int $value Количество единиц товара, которое одобрили для оплаты по этому электронному сертификату.
     *
     * @return self
     */
    public function setTruQuantity($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty truQuantity value',
                0,
                'ElectronicCertificate.truQuantity'
            );
        }

        if (!is_numeric($value)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid truQuantity value type',
                0,
                'ElectronicCertificate.truQuantity',
                $value
            );
        }

        $this->_tru_quantity = (int)$value;

        return $this;
    }

    /**
     * Возвращает available_compensation.
     *
     * @return AmountInterface Максимально допустимая сумма
     */
    public function getAvailableCompensation()
    {
        return $this->_available_compensation;
    }

    /**
     * Устанавливает available_compensation.
     *
     * @param AmountInterface|array $value Максимально допустимая сумма
     *
     * @return self
     */
    public function setAvailableCompensation($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty availableCompensation value',
                0,
                'ElectronicCertificate.availableCompensation'
            );
        }

        if (is_array($value)) {
            $this->_available_compensation = $this->factoryAmount($value);
        } elseif ($value instanceof AmountInterface) {
            $this->_available_compensation = $value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid availableCompensation value type',
                0,
                'ElectronicCertificate.availableCompensation',
                $value
            );
        }

        return $this;
    }

    /**
     * Возвращает applied_compensation.
     *
     * @return AmountInterface Сумма, которую одобрили для оплаты по сертификату
     */
    public function getAppliedCompensation()
    {
        return $this->_applied_compensation;
    }

    /**
     * Устанавливает applied_compensation.
     *
     * @param AmountInterface|array $value Сумма, которую одобрили для оплаты по сертификату
     *
     * @return self
     */
    public function setAppliedCompensation($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty appliedCompensation value',
                0,
                'ElectronicCertificate.appliedCompensation'
            );
        }

        if (is_array($value)) {
            $this->_applied_compensation = $this->factoryAmount($value);
        } elseif ($value instanceof AmountInterface) {
            $this->_applied_compensation = $value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid appliedCompensation value type',
                0,
                'ElectronicCertificate.appliedCompensation',
                $value
            );
        }

        return $this;
    }

    /**
     * Фабричный метод создания суммы
     *
     * @param array $options Сумма в виде ассоциативного массива
     *
     * @return AmountInterface Созданный инстанс суммы
     */
    private function factoryAmount($options)
    {
        $amount = new MonetaryAmount(null, $options['currency']);
        if ($options['value'] > 0) {
            $amount->setValue($options['value']);
        }

        return $amount;
    }
}

