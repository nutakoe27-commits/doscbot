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
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Helpers\TypeCast;
use YooKassa\Model\AmountInterface;
use YooKassa\Model\MonetaryAmount;

/**
 * Класс, представляющий модель ElectronicCertificatePaymentData.
 *
 * Данные от ФЭС НСПК для оплаты по электронному сертификату.
 * Необходимо передавать только при [оплате со сбором данных на вашей стороне](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/merchant-payment-form).
 *
 * @property AmountInterface $amount Сумма, которая спишется с электронного сертификата.
 * @property string $basket_id Идентификатор корзины покупки, сформированной в НСПК.
 * @property string $basketId Идентификатор корзины покупки, сформированной в НСПК.
 */
class ElectronicCertificatePaymentData extends AbstractObject
{
    /**
     * Сумма, которую необходимо использовать по электронному сертификату, — значение `totalCertAmount`, которое вы получили в ФЭС НСПК в [запросе на предварительное одобрение использования сертификата (Pre-Auth)](https://www.nspk.ru/developer/api-fes#operation/preAuthPurchase).
     * Сумма должна быть не больше общей суммы платежа (`amount`).
     *
     * @var AmountInterface
     */
    private $_amount;

    /**
     * Идентификатор корзины покупки, сформированной в НСПК, — значение `purchaseBasketId`, которое вы получили в ФЭС НСПК в [запросе на предварительное одобрение использования сертификата (Pre-Auth)](https://www.nspk.ru/developer/api-fes#operation/preAuthPurchase).
     *
     * @var string
     */
    private $_basket_id;

    /**
     * Возвращает amount.
     *
     * @return AmountInterface Сумма, которая спишется с электронного сертификата
     */
    public function getAmount()
    {
        return $this->_amount;
    }

    /**
     * Устанавливает amount.
     *
     * @param AmountInterface|array $value Сумма, которая спишется с электронного сертификата
     *
     * @return self
     */
    public function setAmount($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty value for "amount" parameter in ElectronicCertificatePaymentData',
                0,
                'ElectronicCertificatePaymentData.amount'
            );
        }

        if (is_array($value)) {
            $this->_amount = $this->factoryAmount($value);
        } elseif ($value instanceof AmountInterface) {
            $this->_amount = $value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid value type for "amount" parameter in ElectronicCertificatePaymentData',
                0,
                'ElectronicCertificatePaymentData.amount',
                $value
            );
        }
    }

    /**
     * Возвращает basket_id.
     *
     * @return string Идентификатор корзины покупки
     */
    public function getBasketId()
    {
        return $this->_basket_id;
    }

    /**
     * Устанавливает basket_id.
     *
     * @param string $basket_id Идентификатор корзины покупки
     *
     * @return self
     */
    public function setBasketId($basket_id)
    {
        if ($basket_id === null || $basket_id === '') {
            throw new EmptyPropertyValueException(
                'Empty basketId value',
                0,
                'ElectronicCertificatePaymentData.basketId'
            );
        }

        if (!TypeCast::canCastToString($basket_id)) {
            throw new InvalidPropertyValueTypeException(
                'Invalid basketId value type',
                0,
                'ElectronicCertificatePaymentData.basketId',
                $basket_id
            );
        }

        $this->_basket_id = (string) $basket_id;

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

