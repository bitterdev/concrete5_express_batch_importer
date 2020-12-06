# conrete5 Express Batch Importer

Concrete5 Express is great. No question. However, it soon reaches its limits when working with large amounts of data. Creating an entry lasts on average 700 ms on my development environment (processor I7 6800K + 16GB RAM). If you want to import a lot of data at the same time you get fast time-out error messages from PHP. 
Of course, one could simply say: Fine. Express is only suitable for smaller database structures. For everything else you can use Doctrine. However, the advantages of Express are so powerful that I have tried to develop an algorithm that manages to import large amounts of data into concrete5 Express data objects within a short time.

Et voila: With this algorithm it is possible to mass import records. I was able to reduce the time to 1.5ms per entry. Thus, it is possible to import "Big Data" (records beyond 10,000). I've developed it for a project of mine, but now I make it available to users of the developer world (MIT-licensed) .Enjoy!	

However, the algorithm has a few disadvantages:
- Because it does not use prepared statements, it is susceptible to multibyte SQL injections. That's why it can only be fed with single-byte data. You escape via addslashes ()
- There is no guarantee of upward compatibility with newer concrete5 versions. Please make a functionality test on a separate development environment before every update.
- This method only works with the MySQL database driver.
## Features

- Compatible with PHP 5.5 or greater.
- Easy to install with Composer.
- Compliant with PSR-1, PSR-2 and PSR-4.

## Requirements

- PHP 5.5 or greater with the following extensions:
    - cURL
- concrete5 8.0 or greater

## Installation

The Package can be installed with Composer. 

1. Install Composer.
```
curl -sS https://getcomposer.org/installer | php
```
2. Install the API.
```
php composer.phar require bitter/concrete5-express-batch-importer
```
3. Require Composer's autoloader by adding the following line to your code.
```
require 'vendor/autoload.php';
```

## Example Usageslo

### Example 1: Import Datasets

```php
// Create the Express Object
$student = Express::buildObject('student', 'students', 'Student');
$student->addAttribute('text', 'First Name', 'first_name');
$student->addAttribute('text', 'Last Name', 'last_name');
$student->addAttribute('textarea', 'Bio', 'bio');
$student->save();

// Import Test Data to the Express Object
$entries = [];

for ($i = 1; $i <= 100; $i++) {
    $entries[] = [
        "first_name" => "Lorem ipsum",
        "last_name" => "Lorem ipsum",
        "bio" => "Lorem ipsum lorem ipsum lorem ipsum lorem ipsum lorem"
    ];
}

$startTime = microtime(true) * 1000;

\Bitter\Concrete\Express\BatchImporter::batchImport("student", $entries);

$endTime = microtime(true) * 1000;

\Log::addEntry(t("Avg. Time: %sms / Entry", round(($endTime - $startTime) / count($entries), 2)));
```

Benchmark: 0.67ms / Entry


### Example 2: Import Datasets from a CSV file

```php
\Bitter\Concrete\Express\BatchImporter::batchImportCSV("student", "path_to/my_csv_file.csv");
```

Benchmark: 0.71ms / Entry

### Example 3: Import Datasets with special attribute types (like Address)

```php
// Create the Express Object
$student = Express::buildObject('student', 'students', 'Student');
$student->addAttribute('text', 'First Name', 'first_name');
$student->addAttribute('text', 'Last Name', 'last_name');
$student->addAttribute('textarea', 'Bio', 'bio');
$student->addAttribute('address', 'Address', 'address');
$student->save();

// Import Test Data to the Express Object
$entries = [];

for ($i = 1; $i <= 100; $i++) {
    $entries[] = [
        "first_name" => "Lorem ipsum",
        "last_name" => "Lorem ipsum",
        "bio" => "Lorem ipsum lorem ipsum lorem ipsum lorem ipsum lorem",
        "address" => [
            // List the attributes keys + values here of the attribute type (all attribute types are supported)
            'address1' => "Lorem ipsum",
            'address2' => "Lorem ipsum",
            'state_province' => "Lorem ipsum",
            'city' => "Lorem ipsum",
            'country' => 'DE',
            'postal_code' => "12345"
        ]
    ];
}

$startTime = microtime(true) * 1000;

\Bitter\Concrete\Express\BatchImporter::batchImport("student", $entries);

$endTime = microtime(true) * 1000;

\Log::addEntry(t("Avg. Time: %sms / Entry", round(($endTime - $startTime) / count($entries), 2)));
```

Benchmark: 1.6ms / Entry


### Example 4: Import Datasets with associations (One To One)

```php

// Create the First Express Object
$student = Express::buildObject('student', 'students', 'Student');
$student->addAttribute('text', 'First Name', 'first_name');
$student->addAttribute('text', 'Last Name', 'last_name');
$student->save();

// Create the Second Express Object
$teacher = Express::buildObject('teacher', 'teachers', 'Teacher');
$teacher->addAttribute('number', 'Id', 'id');
$teacher->addAttribute('text', 'First Name', 'first_name');
$teacher->addAttribute('text', 'Last Name', 'last_name');
$teacher->save();

// Build the Association
$student->buildAssociation()->addOneToOne(Express::getObjectByHandle("teacher"))->save();

// Import Test Data to the first Express Object
\Bitter\Concrete\Express\BatchImporter::batchImport("teacher", [
    [
        "id" => 1,
        "first_name" => "John",
        "last_name" => "Smith"
    ],

    [
        "id" => 2,
        "first_name" => "Rick",
        "last_name" => "Adams"
    ],

    [
        "id" => 3,
        "first_name" => "Marcus",
        "last_name" => "Crichton"
    ]
]);

// Import Test Data to the second Express Object (with Associations)
$entries = [];

for ($i = 1; $i <= 100; $i++) {
    $entries[] = [
        "first_name" => "Lorem ipsum",
        "last_name" => "Lorem ipsum",
        "teacher" => [
            ["id" => rand(1, 3)]
        ]
    ];
}

$startTime = microtime(true) * 1000;

\Bitter\Concrete\Express\BatchImporter::batchImport("student", $entries);

$endTime = microtime(true) * 1000;

\Log::addEntry(t("Avg. Time: %sms / Entry", round(($endTime - $startTime) / count($entries), 2)));
```

Benchmark: 1.08ms / Entry


### Example 4: Import Datasets with associations (One to Many)

```php

// Create the First Express Object
$student = Express::buildObject('student', 'students', 'Student');
$student->addAttribute('text', 'First Name', 'first_name');
$student->addAttribute('text', 'Last Name', 'last_name');
$student->save();

// Create the Second Express Object
$teacher = Express::buildObject('teacher', 'teachers', 'Teacher');
$teacher->addAttribute('number', 'Id', 'id');
$teacher->addAttribute('text', 'First Name', 'first_name');
$teacher->addAttribute('text', 'Last Name', 'last_name');
$teacher->save();

// Build the Association
$student->buildAssociation()->addManyToMany(Express::getObjectByHandle("teachers"))->save();

// Import Test Data to the first Express Object
\Bitter\Concrete\Express\BatchImporter::batchImport("teacher", [
    [
        "id" => 1,
        "first_name" => "John",
        "last_name" => "Smith"
    ],

    [
        "id" => 2,
        "first_name" => "Rick",
        "last_name" => "Adams"
    ],

    [
        "id" => 3,
        "first_name" => "Marcus",
        "last_name" => "Crichton"
    ]
]);

// Import Test Data to the second Express Object (with Associations)
$entries = [];

for ($i = 1; $i <= 100; $i++) {
    $entries[] = [
        "first_name" => "Lorem ipsum",
        "last_name" => "Lorem ipsum",
        "teacher" => [
            ["id" => 1],
            ["id" => 2],
            ["id" => 3]
        ]
    ];
}

$startTime = microtime(true) * 1000;

\MeinPlakat\Express\BatchImporter::batchImport("student", $entries);

$endTime = microtime(true) * 1000;

\Log::addEntry(t("Avg. Time: %sms / Entry", round(($endTime - $startTime) / count($entries), 2)));
```

Benchmark: 1.08ms / Entry

## License

Licensed under the MIT License

Copyright 2017 Fabian Bitter

## Support
You want to say thank you? Feel free to donate.

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/bitterfabian)