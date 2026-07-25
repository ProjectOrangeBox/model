<?php

declare(strict_types=1);

use orange\dto\Dto;
use orange\model\exceptions\DtoValidationFailed;

/**
 * DtoModel is the validate-service-free counterpart to Model: an operation names
 * a Dto class, and the Dto's attributes carry the rules, the filters and the
 * database column mapping.
 */
final class ExampleDtoModelTest extends \unitTestHelper
{
    protected $instance;
    protected $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/TestDatabase.php';
        require_once __DIR__ . '/support/ExampleDtoModel.php';

        // a private in-memory database per test - it goes away with the
        // connection, so there is nothing to tear down afterwards
        $this->pdo = TestDatabase::sqlite();

        $this->instance = ExampleDtoModel::newInstance([], $this->pdo);
    }

    public function testCreateUser(): void
    {
        $id = $this->instance->create(['fname' => 'John', 'lname' => 'Orange', 'age' => '32']);

        $this->assertEquals(3, $id);

        $this->assertEquals([
            'id' => 3,
            'first_name' => 'John',
            'last_name' => 'Orange',
            'age' => 32,
            'is_active' => 1,
        ], $this->instance->read(3));
    }

    /**
     * The request keys (fname/lname) differ from the table's columns
     * (first_name/last_name) - the Dto's #[FieldName]/#[Column] pair is what
     * bridges them, so the model itself never mentions either name.
     */
    public function testFieldNamesAreRemappedToColumns(): void
    {
        $columns = $this->instance->validateFields('create', ['fname' => 'John', 'lname' => 'Orange', 'age' => 32]);

        $this->assertSame(['first_name' => 'John', 'last_name' => 'Orange', 'age' => 32], $columns);
    }

    public function testFiltersRunBeforeTheValueIsStored(): void
    {
        // Trim strips the padding, ToInteger casts the numeric string
        $columns = $this->instance->validateFields('create', ['fname' => '  John  ', 'lname' => 'Orange', 'age' => '32']);

        $this->assertSame('John', $columns['first_name']);
        $this->assertSame(32, $columns['age']);
    }

    public function testUpdateDropsThePrimaryFromTheSetClause(): void
    {
        $this->assertTrue($this->instance->update(['id' => 1, 'fname' => 'Johnny', 'lname' => 'Appleseed', 'age' => 29]));

        $row = $this->instance->read(1);

        $this->assertEquals(29, $row['age']);
        $this->assertEquals(1, $row['id']);
    }

    public function testValidateFieldsWithoutPrimary(): void
    {
        $columns = $this->instance->validateFields('update', [
            'id' => 1,
            'fname' => 'Johnny',
            'lname' => 'Appleseed',
            'age' => 29,
        ], true);

        $this->assertArrayNotHasKey('id', $columns);
        $this->assertSame(['first_name' => 'Johnny', 'last_name' => 'Appleseed', 'age' => 29], $columns);
    }

    public function testInvalidInputThrowsCarryingTheDtoErrors(): void
    {
        try {
            $this->instance->create(['fname' => '', 'lname' => 'Orange', 'age' => 12]);

            $this->fail('expected DtoValidationFailed');
        } catch (DtoValidationFailed $e) {
            $this->assertSame(406, $e->getHttpCode());

            // the age rule and the required-first-name rule both failed
            $this->assertContains('fname', $e->getKeys());
            $this->assertContains('age', $e->getKeys());

            $this->assertNotEmpty($e->getMessage());
            $this->assertJson($e->getOutput());
        }
    }

    /**
     * makeDto() is the non-throwing door: a failure is an answer, not an
     * exception, so a caller can report on it.
     */
    public function testMakeDtoDoesNotThrow(): void
    {
        $dto = $this->instance->makeDto('create', ['fname' => '', 'lname' => '', 'age' => 5]);

        $this->assertInstanceOf(Dto::class, $dto);
        $this->assertFalse($dto->isValid());
        $this->assertNotEmpty($dto->errors());
    }

    public function testUnknownOperationThrows(): void
    {
        $this->expectException(DtoValidationFailed::class);

        $this->instance->makeDto('nosuchset', []);
    }

    public function testGeneratedTablenameStripsTheModelSuffix(): void
    {
        $this->assertEquals('ExampleDto', $this->instance->generateTablename());
    }
}
