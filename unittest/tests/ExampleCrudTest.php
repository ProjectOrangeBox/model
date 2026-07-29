<?php

declare(strict_types=1);

final class ExampleCrudTest extends unitTestHelper
{
    protected $instance;
    protected $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/TestDatabase.php';
        require_once __DIR__ . '/support/ExampleCrud.php';

        // a private in-memory database per test - it goes away with the
        // connection, so there is nothing to tear down afterwards
        $this->pdo = TestDatabase::sqlite();

        // newInstance, not getInstance: getInstance() caches per class for the
        // whole process, which would hand every later test the first test's
        // (already discarded) connection
        $this->instance = ExampleCrud::newInstance([], $this->pdo);
    }

    public function testCreateUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));
    }

    public function testUpdateUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));
        $this->assertTrue($this->instance->update(['age' => 48], 3));
    }

    public function testReadUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));

        $match = array(
            'id' => 3,
            'first_name' => 'John',
            'last_name' => 'Orange',
            'age' => 32,
        );

        $this->assertEquals($match, $this->instance->read(3));
    }

    public function testReadAllUsers(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));

        $match = array(
            0 =>
            array(
                'id' => 1,
                'first_name' => 'Johnny',
                'last_name' => 'Appleseed',
                'age' => 28,
            ),
            1 =>
            array(
                'id' => 2,
                'first_name' => 'Jenny',
                'last_name' => 'Appleseed',
                'age' => 31,
            ),
            2 =>
            array(
                'id' => 3,
                'first_name' => 'John',
                'last_name' => 'Orange',
                'age' => 32,
            ),
        );

        $this->assertEquals($match, $this->instance->readAll());
    }

    public function testDeleteUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));
        $this->assertTrue($this->instance->delete(3));
    }
}
