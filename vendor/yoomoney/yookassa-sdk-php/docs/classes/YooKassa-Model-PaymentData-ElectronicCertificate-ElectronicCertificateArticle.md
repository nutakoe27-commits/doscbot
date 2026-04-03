# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle
### Namespace: [\YooKassa\Model\PaymentData\ElectronicCertificate](../namespaces/yookassa-model-paymentdata-electroniccertificate.md)
---
**Summary:**

Класс, представляющий модель ElectronicCertificateArticle.

**Description:**

Товарная позиция в корзине покупки при оплате по электронному сертификату.

---
### Constants
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [ARTICLE_NUMBER_MIN_VALUE](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#constant_ARTICLE_NUMBER_MIN_VALUE) |  |  |
| public | [ARTICLE_NUMBER_MAX_VALUE](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#constant_ARTICLE_NUMBER_MAX_VALUE) |  |  |
| public | [TRU_CODE_LENGTH](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#constant_TRU_CODE_LENGTH) |  |  |
| public | [ARTICLE_CODE_MAX_LENGTH](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#constant_ARTICLE_CODE_MAX_LENGTH) |  |  |
| public | [ARTICLE_NAME_MAX_LENGTH](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#constant_ARTICLE_NAME_MAX_LENGTH) |  |  |

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [$article_code](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_article_code) |  | Код товара в вашей системе. Максимум 128 символов. |
| public | [$article_name](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_article_name) |  | Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов. |
| public | [$article_number](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_article_number) |  | Порядковый номер товара в корзине. От 1 до 999 включительно. |
| public | [$articleCode](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_articleCode) |  | Код товара в вашей системе. Максимум 128 символов. |
| public | [$articleName](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_articleName) |  | Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов. |
| public | [$articleNumber](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_articleNumber) |  | Порядковый номер товара в корзине. От 1 до 999 включительно. |
| public | [$metadata](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_metadata) |  | Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8. |
| public | [$price](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_price) |  | Цена за единицу товара. |
| public | [$quantity](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_quantity) |  | Количество единиц товара. Формат: целое положительное число. |
| public | [$tru_code](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_tru_code) |  | Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code) |
| public | [$truCode](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#property_truCode) |  | Код ТРУ. 30 символов, две группы цифр, разделенные точкой. Формат: ~`NNNNNNNNN.NNNNNNNNNYYYYMMMMZZZ`, где ~`NNNNNNNNN.NNNNNNNNN` — код вида ТРУ по [Перечню ТРУ](https://esnsi.gosuslugi.ru/classifiers/10616/data?pg=1&p=1), ~`YYYY` — код производителя, ~`MMMM` — код модели, ~`ZZZ` — код страны производителя. Пример: ~`329921120.06001010200080001643`  [Как сформировать код ТРУ](https://yookassa.ru/developers/payment-acceptance/integration-scenarios/manual-integration/other/electronic-certificate/basics#payments-preparations-tru-code) |

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
| public | [getArticleCode()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getArticleCode) |  | Возвращает article_code. |
| public | [getArticleName()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getArticleName) |  | Возвращает article_name. |
| public | [getArticleNumber()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getArticleNumber) |  | Возвращает article_number. |
| public | [getMetadata()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getMetadata) |  | Возвращает metadata. |
| public | [getPrice()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getPrice) |  | Возвращает price. |
| public | [getQuantity()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getQuantity) |  | Возвращает quantity. |
| public | [getTruCode()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_getTruCode) |  | Возвращает tru_code. |
| public | [jsonSerialize()](../classes/YooKassa-Common-AbstractObject.md#method_jsonSerialize) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации |
| public | [offsetExists()](../classes/YooKassa-Common-AbstractObject.md#method_offsetExists) |  | Проверяет наличие свойства |
| public | [offsetGet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetGet) |  | Возвращает значение свойства |
| public | [offsetSet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetSet) |  | Устанавливает значение свойства |
| public | [offsetUnset()](../classes/YooKassa-Common-AbstractObject.md#method_offsetUnset) |  | Удаляет свойство |
| public | [setArticleCode()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setArticleCode) |  | Устанавливает article_code. |
| public | [setArticleName()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setArticleName) |  | Устанавливает article_name. |
| public | [setArticleNumber()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setArticleNumber) |  | Устанавливает article_number. |
| public | [setMetadata()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setMetadata) |  | Устанавливает metadata. |
| public | [setPrice()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setPrice) |  | Устанавливает price |
| public | [setQuantity()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setQuantity) |  | Устанавливает quantity. |
| public | [setTruCode()](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md#method_setTruCode) |  | Устанавливает tru_code. |
| public | [toArray()](../classes/YooKassa-Common-AbstractObject.md#method_toArray) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации Является алиасом метода AbstractObject::jsonSerialize() |
| protected | [getUnknownProperties()](../classes/YooKassa-Common-AbstractObject.md#method_getUnknownProperties) |  | Возвращает массив свойств которые не существуют, но были заданы у объекта |

---
### Details
* File: [lib/Model/PaymentData/ElectronicCertificate/ElectronicCertificateArticle.php](../../lib/Model/PaymentData/ElectronicCertificate/ElectronicCertificateArticle.php)
* Package: Default
* Class Hierarchy: 
  * [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)
  * \YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle

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


<a name="constant_ARTICLE_NAME_MAX_LENGTH" class="anchor"></a>
###### ARTICLE_NAME_MAX_LENGTH
```php
ARTICLE_NAME_MAX_LENGTH = 128 : int
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


<a name="property_article_name"></a>
#### public $article_name : string
---
***Description***

Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

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


<a name="property_articleName"></a>
#### public $articleName : string
---
***Description***

Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов.

**Type:** <a href="../string"><abbr title="string">string</abbr></a>

**Details:**


<a name="property_articleNumber"></a>
#### public $articleNumber : int
---
***Description***

Порядковый номер товара в корзине. От 1 до 999 включительно.

**Type:** <a href="../int"><abbr title="int">int</abbr></a>

**Details:**


<a name="property_metadata"></a>
#### public $metadata : \YooKassa\Model\Metadata|null
---
***Description***

Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8.

**Type:** <a href="../\YooKassa\Model\Metadata|null"><abbr title="\YooKassa\Model\Metadata|null">Metadata|null</abbr></a>

**Details:**


<a name="property_price"></a>
#### public $price : \YooKassa\Model\AmountInterface
---
***Description***

Цена за единицу товара.

**Type:** <a href="../classes/YooKassa-Model-AmountInterface.html"><abbr title="\YooKassa\Model\AmountInterface">AmountInterface</abbr></a>

**Details:**


<a name="property_quantity"></a>
#### public $quantity : int
---
***Description***

Количество единиц товара. Формат: целое положительное число.

**Type:** <a href="../int"><abbr title="int">int</abbr></a>

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
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** string|null - Код товара в вашей системе


<a name="method_getArticleName" class="anchor"></a>
#### public getArticleName() : string

```php
public getArticleName() : string
```

**Summary**

Возвращает article_name.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** string - Название товара в вашей системе


<a name="method_getArticleNumber" class="anchor"></a>
#### public getArticleNumber() : int

```php
public getArticleNumber() : int
```

**Summary**

Возвращает article_number.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** int - Порядковый номер товара в корзине


<a name="method_getMetadata" class="anchor"></a>
#### public getMetadata() : \YooKassa\Model\Metadata|null

```php
public getMetadata() : \YooKassa\Model\Metadata|null
```

**Summary**

Возвращает metadata.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** \YooKassa\Model\Metadata|null - Любые дополнительные данные


<a name="method_getPrice" class="anchor"></a>
#### public getPrice() : \YooKassa\Model\AmountInterface

```php
public getPrice() : \YooKassa\Model\AmountInterface
```

**Summary**

Возвращает price.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** \YooKassa\Model\AmountInterface - Цена за единицу товара


<a name="method_getQuantity" class="anchor"></a>
#### public getQuantity() : int

```php
public getQuantity() : int
```

**Summary**

Возвращает quantity.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

**Returns:** int - Количество единиц товара


<a name="method_getTruCode" class="anchor"></a>
#### public getTruCode() : string

```php
public getTruCode() : string
```

**Summary**

Возвращает tru_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

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
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | value  | Код товара в вашей системе. Максимум 128 символов. |

**Returns:** self - 


<a name="method_setArticleName" class="anchor"></a>
#### public setArticleName() : self

```php
public setArticleName(string $value) : self
```

**Summary**

Устанавливает article_name.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | value  | Название товара в вашей системе. Отображается на готовой платежной форме ЮKassa. Максимум 128 символов. |

**Returns:** self - 


<a name="method_setArticleNumber" class="anchor"></a>
#### public setArticleNumber() : self

```php
public setArticleNumber(int $value) : self
```

**Summary**

Устанавливает article_number.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">int</code> | value  | Порядковый номер товара в корзине. От 1 до 999 включительно. |

**Returns:** self - 


<a name="method_setMetadata" class="anchor"></a>
#### public setMetadata() : self

```php
public setMetadata(\YooKassa\Model\Metadata|array|null $value) : self
```

**Summary**

Устанавливает metadata.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\YooKassa\Model\Metadata OR array OR null</code> | value  | Любые дополнительные данные, которые нужны вам для работы (например, ваш внутренний идентификатор заказа). Передаются в виде набора пар «ключ-значение» и возвращаются в ответе от ЮKassa. Ограничения: максимум 16 ключей, имя ключа не больше 32 символов, значение ключа не больше 512 символов, тип данных — строка в формате UTF-8. |

**Returns:** self - 


<a name="method_setPrice" class="anchor"></a>
#### public setPrice() : self

```php
public setPrice(\YooKassa\Model\AmountInterface|array $value) : self
```

**Summary**

Устанавливает price

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\YooKassa\Model\AmountInterface OR array</code> | value  | Цена товара |

**Returns:** self - 


<a name="method_setQuantity" class="anchor"></a>
#### public setQuantity() : self

```php
public setQuantity(int $value) : self
```

**Summary**

Устанавливает quantity.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">int</code> | value  | Количество единиц товара. Формат: целое положительное число. |

**Returns:** self - 


<a name="method_setTruCode" class="anchor"></a>
#### public setTruCode() : self

```php
public setTruCode(string $value) : self
```

**Summary**

Устанавливает tru_code.

**Details:**
* Inherited From: [\YooKassa\Model\PaymentData\ElectronicCertificate\ElectronicCertificateArticle](../classes/YooKassa-Model-PaymentData-ElectronicCertificate-ElectronicCertificateArticle.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | value  | Код ТРУ. 30 символов |

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