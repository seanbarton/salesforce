# Salesforce REST API Client

## Installation

Ensure you have [composer](http://getcomposer.org) installed, then run the following command:

    composer require seanbarton/salesforce

That will fetch the library and its dependencies inside your vendor folder.

## Requirements

-   [PHP 7.3+](https://www.php.net)
-   [Composer 2.0+](https://getcomposer.org)

## Features

-   OAuth with password grant type
-   Create, update, delete, upsert, and query records
-   Object representation of Salesforce records with seanbarton\Salesforce\Record
-   Field validation and object mapping
-   Extendable code: add custom objects or validation rules

## Setting up a Connected App

Before you begin, you need to have set up a "Connected App" in Salesforce and get a `consumerKey`, `consumerSecret`, `username`, and `password` to allow Api access.

_Note, this process may vary depending on changes to the Salesforce website, or on your Salesforce account and settings._

_Check [Salesforce's Help Docs](https://help.salesforce.com/s/articleView?id=sf.connected_app_create.htm) to verify._

1. Log into to your Salesforce org
2. Click on Setup in the upper right-hand menu
3. Under Build click Create → Apps
4. Scroll to the bottom and click "New" under Connected Apps.
5. Enter the following details for the remote application:
    - Connected App Name
    - API Name
    - Contact Email
    - Under the API dropdown, enable OAuth Settings
    - Callback URL
    - Select Access Scope (If you need a refresh token, specify it here)
6. Click Save, and store your access credentials in a safe place.

## Usage

Creating a new Api client and connecting:

```php
use seanbarton\Salesforce\Authenticator\Password;
use seanbarton\Salesforce\Client;

// Provide endpoint and any Guzzle options in Password::create(), endoint defaults to login.salesforce.com
$auth = Password::create(['endpoint' => 'https://test.salesforce.com/'])->authenticate([
    "client_id" => $YOUR_CONSUMER_KEY,
    "client_secret" => $YOUR_CONSUMER_SECRET,
    "username" => $YOUR_SALESFORCE_USERNAME,
    "password" => $YOUR_SALESFORCE_PASSWORD_AND_SECURITY_TOKEN
]);

// Here you may also provide object mappings and desired Salesforce API version
$salesforce = new Client($auth, [], 59);
```

The following examples use a Salesforce object named `Example` that has a field `Name`.

Executing Basic SOQL Queries:

```php
$select = "SELECT Id, Name FROM Example LIMIT 100";
foreach ($salesforce->query($select) as $object) {
    echo "Example {$object->Id} ({$object->Name})\n";
    // outputs something like "Example 5003000000D8cuIQAA (Bob)"
}
```

If you need to use php values in your query, put `{tokens}` in your SOQL and pass the values separately via `query()`'s second argument. The values will be properly quoted and escaped based on their type, and interpolated into the query:

```php
$select = "SELECT Id, Name FROM Example WHERE Name={name} LIMIT 100";
$name = 'Bob';
foreach ($salesforce->query($select, ['name' => $name]) as $object) {
    echo "Example {$object->Id} ({$object->Name})\n";
    // outputs something like "Example 5003000000D8cuIQAA (Bob)"
}
```

Fetching a Record by Id:

```php
$id = "5003000000D8cuIQAA";
$bob = $salesforce->get("Example", $id);
echo "Hello, {$bob->Name}\n";
// outputs something like "Hello, Bob"
```

Creating a new Record:

```php
use seanbarton\Salesforce\Record;

$linda = $salesforce->create(new Record("Example", ["Name" => "Linda"]));
echo "Example {$linda->Id} ({$linda->Name})";
// outputs something like "Example 5003000000D8cuIQAA (Linda)"
```

Updating an existing Record:

```php
$bob->Name = "Roberto";
$roberto = $salesforce->update($bob);
echo "Hello, {$roberto->Name}\n";
// outputs "Hello, Roberto"
```

Deleting a Record:

```php
$ded = $salesforce->delete($bob);
var_dump($ded->Id);
// outputs "NULL"
```

## Advanced Usage

### Extending the Record class

The included `seanbarton\Salesforce\Record` class can be used without modification as a generic "salesforce record" implementation - it will automatically set properties based on what's fetched from the Api. However, the intent is that applications will extend from it and define the properties needed for each of their Salesforce objects. This allows for a consistent schema that your code can rely on, and even lets you implement some level of validation directly in your application.

To build your own Salesforce Object, you must:

-   extend from `seanbarton\Salesforce\Record`
-   define the object fields as public properties
-   list any properties that must not be included in update() calls (e.g., renamed fields, nested objects or object lists) in UNEDITABLE_FIELDS.
-   add any necessary logic in `setField()` (e.g., building a new object if your record has a relation)
-   add any desired logic in `validateField()`

Using our "Example" object from above,

```php
use seanbarton\Salesforce\Record;

class Example extends Record
{
    public const TYPE = "Example";

    public ?string $Name = null;
}
```

### Inline Records and Record Lists

SOQL allows queries for nested objects and queries, which appear in results as inline records and query results respectively. This library does understand results from such queries, but your Record subclasses must define properties in a particular way to support them.

For example, a query similar to `SELECT Manager.Id, Manager.Name, (SELECT Id, Name FROM Members) FROM Teams` would require a Record class like so:

```php
use seanbarton\Salesforce\Record;
use seanbarton\Salesforce\Result;
use Example;

class Team extends Record
{
    public const TYPE = "Team";

    protected const UNEDITABLE_FIELDS = [
        "Manager",
        "Members",
        ...parent::UNEDITABLE_FIELDS
    ];

    public ?Example $Manager = null;
    public ?Result $Members = null;
}
```

Where there is an inline object, the property should be typed as the corresponding Record subclass. For any record type where you're not making a Record subclass, type the property as `Record` — though this is obviously less useful.

Where there is a subquery, the property should be typed as a `Result` instance. Among other things, this allows the Result to support paginated subqueries.

### Object Mapping

Finally, to allow the Api Client take advantage of your subclasses, you must provide an `$objectMap` so it knows which PHP classes correspond to which Salesforce record types. Without this, you'll end up with generic Record instances for everything. Nested Results will be provided the same `$objectMap` as their parent Result instance.

```php
$salesforce = new Client(
    $password->authenticate([...$credentials]),
    [Example::TYPE => Example::class, Team::TYPE => Team::class]
);
```

### Field Validation

Some basic validation functions are included in `seanbarton\Salesforce\Validator`. These methods all take the value to validate as the first argument, and can have other arguments depending on needs. Follow this same pattern to implement additional validation functions for your own objects as needed.

```php
use seanbarton\Salesforce\Record;
use seanbarton\Salesforce\Validator;

class Example extends Record
{
    public ?string $Name = null;

    protected function validateField(string $field) : void {
        switch ($field) {
            case "Name":
                Validator::characterLength($this->Name, 2, 100);
                return;
            default:
                parent::validateField($field);
                return;
        }
    }
}
```

### Handling Errors

All Runtime Exceptions thrown from this library will be an instance of `seanbarton\Salesforce\Error`.

Exceptions are grouped into the following types:

-   `seanbarton\Salesforce\Exceptions\SalesforceException`:

    Errors originating from the Salesforce API, including HTTP errors (e.g., connection timeouts)

-   `seanbarton\Salesforce\Exceptions\AuthException`:

    Authentication failures or attempts to use the HttpClient before authentication has succeeded

-   `seanbarton\Salesforce\Exceptions\ResultException`:

    Errors parsing or handling Salesforce Api results or records; these will usually indicate a problem in your custom Record classes

-   `seanbarton\Salesforce\Exceptions\UsageException`:

    Errors arising from incorrect library usage; these will usually indicate a runtime problem in your application code

-   `seanbarton\Salesforce\Exceptions\ValidationException`:

    Validation errors.

## Running for development with Docker

We have included a Dockerfile to make it easy to run the tests and debug the code. You must have Docker installed. The following commands will build the image and run the container:

1. `docker build -t seanbarton/salesforce --build-arg PHP_VERSION=8 .`
2. `docker run -it --rm -v ${PWD}:/var/www/sapi seanbarton/salesforce sh`

## Debugging with XDebug in VSCode

Docker image is configured with XDebug. To debug the code with VSCode, follow these steps:

1.  Install the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) in VSCode
2.  Add a new PHP Debug configuration in VSCode:

        {
            "name": "XDebug Docker",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/sapi/": "${workspaceRoot}/"
            }
        }

3.  `docker run -it --rm -v ${PWD}:/var/www/sapi --add-host host.docker.internal:host-gateway seanbarton/salesforce sh`
4.  Start debugging in VSCode with the 'XDebug Docker' configuration.

## Testing

This library ships with PHPUnit for development. Composer file has been configured with some scripts, run the following command to run the tests:

    composer test
