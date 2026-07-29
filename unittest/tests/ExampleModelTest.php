<?php

declare(strict_types=1);

final class ExampleModelTest extends unitTestHelper
{
    protected $instance;
    protected $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/TestDatabase.php';
        require_once __DIR__ . '/support/ExampleModel.php';

        // a private in-memory database per test - it goes away with the
        // connection, so there is nothing to tear down afterwards
        $this->pdo = TestDatabase::sqlite();

        // newInstance, not getInstance: getInstance() caches per class for the
        // whole process, which would hand every later test the first test's
        // (already discarded) connection
        $this->instance = ExampleModel::newInstance([], $this->pdo);
    }

    /* Tests */

    /* public */

    public function testGetUser(): void
    {
        $this->assertEquals([
            'id' => 1,
            'first_name' => 'Johnny',
            'last_name' => 'Appleseed',
            'age' => 28,
        ], $this->instance->getUser(1));
    }

    public function testGetDetailUser(): void
    {
        $this->assertEquals([
            'fname' => 'Jenny',
            'lname' => 'Appleseed',
            'childern' => [
                0 => ['child_name' => 'Sally', 'id' => 2],
                1 => ['child_name' => 'Chuck', 'id' => 3],
            ]
        ], $this->instance->getUserDetailed(2));
    }

    public function testGetDetailUserNone(): void
    {
        $this->assertEquals([], $this->instance->getUserDetailed(999));
    }
}
