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

namespace YooKassa\Model\PaymentData;

use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData;
use YooKassa\Model\PaymentMethodType;

/**
 * Класс, представляющий модель PaymentMethodDataElectronicCertificate.
 *
 * Данные для оплаты по электронному сертификату.
 *
 * @property string $type Код способа оплаты.
 * @property PaymentDataBankCardCard|null $card Данные банковской карты «Мир».
 * @property ElectronicCertificatePaymentData|null $electronic_certificate Данные от ФЭС НСПК для оплаты по электронному сертификату.
 * @property ElectronicCertificatePaymentData|null $electronicCertificate Данные от ФЭС НСПК для оплаты по электронному сертификату.
 * @property ElectronicCertificateArticle[]|null $articles Корзина покупки (в терминах НСПК) — список товаров, которые можно оплатить по сертификату.  Необходимо передавать только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form).
 */
class PaymentDataElectronicCertificate extends AbstractPaymentData
{
    /**
     * Данные банковской карты «Мир».
     *
     * @var PaymentDataBankCardCard|null
     */
    private $_card;

    /**
     * Данные от ФЭС НСПК для оплаты по электронному сертификату.
     *
     * @var ElectronicCertificatePaymentData|null
     */
    private $_electronic_certificate;

    /**
     * Корзина покупки (в терминах НСПК) — список товаров, которые можно оплатить по сертификату.  Необходимо передавать только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form).
     *
     * @var ElectronicCertificateArticle[]|null
     */
    private $_articles;

    public function __construct()
    {
        $this->setType(PaymentMethodType::ELECTRONIC_CERTIFICATE);
    }

    /**
     * Возвращает данные банковской карты «Мир».
     *
     * @return PaymentDataBankCardCard Данные банковской карты
     */
    public function getCard()
    {
        return $this->_card;
    }

    /**
     * Устанавливает данные банковской карты «Мир».
     *
     * @param PaymentDataBankCardCard|array $value Данные банковской карты
     */
    public function setCard($value = null)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->_card = null;
        } elseif (is_object($value) && $value instanceof PaymentDataBankCardCard) {
            $this->_card = $value;
        } elseif (is_array($value) || $value instanceof \Traversable) {
            $card = new PaymentDataBankCardCard();
            foreach ($value as $property => $val) {
                $card->offsetSet($property, $val);
            }
            $this->_card = $card;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid card value type in PaymentDataElectronicCertificate',
                0,
                'PaymentDataElectronicCertificate.card',
                $value
            );
        }

        return $this;
    }

    /**
     * Возвращает electronic_certificate.
     *
     * @return ElectronicCertificatePaymentData|null Данные от ФЭС НСПК для оплаты по электронному сертификату
     */
    public function getElectronicCertificate()
    {
        return $this->_electronic_certificate;
    }

    /**
     * Устанавливает electronic_certificate.
     *
     * @param ElectronicCertificatePaymentData|array|null $value Данные от ФЭС НСПК для оплаты по электронному сертификату.
     *
     * @return self
     */
    public function setElectronicCertificate($value = null)
    {
        if ($value === null || $value === '') {
            $this->_electronic_certificate = null;
        } elseif (is_array($value)) {
            $this->_electronic_certificate = new ElectronicCertificatePaymentData($value);
        } elseif ($value instanceof ElectronicCertificatePaymentData) {
            $this->_electronic_certificate = $value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid electronicCertificate value type in PaymentDataElectronicCertificate',
                0,
                'PaymentDataElectronicCertificate.electronicCertificate',
                $value
            );
        }

        return $this;
    }

    /**
     * Возвращает articles.
     *
     * @return ElectronicCertificateArticle[]|null Корзина покупки (в терминах НСПК) — список товаров
     */
    public function getArticles()
    {
        return $this->_articles;
    }

    /**
     * Устанавливает articles.
     *
     * @param array|ElectronicCertificateArticle[]|null $value Корзина покупки (в терминах НСПК) — список товаров
     *
     * @return self
     */
    public function setArticles($value = null)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->_articles = null;
        } elseif (is_array($value)) {
            $articles = array();
            foreach ($value as $item) {
                if (is_array($item)) {
                    $articles[] = new ElectronicCertificateArticle($item);
                } elseif ($item instanceof ElectronicCertificateArticle) {
                    $articles[] = $item;
                } else {
                    throw new InvalidPropertyValueTypeException(
                        'Invalid article value type in PaymentDataElectronicCertificate',
                        0,
                        'PaymentDataElectronicCertificate.articles',
                        $item
                    );
                }
            }
            $this->_articles = $articles;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid articles value type in PaymentDataElectronicCertificate',
                0,
                'PaymentDataElectronicCertificate.articles',
                $value
            );
        }

        return $this;
    }
}
