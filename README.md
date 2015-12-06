# Aerospike Module for Codeception

Integrates [Aerospike][1] into Codeception tests.

## Get Started

### Requirements

To use this module on your machine, you need at least:

- [Aerospike Server][1] >= 3.5.3
- [Aerospike PHP Extension][2]
- Latest [Codeception][3] release

**NOTE:**
Probably this will work with older Aerospike Server versions, but we have not tested.

### Installation

Create the `composer.json` file as follows:

```json
{
    "require": {
        "codeception/codeception": "*",
        "codeception/aerospike-module": "*",
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
    enabled:
        - Aerospike:
            addr: '127.0.0.1'
            port: 3000
            set: 'cache'
            namespace: 'test'
            persistent: false
            cleanup: true
```

Then regenerate base class for unit suite

```sh
$ vendor/bin/codecept build
```

## License

Aerospike Module is open-sourced software licensed under the [MIT][4] License.

© 2015 Codeception Team and contributors

[1]: http://www.aerospike.com/
[2]: http://www.aerospike.com/docs/client/php/install/
[3]: https://github.com/Codeception/Codeception
[4]: https://github.com/Codeception/Aerospike-module/blob/master/docs/LICENSE.md
