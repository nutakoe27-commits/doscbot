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

namespace YooKassa\Model\PaymentMethod;

use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle;
use YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData;
use YooKassa\Model\PaymentMethodType;

/**
 * Класс, представляющий модель PaymentMethodElectronicCertificate.
 *
 * Оплата по электронному сертификату.
 *
 * @property string $type Код способа оплаты.
 * @property BankCard|null $card Данные банковской карты «Мир».
 * @property ElectronicCertificatePaymentData|null $electronic_certificate Данные от ФЭС НСПК для оплаты по электронному сертификату.
 * @property ElectronicCertificatePaymentData|null $electronicCertificate Данные от ФЭС НСПК для оплаты по электронному сертификату.
 * @property ElectronicCertificateApprovedPaymentArticle[]|null $articles Одобренная корзина покупки — список товаров, одобренных к оплате по электронному сертификату.  Присутствует только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form).
 */
class PaymentMethodElectronicCertificate extends AbstractPaymentMethod
{
    /**
     * @var BankCard|null
     */
    private $_card;

    /**
     * Данные от ФЭС НСПК для оплаты по электронному сертификату.
     *
     * @var ElectronicCertificatePaymentData|null
     */
    private $_electronic_certificate;

    /**
     * Одобренная корзина покупки — список товаров, одобренных к оплате по электронному сертификату.  Присутствует только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form).
     *
     * @var ElectronicCertificateApprovedPaymentArticle[]|null
     */
    private $_articles;

    public function __construct()
    {
        $this->setType(PaymentMethodType::ELECTRONIC_CERTIFICATE);
    }

    /**
     * Возвращает card.
     *
     * @return BankCard|null Данные банковской карты «Мир»
     */
    public function getCard()
    {
        return $this->_card;
    }

    /**
     * Устанавливает card.
     *
     * @param BankCard|array|null $value Данные банковской карты «Мир»
     *
     * @return self
     */
    public function setCard($value = null)
    {
        if ($value === null || $value === '') {
            $this->_card = null;
        } elseif (is_array($value)) {
            $this->_card = new BankCard($value);
        } elseif ($value instanceof BankCard) {
            $this->_card = $value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid card value type in PaymentMethodElectronicCertificate',
                0,
                'PaymentMethodElectronicCertificate.card',
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
                'Invalid electronicCertificate value type in PaymentMethodElectronicCertificate',
                0,
                'PaymentMethodElectronicCertificate.electronicCertificate',
                $value
            );
        }

        return $this;
    }

    /**
     * Возвращает articles.
     *
     * @return array|null Одобренная корзина покупки — список товаров
     */
    public function getArticles()
    {
        return $this->_articles;
    }

    /**
     * Устанавливает articles.
     *
     * @param array|null $value Одобренная корзина покупки — список товаров
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
                    $articles[] = new ElectronicCertificateApprovedPaymentArticle($item);
                } elseif ($item instanceof ElectronicCertificateApprovedPaymentArticle) {
                    $articles[] = $item;
                } else {
                    throw new InvalidPropertyValueTypeException(
                        'Article must be instance of ElectronicCertificateApprovedPaymentArticle',
                        0,
                        'PaymentMethodElectronicCertificate.articles',
                        $item
                    );
                }
            }
            $this->_articles = $articles;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid articles value type in PaymentMethodElectronicCertificate',
                0,
                'PaymentMethodElectronicCertificate.articles',
                $value
            );
        }

        return $this;
    }
}

