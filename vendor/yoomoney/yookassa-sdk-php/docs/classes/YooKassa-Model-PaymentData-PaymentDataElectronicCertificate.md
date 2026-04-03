# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Model\PaymentData\PaymentDataElectronicCertificate
### Namespace: [\YooKassa\Model\PaymentData](../namespaces/yookassa-model-paymentdata.md)
---
**Summary:**

Класс, представляющий модель PaymentMethodDataElectronicCertificate.

**Description:**

Данные для оплаты по электронному сертификату.

---
### Constants
* No constants found

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [$articles](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#property_articles) |  | Корзина покупки (в терминах НСПК) — список товаров, которые можно оплатить по сертификату.  Необходимо передавать только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form). |
| public | [$card](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#property_card) |  | Данные банковской карты «Мир». |
| public | [$electronic_certificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#property_electronic_certificate) |  | Данные от ФЭС НСПК для оплаты по электронному сертификату. |
| public | [$electronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#property_electronicCertificate) |  | Данные от ФЭС НСПК для оплаты по электронному сертификату. |
| public | [$type](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#property_type) |  | Код способа оплаты. |
| public | [$type](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md#property_type) |  | Тип метода оплаты |

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [__construct()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method___construct) |  |  |
| public | [__get()](../classes/YooKassa-Common-AbstractObject.md#method___get) |  | Возвращает значение свойства |
| public | [__isset()](../classes/YooKassa-Common-AbstractObject.md#method___isset) |  | Проверяет наличие свойства |
| public | [__set()](../classes/YooKassa-Common-AbstractObject.md#method___set) |  | Устанавливает значение свойства |
| public | [__unset()](../classes/YooKassa-Common-AbstractObject.md#method___unset) |  | Удаляет свойство |
| public | [fromArray()](../classes/YooKassa-Common-AbstractObject.md#method_fromArray) |  | Устанавливает значения свойств текущего объекта из массива |
| public | [getArticles()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_getArticles) |  | Возвращает articles. |
| public | [getCard()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_getCard) |  | Возвращает данные банковской карты «Мир». |
| public | [getElectronicCertificate()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_getElectronicCertificate) |  | Возвращает electronic_certificate. |
| public | [getType()](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md#method_getType) |  | Возвращает тип метода оплаты |
| public | [jsonSerialize()](../classes/YooKassa-Common-AbstractObject.md#method_jsonSerialize) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации |
| public | [offsetExists()](../classes/YooKassa-Common-AbstractObject.md#method_offsetExists) |  | Проверяет наличие свойства |
| public | [offsetGet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetGet) |  | Возвращает значение свойства |
| public | [offsetSet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetSet) |  | Устанавливает значение свойства |
| public | [offsetUnset()](../classes/YooKassa-Common-AbstractObject.md#method_offsetUnset) |  | Удаляет свойство |
| public | [setArticles()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_setArticles) |  | Устанавливает articles. |
| public | [setCard()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_setCard) |  | Устанавливает данные банковской карты «Мир». |
| public | [setElectronicCertificate()](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md#method_setElectronicCertificate) |  | Устанавливает electronic_certificate. |
| public | [toArray()](../classes/YooKassa-Common-AbstractObject.md#method_toArray) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации Является алиасом метода AbstractObject::jsonSerialize() |
| protected | [getUnknownProperties()](../classes/YooKassa-Common-AbstractObject.md#method_getUnknownProperties) |  | Возвращает массив свойств которые не существуют, но были заданы у объекта |
| protected | [setType()](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md#method_setType) |  | Устанавливает тип метода оплаты |

---
### Details
* File: [lib/Model/PaymentData/PaymentDataElectronicCertificate.php](../../lib/Model/PaymentData/PaymentDataElectronicCertificate.php)
* Package: Default
* Class Hierarchy:  
  * [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)
  * [\YooKassa\Model\PaymentData\AbstractPaymentData](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md)
  * \YooKassa\Model\PaymentData\PaymentDataElectronicCertificate

---
## Properties
<a name="property_articles"></a>
#### public $articles : \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null
---
***Description***

Корзина покупки (в терминах НСПК) — список товаров, которые можно оплатить по сертификату.  Необходимо передавать только при [оплате на готовой странице ЮKassa](/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/ready-made-payment-form).

**Type:** <a href="../\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null"><abbr title="\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null">ElectronicCertificateArticle[]|null</abbr></a>

**Details:**


<a name="property_card"></a>
#### public $card : \YooKassa\Model\PaymentData\PaymentDataBankCardCard|null
---
***Description***

Данные банковской карты «Мир».

**Type:** <a href="../\YooKassa\Model\PaymentData\PaymentDataBankCardCard|null"><abbr title="\YooKassa\Model\PaymentData\PaymentDataBankCardCard|null">PaymentDataBankCardCard|null</abbr></a>

**Details:**


<a name="property_electronic_certificate"></a>
#### public $electronic_certificate : \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null
---
***Description***

Данные от ФЭС НСПК для оплаты по электронному сертификату.

**Type:** <a href="../\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null"><abbr title="\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null">ElectronicCertificatePaymentData|null</abbr></a>

**Details:**


<a name="property_electronicCertificate"></a>
#### public $electronicCertificate : \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null
---
***Description***

Данные от ФЭС НСПК для оплаты по электронному сертификату.

**Type:** <a href="../\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null"><abbr title="\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null">ElectronicCertificatePaymentData|null</abbr></a>

**Details:**


<a name="property_type"></a>
#### public $type : string
---
***Description***

Код способа оплаты.

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

**Details:**


<a name="property_type"></a>
#### public $type : string
---
***Description***

Тип метода оплаты

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\AbstractPaymentData](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md)



---
## Methods
<a name="method___construct" class="anchor"></a>
#### public __construct() : mixed

```php
public __construct() : mixed
```

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

**Returns:** mixed - 


<a name="method___get" class="anchor"></a>
#### public __get() : mixed

```php
public __get(string $propertyName) : mixed
```

**Summary**

Возвращает значение свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя свойства |

**Returns:** mixed - Значение свойства


<a name="method___isset" class="anchor"></a>
#### public __isset() : bool

```php
public __isset(string $propertyName) : bool
```

**Summary**

Проверяет наличие свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя проверяемого свойства |

**Returns:** bool - True если свойство имеется, false если нет


<a name="method___set" class="anchor"></a>
#### public __set() : mixed

```php
public __set(string $propertyName, mixed $value) : mixed
```

**Summary**

Устанавливает значение свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя свойства |
| <code lang="php">mixed</code> | value  | Значение свойства |

**Returns:** mixed - 


<a name="method___unset" class="anchor"></a>
#### public __unset() : mixed

```php
public __unset(string $propertyName) : mixed
```

**Summary**

Удаляет свойство

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя удаляемого свойства |

**Returns:** mixed - 


<a name="method_fromArray" class="anchor"></a>
#### public fromArray() : mixed

```php
public fromArray(array|\Traversable $sourceArray) : mixed
```

**Summary**

Устанавливает значения свойств текущего объекта из массива

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array OR \Traversable</code> | sourceArray  | Ассоциативный массив с настройками |

**Returns:** mixed - 


<a name="method_getArticles" class="anchor"></a>
#### public getArticles() : \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null

```php
public getArticles() : \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null
```

**Summary**

Возвращает articles.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

**Returns:** \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null - Корзина покупки (в терминах НСПК) — список товаров


<a name="method_getCard" class="anchor"></a>
#### public getCard() : \YooKassa\Model\PaymentData\PaymentDataBankCardCard

```php
public getCard() : \YooKassa\Model\PaymentData\PaymentDataBankCardCard
```

**Summary**

Возвращает данные банковской карты «Мир».

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

**Returns:** \YooKassa\Model\PaymentData\PaymentDataBankCardCard - Данные банковской карты


<a name="method_getElectronicCertificate" class="anchor"></a>
#### public getElectronicCertificate() : \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null

```php
public getElectronicCertificate() : \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null
```

**Summary**

Возвращает electronic_certificate.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

**Returns:** \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|null - Данные от ФЭС НСПК для оплаты по электронному сертификату


<a name="method_getType" class="anchor"></a>
#### public getType() : string

```php
public getType() : string
```

**Summary**

Возвращает тип метода оплаты

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\AbstractPaymentData](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md)

**Returns:** string - Тип метода оплаты


<a name="method_jsonSerialize" class="anchor"></a>
#### public jsonSerialize() : array

```php
public jsonSerialize() : array
```

**Summary**

Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив со свойствами текущего объекта


<a name="method_offsetExists" class="anchor"></a>
#### public offsetExists() : bool

```php
public offsetExists(string $offset) : bool
```

**Summary**

Проверяет наличие свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя проверяемого свойства |

**Returns:** bool - True если свойство имеется, false если нет


<a name="method_offsetGet" class="anchor"></a>
#### public offsetGet() : mixed

```php
public offsetGet(string $offset) : mixed
```

**Summary**

Возвращает значение свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя свойства |

**Returns:** mixed - Значение свойства


<a name="method_offsetSet" class="anchor"></a>
#### public offsetSet() : void

```php
public offsetSet(string $offset, mixed $value) : void
```

**Summary**

Устанавливает значение свойства

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя свойства |
| <code lang="php">mixed</code> | value  | Значение свойства |

**Returns:** void - 


<a name="method_offsetUnset" class="anchor"></a>
#### public offsetUnset() : void

```php
public offsetUnset(string $offset) : void
```

**Summary**

Удаляет свойство

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя удаляемого свойства |

**Returns:** void - 


<a name="method_setArticles" class="anchor"></a>
#### public setArticles() : self

```php
public setArticles(array|\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[]|null $value = null) : self
```

**Summary**

Устанавливает articles.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array OR \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle[] OR null</code> | value  | Корзина покупки (в терминах НСПК) — список товаров |

**Returns:** self - 


<a name="method_setCard" class="anchor"></a>
#### public setCard() : mixed

```php
public setCard(\YooKassa\Model\PaymentData\PaymentDataBankCardCard|array $value = null) : mixed
```

**Summary**

Устанавливает данные банковской карты «Мир».

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\YooKassa\Model\PaymentData\PaymentDataBankCardCard OR array</code> | value  | Данные банковской карты |

**Returns:** mixed - 


<a name="method_setElectronicCertificate" class="anchor"></a>
#### public setElectronicCertificate() : self

```php
public setElectronicCertificate(\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData|array|null $value = null) : self
```

**Summary**

Устанавливает electronic_certificate.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\PaymentDataElectronicCertificate](../classes/YooKassa-Model-PaymentData-PaymentDataElectronicCertificate.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificatePaymentData OR array OR null</code> | value  | Данные от ФЭС НСПК для оплаты по электронному сертификату. |

**Returns:** self - 


<a name="method_toArray" class="anchor"></a>
#### public toArray() : array

```php
public toArray() : array
```

**Summary**

Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации
Является алиасом метода AbstractObject::jsonSerialize()

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив со свойствами текущего объекта


<a name="method_getUnknownProperties" class="anchor"></a>
#### protected getUnknownProperties() : array

```php
protected getUnknownProperties() : array
```

**Summary**

Возвращает массив свойств которые не существуют, но были заданы у объекта

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив с не существующими у текущего объекта свойствами


<a name="method_setType" class="anchor"></a>
#### protected setType() : mixed

```php
protected setType(string $value) : mixed
```

**Summary**

Устанавливает тип метода оплаты

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\AbstractPaymentData](../classes/YooKassa-Model-PaymentData-AbstractPaymentData.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | value  | Тип метода оплаты |

**Returns:** mixed - 



---

### Top Namespaces

* [\YooKassa](../namespaces/yookassa.md)

---

### Reports
* [Errors - 0](../reports/errors.md)
* [Markers - 0](../reports/markers.md)
* [Deprecated - 43](../reports/deprecated.md)

---

This document was automatically generated from source code comments on 2026-02-18 using [phpDocumentor](http://www.phpdoc.org/)

&copy; 2026 YooMoney