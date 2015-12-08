<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleException;
use Codeception\Module as CodeceptionModule;
use Codeception\TestCase;

/**
 * Connects to [Aerospike](http://www.aerospike.com/) using [php-aerospike](http://www.aerospike.com/docs/client/php) extension.
 *
 * Performs a cleanup inserted keys after each test run.
 *
 * ## Status
 *
 * * Maintainer: **Serghei Iakovlev**
 * * Stability: **beta**
 * * Contact: sadhooklay@gmail.com
 *
 * ## Configuration
 *
 * * addr: localhost - Aerospike host to connect
 * * port: 3000 - default Aerospike port
 * * set: cache - the Aerospike set to store data
 * * namespace: test - the Aerospike namespace to store data
 * * reconnect: false - whether the module should reconnect to the Aerospike before each test
 * * silent: true - do not throw exception if the Aerospike extension does not installed at bootstrap time
 *
 *
 * ## Example (`unit.suite.yml`)
 *
 *     modules:
 *         enabled:
 *             - Aerospike:
 *                 addr: '127.0.0.1'
 *                 port: 3000
 *                 set: 'cache'
 *                 namespace: 'test'
 *                 reconnect: true
 *                 silent: true
 *
 * Be sure you don't use the production server to connect.
 *
 * ## Public Properties
 *
 * * aerospike - instance of Aerospike object
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
        'silent'    => true,
    ];

    protected $keys = [];

    public function _initialize()
    {
        if (!class_exists('\Aerospike') && !$this->config['silent']) {
            throw new ModuleException(__CLASS__, 'Aerospike classes not loaded');
        }

        $this->connect();
    }

    public function _before(TestCase $test)
    {
        if (class_exists('\Aerospike')) {
            if ($this->config['reconnect']) {
                $this->connect();
            }

            $this->removeInserted();
        }

        parent::_before($test);
    }

    public function _after(TestCase $test)
    {
        if ($this->config['reconnect'] && class_exists('\Aerospike')) {
            $this->disconnect();
        }

        parent::_after($test);
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
        $key = $this->buildKey($key);
        $this->aerospike->get($key, $data);

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
        $key = $this->buildKey($key);
        $this->aerospike->get($key, $actual);

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
        $key = $this->buildKey($key);
        $this->aerospike->get($key, $actual);

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
        $key  = $this->buildKey($key);
        $bins = ['value' => $value];

        $status = $this->aerospike->put($key, $bins, $ttl);

        if (\Aerospike::OK != $status) {
            $this->fail(sprintf('Warning [%s]: %s', $this->aerospike->errorno(), $this->aerospike->error()));
            return null;
        }

        $this->keys[] = $key;
        $this->debugSection('Aerospike', json_encode([$key, $value]));

        return $key;
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
        if ($this->aerospike instanceof \Aerospike || !class_exists('\Aerospike')) {
            return;
        }

        $config = ['hosts' => [['addr' => $this->config['addr'], 'port' => $this->config['port']]]];

        $this->aerospike = new \Aerospike(
            $config,
            false,
            $this->config['options']
        );
    }

    private function disconnect()
    {
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
            $this->aerospike->remove(
                $key,
                [\Aerospike::OPT_POLICY_RETRY => \Aerospike::POLICY_RETRY_ONCE]
            );

            unset($this->keys[$i]);
        }
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
            $key
        );
    }
}
