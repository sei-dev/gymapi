# NetRačuni PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wdev-rs/netracuni-php.svg?style=flat-square)](https://packagist.org/packages/wdev-rs/netracuni-php)
[![Total Downloads](https://img.shields.io/packagist/dt/wdev-rs/netracuni-php.svg?style=flat-square)](https://packagist.org/packages/wdev-rs/netracuni-php)
![Tests](https://github.com/wdev-rs/netracuni-php/actions/workflows/main.yml/badge.svg)

The NetRačuni PHP sdk makes it easy to use the NetRačuni API from any PHP based webshop. 
It supports all the features from the API, easy to use and compatible with PHP version >= 7.4

## Installation

You can install the package via composer:

```bash
composer require wdev-rs/netracuni-php
```

## Dependencies

The NetRačuni client depends on the Guzzle library. If you use composer it will be installed automatically.

## Usage

To use the API, you need to create a token in your NetRačuni account. Please consult the [documentation](https://netracuni.com/register-help/sr#28) how to
do it.

When you have the token, you can start the integration by creating an instance of the NetRačuni client: 

```php
$netRacuni = new WdevRs\NetRacuniPhp\NetRacuniClient('net_racuni_staging_************************************************');
```

### Using sandbox or production

During development and testing you can use the sandbox environment. 
The client automatically recognises the token and sets up the correct endpoint urls.
If you want to make sure you are using the correct environment use the following methods:

Switch to sandbox:
```php
$netRacuni->sandbox();
```

Switch to production
```php
$netRacuni->production();
```

### Check the settings

Using the `ping` method you can check the connection with the NetRačuni API and 
also check if the settings in your account are correct.

```php
$netRacuni->ping()
```

The method returns boolean, or throws an error when something went wrong.

### Get the tax labels

You can get the actual tax labels from the API, which returns the actual tax labels from the server of the tax authority.

```php
$netRacuni->getTaxLabels()
```

The method returns an array with the tax labels, or throws an exception if an error occurred.

Example response from the sandbox:
```json
[
  {
    "order_id": 1,
    "name": "ECAL",
    "category_type": 0,
    "label": "F",
    "rate": 11
  },
  {
    "order_id": 2,
    "name": "N-TAX",
    "category_type": 0,
    "label": "N",
    "rate": 0
  },
  {
    "order_id": 3,
    "name": "PBL",
    "category_type": 2,
    "label": "P",
    "rate": 0.5
  },
  {
    "order_id": 4,
    "name": "STT",
    "category_type": 0,
    "label": "E",
    "rate": 6
  },
  {
    "order_id": 5,
    "name": "TOTL",
    "category_type": 1,
    "label": "T",
    "rate": 2
  },
  {
    "order_id": 6,
    "name": "VAT",
    "category_type": 0,
    "label": "A",
    "rate": 10
  },
  {
    "order_id": 6,
    "name": "VAT",
    "category_type": 0,
    "label": "B",
    "rate": 0
  },
  {
    "order_id": 6,
    "name": "VAT",
    "category_type": 0,
    "label": "\u0416",
    "rate": 19
  },
  {
    "order_id": 7,
    "name": "VAT-EXCL",
    "category_type": 0,
    "label": "C",
    "rate": 0
  }
]
```

To create and invoice with the NetRačuni client use the `createInvoice` method. 
Please note that by using the API you can only create sale invoice (promet prodaja).
The argument is an array which defines the products what you will have on the invoice.

**IMPORTANT**: The taxLabels needs to be an array, and the letters have to correspond to the labels received from the
`getTaxLabels` method. In production all the tax labels **MUST** use cyrillic letters, even if some letters look the same
on cyrillic and latin these are not the same (for example tax label "A").

```php

$items = [
    "items" => [
        [
            "name" => "Test Item",
            "taxLabels" => [
                "A"
            ],
            "unit" => "KOM",
            "quantity" => 2,
            "price" => 152.66
        ]
    ]
];

$result = $netRacuni->createInvoice($items);
```

The result is an instance of the `WdevRs\NetRacuniPhp\InvoiceResponse`

To get the invoice in pdf format use the `getInvoicePdfUrl` method. It returns the url of the pdf what you can download or open in a new browser window.

```php
$invoiceUrl = $result->getInvoicePdfUrl();
```

To retrieve all the invoice data received from the server of the tax authority use
the `getInvoice` method.

```php
$invoice = $result->getInvoice();
```

While it is not mandatory we highly encourage you to store both the invoice data 
and the pdf url, and connect it to the order in your webshop.

### Error handling

The NetRačuni Client transform the errors received from the API to PHP exceptions.
You can simply catch the exception, and the exception message will tell you what 
the actual problem is. For more details about the possible error messages, please check the [documentation](https://netracuni.com/register-help/sr#28).

```php
try {
    $result = $netRacuni->createInvoice($items);
} catch(\Exception $e) {
    Log::error($e->getMessage())
    // Example  Company no uploaded certificate, please upload one in https://netracuni.com/teams/ site
}
```

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email daniel@wdev.rs instead of using the issue tracker.

## Credits

-   [Daniel Werner](https://github.com/wdev-rs)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## PHP Package Boilerplate

This package was generated using the [PHP Package Boilerplate](https://laravelpackageboilerplate.com) by [Beyond Code](http://beyondco.de/).
