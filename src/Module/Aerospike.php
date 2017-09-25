<?php

namespace Codeception\Module;

use Codeception\TestInterface;
use Codeception\Exception\ModuleException;
use Codeception\Module as CodeceptionModule;

/**
 * This module uses the [php-aerospike](http://www.aerospike.com/docs/client/php) extension
 * to interact with a [Aerospike](http://www.aerospike.com/) server.
 *
 * Performs a cleanup inserted keys after each test run.
 *
 * ## Status
 *
 * * Maintainer: **Serghei Iakovlev**
 * * Stability: **stable**
 * * Contact: serghei@codeception.com
 *
 * ## Configuration
 *
 * * addr: localhost - Aerospike host to connect
 * * port: 3000 - default Aerospike port
 * * set: cache - the Aerospike set to store data
 * * namespace: test - the Aerospike namespace to store data
 * * reconnect: false - whether the module should reconnect to the Aerospike before each test
 * * prefix: my_prefix_ - the key prefix
 *
 *
 * ### Example (`unit.suite.yml`)
 *
 * ```yaml
 *    modules:
 *        - Aerospike:
 *            addr: '127.0.0.1'
 *            port: 3000
 *            set: 'cache'
 *            namespace: 'test'
 *            reconnect: false
 *            prefix: 'prefix_'
 * ```
 *
 * Be sure you don't use the production server to connect.
 *
 * ## Public Properties
 *
 * * **aerospike** - instance of Aerospike object
 *
 */
class Aerospike extends CodeceptionModule
{
    /**
     * The Aerospike
     * @var \Aerospike
     */
    public $aerospike = null;

    protected $config = [
        'addr'      => '127.0.0.1',
        'port'      => 3000,
        'options'   => [],
        'set'       => 'cache',
        'namespace' => 'test',
        'reconnect' => false,
        'prefix'    => '',
    ];

    protected $keys = [];

    /**
     * Instructions to run after configuration is loaded
     *
     * @throws ModuleException
     */
    public function _initialize()
    {
        $this->connect();
    }

    /**
     * Code to run before each test
     *
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        if ($this->config['reconnect']) {
            $this->connect();
        }

        $this->removeInserted();

        parent::_before($test);
    }

    /**
     * Code to run after each test
     *
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        if ($this->config['reconnect']) {
            $this->disconnect();
        }

        parent::_after($test);
    }

    protected function onReconfigure()
    {
        if (!class_exists('\Aerospike')) {
            throw new ModuleException(__CLASS__, 'Aerospike classes not loaded');
        }

        $this->disconnect();
        $this->connect();
    }

    /**
     * Grabs value from Aerospike by key.
     *
     * Example:
     *
     * ```php
     * <?php
     * $users_count = $I->grabValueFromAerospike('users_count');
     * ?>
     * ```
     *
     * @param $key
     * @return mixed
     */
    public function grabValueFromAerospike($key)
    {
        $akey = $this->buildKey($key);

        $status = $this->aerospike->get($akey, $data);
        if ($status != \Aerospike::OK) {
            $this->fail(
                strtr(
                    "[:errno:] Unable to grab ':key:' from the database: :error:",
                    [
                        ':errno:' => $this->aerospike->errorno(),
                        ':key:'   => $key,
                        ':error:' => $this->aerospike->error()
                    ]
                )
            );
        }

        $this->debugSection('Value', $data['bins']['value']);

        return $data['bins']['value'];
    }

    /**
     * Checks item in Aerospike exists and the same as expected.
     *
     * Example:
     *
     * ```php
     * <?php
     * $I->seeInAerospike('key');
     * $I->seeInAerospike('key', 'value');
     * ?>
     *
     * @param $key
     * @param mixed $value
     */
    public function seeInAerospike($key, $value = false)
    {
        $akey = $this->buildKey($key);

        $status = $this->aerospike->get($akey, $actual);
        if ($status != \Aerospike::OK) {
            $this->fail(
                strtr(
                    "[:errno:] Unable to get ':key:' from the database: :error:",
                    [
                        ':errno:' => $this->aerospike->errorno(),
                        ':key:'   => $key,
                        ':error:' => $this->aerospike->error()
                    ]
                )
            );
        }

        $this->debugSection('Value', $actual['bins']['value']);
        $this->assertEquals($value, $actual['bins']['value']);
    }

    /**
     * Checks item in Aerospike does not exist or is the same as expected.
     *
     * Example:
     *
     * ```php
     * <?php
     * $I->dontSeeInAerospike('key');
     * $I->dontSeeInAerospike('key', 'value');
     * ?>
     *
     * @param string $key  Key
     * @param mixed $value Value
     */
    public function dontSeeInAerospike($key, $value = false)
    {
        $akey = $this->buildKey($key);

        if ($value === false) {
            $status = $this->aerospike->get($akey, $record);
            $this->assertSame(\Aerospike::ERR_RECORD_NOT_FOUND, $status);
            return;
        }

        $this->aerospike->get($akey, $actual);

        if (isset($actual['bins']['value'])) {
            $actual = $actual['bins']['value'];
        }

        $this->debugSection('Value', $actual);
        $this->assertNotEquals($value, $actual);
    }

    /**
     * Inserts data into Aerospike database.
     *
     * This data will be erased after the test.
     *
     * ```php
     * <?php
     * $I->haveInAerospike('users', ['name' => 'miles', 'email' => 'miles@davis.com']);
     * ?>
     * ```
     *
     * @param string $key  Key
     * @param mixed $value Value
     * @param int $ttl     The time-to-live in seconds for the record. [Optional]
     *
     * @return array
     */
    public function haveInAerospike($key, $value, $ttl = 0)
    {
        $akey  = $this->buildKey($key);
        $bins = ['value' => $value];

        $status = $this->aerospike->put(
            $akey,
            $bins,
            $ttl,
            [\Aerospike::OPT_POLICY_KEY => \Aerospike::POLICY_KEY_SEND]
        );

        if ($status != \Aerospike::OK) {
            $this->fail(
                strtr(
                    "[:errno:] Unable to store ':key:' to the database: :error:",
                    [
                        ':errno:' => $this->aerospike->errorno(),
                        ':key:'   => $key,
                        ':error:' => $this->aerospike->error()
                    ]
                )
            );
        }

        $this->keys[] = $akey;
        $this->debugSection('Aerospike', [$key, $value]);

        return $akey;
    }

    /**
     * Gets Aerospike instance
     *
     * @return \Aerospike
     */
    public function getAerospike()
    {
        return $this->aerospike;
    }

    private function connect()
    {
        if (!class_exists('\Aerospike')) {
            throw new ModuleException(
                __CLASS__,
                'Unable to connect to the Aerospike Server: The aerospike extension doe not loaded.'
            );
        }

        if ($this->aerospike instanceof \Aerospike) {
            return;
        }

        $config = ['hosts' => [['addr' => $this->config['addr'], 'port' => (int)$this->config['port']]], 'shm' => []];

        $this->aerospike = new \Aerospike(
            $config,
            true,
            $this->config['options']
        );
    }

    private function disconnect()
    {
        if (!$this->aerospike instanceof \Aerospike) {
            return;
        }

        if ($this->aerospike->isConnected()) {
            $this->aerospike->close();
        }

        $this->aerospike = null;
    }

    protected function removeInserted()
    {
        if (empty($this->keys)) {
            return;
        }

        foreach ($this->keys as $i => $key) {
            // We should to do check this because the test can delete the data by this time
            $status = $this->aerospike->exists($key, $metadata);
            $value  = json_encode($key);

            if ($status == \Aerospike::OK) {
                $this->aerospike->remove($key, [\Aerospike::OPT_POLICY_RETRY => \Aerospike::POLICY_RETRY_ONCE]);
            } elseif ($status == \Aerospike::ERR_RECORD_NOT_FOUND) {
                $this->debug("The key {$value} does not exist in the database");
            } else {
                $this->debug(
                    strtr(
                        '[:errno:] Could not delete record :key: from the database: :error:',
                        [
                            ':errno:' => $this->aerospike->errorno(),
                            ':key:'   => $value,
                            ':error:' => $this->aerospike->error(),
                        ]
                    )
                );
            }
        }

        $this->keys = [];
    }

    /**
     * Generates a unique key used for storing cache in Aerospike DB.
     *
     * @param string $key Cache key
     * @return array
     */
    protected function buildKey($key)
    {
        return $this->aerospike->initKey(
            $this->config['namespace'],
            $this->config['set'],
            $this->config['prefix'] . $key
        );
    }
}
