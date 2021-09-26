# Aerospike Module for Codeception

Integrates [Aerospike][1] into Codeception tests.

## Get Started

### Requirements

To use this module on your machine, you need at least:

- [Aerospike Server][1] >= 3.5.3
- [Aerospike PHP Extension][2]
- Latest [Codeception][3] release

### Installation

Create the `composer.json` file as follows:

```json
{
    "require-dev": {
        "codeception/codeception": "^2.2",
        "codeception/aerospike-module": "^1.0"
    }
}
```

Then install dependencies:

```sh
$ php composer.phar install
```

### Example (`unit.suite.yml`)

```yaml
modules:
    - Aerospike:
         addr: '127.0.0.1' # Aerospike host to connect
         port: 3000        # default Aerospike port
         set: 'cache'      # the Aerospike set to store data
         namespace: 'test' # the Aerospike namespace to store data
         reconnect: false  # whether the module should reconnect to the Aerospike before each test
         prefix: 'prefix_' # the key prefix
```

Then regenerate base class for unit suite

```sh
$ vendor/bin/codecept build
```

## Usage

Grabs value from Aerospike by key:

```php
$users_count = $I->grabValueFromAerospike('users_count');
```

Checks item in Aerospike exists and the same as expected:
```php
$I->seeInAerospike('key');
$I->seeInAerospike('key', 'value');
```

Checks item in Aerospike does not exist or is the same as expected:
```php
$I->dontSeeInAerospike('key');
$I->dontSeeInAerospike('key', 'value');
```

Inserts data into Aerospike database:
```php
$I->haveInAerospike('users', ['name' => 'miles', 'email' => 'miles@davis.com']);
```

## Changelog

[Changelog](https://github.com/Codeception/Aerospike-module/releases)

## License

Aerospike Module is open-sourced software licensed under the [MIT][4] License.

© 2015-2021 Codeception Team and contributors

[1]: http://www.aerospike.com/
[2]: http://www.aerospike.com/docs/client/php/install/
[3]: https://github.com/Codeception/Codeception
[4]: https://github.com/Codeception/Aerospike-module/blob/master/LICENSE.md
