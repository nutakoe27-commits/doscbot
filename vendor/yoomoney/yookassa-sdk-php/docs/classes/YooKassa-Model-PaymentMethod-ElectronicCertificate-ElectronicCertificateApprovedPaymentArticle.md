# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle
### Namespace: [\YooKassa\Model\PaymentMethod\ElectronicCertificate](../namespaces/yookassa-model-paymentmethod-electroniccertificate.md)
---
**Summary:**

Класс, представляющий модель ElectronicCertificateApprovedPaymentArticle.

**Description:**

Товарная позиция в одобренной корзине покупки при оплате по электронному сертификату.

---
### Constants
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [ARTICLE_NUMBER_MIN_VALUE](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#constant_ARTICLE_NUMBER_MIN_VALUE) |  |  |
| public | [ARTICLE_NUMBER_MAX_VALUE](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#constant_ARTICLE_NUMBER_MAX_VALUE) |  |  |
| public | [TRU_CODE_LENGTH](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#constant_TRU_CODE_LENGTH) |  |  |
| public | [ARTICLE_CODE_MAX_LENGTH](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#constant_ARTICLE_CODE_MAX_LENGTH) |  |  |

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [$article_code](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_article_code) |  | Код товара в вашей системе. Максимум 128 символов. |
| public | [$article_number](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_article_number) |  | Порядковый номер товара в корзине. От 1 до 999 включительно. |
| public | [$articleCode](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_articleCode) |  | Код товара в вашей системе. Максимум 128 символов. |
| public | [$articleNumber](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_articleNumber) |  | Порядковый номер товара в корзине. От 1 до 999 включительно. |
| public | [$certificates](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_certificates) |  | Список электронных сертификатов, которые используются для оплаты покупки. |
| public | [$tru_code](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_tru_code) |  | Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code) |
| public | [$truCode](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#property_truCode) |  | Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code) |

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [__construct()](../classes/YooKassa-Common-AbstractObject.md#method___construct) |  | AbstractObject constructor. |
| public | [__get()](../classes/YooKassa-Common-AbstractObject.md#method___get) |  | Возвращает значение свойства |
| public | [__isset()](../classes/YooKassa-Common-AbstractObject.md#method___isset) |  | Проверяет наличие свойства |
| public | [__set()](../classes/YooKassa-Common-AbstractObject.md#method___set) |  | Устанавливает значение свойства |
| public | [__unset()](../classes/YooKassa-Common-AbstractObject.md#method___unset) |  | Удаляет свойство |
| public | [fromArray()](../classes/YooKassa-Common-AbstractObject.md#method_fromArray) |  | Устанавливает значения свойств текущего объекта из массива |
| public | [getArticleCode()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_getArticleCode) |  | Возвращает article_code. |
| public | [getArticleNumber()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_getArticleNumber) |  | Возвращает article_number. |
| public | [getCertificates()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_getCertificates) |  | Возвращает certificates. |
| public | [getTruCode()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_getTruCode) |  | Возвращает tru_code. |
| public | [jsonSerialize()](../classes/YooKassa-Common-AbstractObject.md#method_jsonSerialize) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации |
| public | [offsetExists()](../classes/YooKassa-Common-AbstractObject.md#method_offsetExists) |  | Проверяет наличие свойства |
| public | [offsetGet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetGet) |  | Возвращает значение свойства |
| public | [offsetSet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetSet) |  | Устанавливает значение свойства |
| public | [offsetUnset()](../classes/YooKassa-Common-AbstractObject.md#method_offsetUnset) |  | Удаляет свойство |
| public | [setArticleCode()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_setArticleCode) |  | Устанавливает article_code. |
| public | [setArticleNumber()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_setArticleNumber) |  | Устанавливает article_number. |
| public | [setCertificates()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_setCertificates) |  | Устанавливает certificates. |
| public | [setTruCode()](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md#method_setTruCode) |  | Устанавливает tru_code. |
| public | [toArray()](../classes/YooKassa-Common-AbstractObject.md#method_toArray) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации Является алиасом метода AbstractObject::jsonSerialize() |
| protected | [getUnknownProperties()](../classes/YooKassa-Common-AbstractObject.md#method_getUnknownProperties) |  | Возвращает массив свойств которые не существуют, но были заданы у объекта |

---
### Details
* File: [lib/Model/PaymentMethod/ElectronicCertificate/ElectronicCertificateApprovedPaymentArticle.php](../../lib/Model/PaymentMethod/ElectronicCertificate/ElectronicCertificateApprovedPaymentArticle.php)
* Package: Default
* Class Hierarchy: 
  * [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)
  * \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle

---
## Constants
<a name="constant_ARTICLE_NUMBER_MIN_VALUE" class="anchor"></a>
###### ARTICLE_NUMBER_MIN_VALUE
```php
ARTICLE_NUMBER_MIN_VALUE = 1 : int
```


<a name="constant_ARTICLE_NUMBER_MAX_VALUE" class="anchor"></a>
###### ARTICLE_NUMBER_MAX_VALUE
```php
ARTICLE_NUMBER_MAX_VALUE = 999 : int
```


<a name="constant_TRU_CODE_LENGTH" class="anchor"></a>
###### TRU_CODE_LENGTH
```php
TRU_CODE_LENGTH = 30 : int
```


<a name="constant_ARTICLE_CODE_MAX_LENGTH" class="anchor"></a>
###### ARTICLE_CODE_MAX_LENGTH
```php
ARTICLE_CODE_MAX_LENGTH = 128 : int
```



---
## Properties
<a name="property_article_code"></a>
#### public $article_code : string|null
---
***Description***

Код товара в вашей системе. Максимум 128 символов.

**Type:** <a href="../string|null"><abbr title="string|null">string|null</abbr></a>

**Details:**


<a name="property_article_number"></a>
#### public $article_number : int
---
***Description***

Порядковый номер товара в корзине. От 1 до 999 включительно.

**Type:** <a href="../int"><abbr title="int">int</abbr></a>

**Details:**


<a name="property_articleCode"></a>
#### public $articleCode : string|null
---
***Description***

Код товара в вашей системе. Максимум 128 символов.

**Type:** <a href="../string|null"><abbr title="string|null">string|null</abbr></a>

**Details:**


<a name="property_articleNumber"></a>
#### public $articleNumber : int
---
***Description***

Порядковый номер товара в корзине. От 1 до 999 включительно.

**Type:** <a href="../int"><abbr title="int">int</abbr></a>

**Details:**


<a name="property_certificates"></a>
#### public $certificates : \YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate[]
---
***Description***

Список электронных сертификатов, которые используются для оплаты покупки.

**Type:** <a href="../\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate[]"><abbr title="\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificate[]">ElectronicCertificate[]</abbr></a>

**Details:**


<a name="property_tru_code"></a>
#### public $tru_code : string
---
***Description***

Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

**Details:**


<a name="property_truCode"></a>
#### public $truCode : string
---
***Description***

Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code)

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

**Details:**



---
## Methods
<a name="method___construct" class="anchor"></a>
#### public __construct() : mixed

```php
public __construct(array $data = array()) : mixed
```

**Summary**

AbstractObject constructor.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array</code> | data  |  |

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


<a name="method_getArticleCode" class="anchor"></a>
#### public getArticleCode() : string|null

```php
public getArticleCode() : string|null
```

**Summary**

Возвращает article_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

**Returns:** string|null - Код товара в вашей системе


<a name="method_getArticleNumber" class="anchor"></a>
#### public getArticleNumber() : int

```php
public getArticleNumber() : int
```

**Summary**

Возвращает article_number.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

**Returns:** int - Порядковый номер товара в корзине


<a name="method_getCertificates" class="anchor"></a>
#### public getCertificates() : array

```php
public getCertificates() : array
```

**Summary**

Возвращает certificates.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

**Returns:** array - Список электронных сертификатов


<a name="method_getTruCode" class="anchor"></a>
#### public getTruCode() : string

```php
public getTruCode() : string
```

**Summary**

Возвращает tru_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

**Returns:** string - Код ТРУ


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


<a name="method_setArticleCode" class="anchor"></a>
#### public setArticleCode() : self

```php
public setArticleCode(string|null $value = null) : self
```

**Summary**

Устанавливает article_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | value  | Код товара в вашей системе. Максимум 128 символов. |

**Returns:** self - 


<a name="method_setArticleNumber" class="anchor"></a>
#### public setArticleNumber() : self

```php
public setArticleNumber(int $value) : self
```

**Summary**

Устанавливает article_number.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">int</code> | value  | Порядковый номер товара в корзине. От 1 до 999 включительно. |

**Returns:** self - 


<a name="method_setCertificates" class="anchor"></a>
#### public setCertificates() : self

```php
public setCertificates(array $value = null) : self
```

**Summary**

Устанавливает certificates.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array</code> | value  | Список электронных сертификатов, которые используются для оплаты покупки. |

**Returns:** self - 


<a name="method_setTruCode" class="anchor"></a>
#### public setTruCode() : self

```php
public setTruCode(string|null $value) : self
```

**Summary**

Устанавливает tru_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentMethod\ElectronicCertificate\ElectronicCertificateApprovedPaymentArticle](../classes/YooKassa-Model-PaymentMethod-ElectronicCertificate-ElectronicCertificateApprovedPaymentArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | value  | Код ТРУ. 30 символов |

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