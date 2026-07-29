<?php

declare(strict_types=1);

final class ExampleCrudActiveTest extends unitTestHelper
{
    protected $instance;
    protected $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/TestDatabase.php';
        require_once __DIR__ . '/support/ExampleCrudActive.php';

        // a private in-memory database per test - it goes away with the
        // connection, so there is nothing to tear down afterwards
        $this->pdo = TestDatabase::sqlite();

        // newInstance, not getInstance: getInstance() caches per class for the
        // whole process, which would hand every later test the first test's
        // (already discarded) connection
        $this->instance = ExampleCrudActive::newInstance([], $this->pdo);
    }

    public function testDeactiveUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));
        $this->assertTrue($this->instance->delete(3));
        $this->assertFalse($this->instance->read(3));

        $this->assertTrue($this->instance->activate(3));

        $match = array(
            'id' => 3,
            'first_name' => 'John',
            'last_name' => 'Orange',
            'age' => 32,
            'is_active' => 1,
        );

        $this->assertEquals($match, $this->instance->read(3));
        $this->assertTrue($this->instance->deactivate(3));

        $this->assertFalse($this->instance->read(3));
    }

    public function testDeactiveReadAllUser(): void
    {
        $this->assertEquals(3, $this->instance->create('Orange', 'John', 32));

        $this->assertTrue($this->instance->delete(3));

        $match = array(
            0 =>
            array(
                'id' => 1,
                'first_name' => 'Johnny',
                'last_name' => 'Appleseed',
                'age' => 28,
                'is_active' => 1,
            ),
            1 =>
            array(
                'id' => 2,
                'first_name' => 'Jenny',
                'last_name' => 'Appleseed',
                'age' => 31,
                'is_active' => 1,
            ),
        );


        $this->assertEquals($match, $this->instance->readAll());
        $this->assertTrue($this->instance->activate(3));

        $match = array(
            0 =>
            array(
                'id' => 1,
                'first_name' => 'Johnny',
                'last_name' => 'Appleseed',
                'age' => 28,
                'is_active' => 1,
            ),
            1 =>
            array(
                'id' => 2,
                'first_name' => 'Jenny',
                'last_name' => 'Appleseed',
                'age' => 31,
                'is_active' => 1,
            ),
            2 =>
            array(
                'id' => 3,
                'first_name' => 'John',
                'last_name' => 'Orange',
                'age' => 32,
                'is_active' => 1,
            ),
        );

        $this->assertEquals($match, $this->instance->readAll());
    }
}
