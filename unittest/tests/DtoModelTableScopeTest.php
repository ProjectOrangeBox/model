<?php

declare(strict_types=1);

use orange\model\exceptions\Model as ModelException;

/**
 * A Dto can describe more than one table. Each DtoModel takes its own table's
 * share of it - and takes all of it when the Dto names no table, which is the
 * ordinary single-model case.
 */
final class DtoModelTableScopeTest extends \unitTestHelper
{
    protected $pdo;
    protected $main;
    protected $join;
    protected $token;

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/TestDatabase.php';
        require_once __DIR__ . '/support/ProfileFormDtoModels.php';

        $this->pdo = TestDatabase::sqlite();

        // newInstance, not getInstance - each test gets its own model
        $this->main = MainProfileModel::newInstance([], $this->pdo);
        $this->join = JoinProfileModel::newInstance([], $this->pdo);
        $this->token = TokenModel::newInstance([], $this->pdo);
    }

    protected function validForm(): array
    {
        return [
            'id' => '7',
            'firstName' => 'Johnny',
            'childId' => '3',
            'childName' => 'Peter',
            'confirm' => 'yes',
        ];
    }

    public function testEachModelTakesOnlyItsOwnTablesColumns(): void
    {
        $this->assertSame(
            ['id' => 7, 'first_name' => 'Johnny'],
            $this->main->validateFields('save', $this->validForm())
        );

        $this->assertSame(
            ['id' => 3, 'child_name' => 'Peter'],
            $this->join->validateFields('save', $this->validForm())
        );
    }

    /**
     * Each table carries its own #[IsPrimary], so a model asks for the key the
     * same way it asks for the columns. Unqualified there are two, and the Dto
     * refuses to guess.
     */
    public function testEachModelTakesItsOwnTablesKey(): void
    {
        $dto = $this->main->requireDto('save', $this->validForm());

        $this->assertSame(7, $dto->primaryValue('main'));
        $this->assertSame(3, $dto->primaryValue('join'));

        $this->assertSame(['id', 'id'], $dto->primaries());

        $this->expectException(\LogicException::class);

        $dto->primaryValue();
    }

    /**
     * The confirmation field carries no #[Table], so it reaches neither
     * insert - it would be an unknown column in both.
     */
    public function testAnUntaggedFieldReachesNeitherTable(): void
    {
        $this->assertArrayNotHasKey('confirm', $this->main->validateFields('save', $this->validForm()));
        $this->assertArrayNotHasKey('confirm', $this->join->validateFields('save', $this->validForm()));
    }

    /**
     * A Dto that names no table at all was written for one model, so every
     * column it has is that model's - even though `tokens` is nowhere in it.
     */
    public function testAModelTakesEveryColumnWhenTheDtoNamesNoTable(): void
    {
        $this->assertSame(
            ['token' => 'abc123', 'user_id' => 7],
            $this->token->validateFields('create', ['token' => 'abc123', 'userId' => '7'])
        );
    }

    /**
     * The other reason a Dto has nothing for this table: it names tables, just
     * not this one. That is a mistyped #[Table] or the wrong Dto registered for
     * the operation, and writing another table's columns into this one is not a
     * recoverable reading of it - so it throws rather than falling back.
     */
    public function testAModelThrowsWhenTheDtoNamesTablesButNotItsOwn(): void
    {
        $model = MainProfileModel::newInstance(['tablename' => 'nosuchtable'], $this->pdo);

        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('has no columns for table "nosuchtable"');

        $model->validateFields('save', $this->validForm());
    }

    public function testWithoutPrimaryDropsThePrimaryFromItsOwnTableOnly(): void
    {
        // the primary is main's, so main loses it ...
        $this->assertSame(
            ['first_name' => 'Johnny'],
            $this->main->validateFields('save', $this->validForm(), true)
        );

        // ... and join loses its own, not main's
        $this->assertSame(
            ['child_name' => 'Peter'],
            $this->join->validateFields('save', $this->validForm(), true)
        );
    }

    /**
     * What a hand-written model method does when it is holding the Dto itself -
     * see ExampleDtoModel::update(). Same ask, same answer.
     */
    public function testAModelCanAskTheDtoDirectlyForTheSameShare(): void
    {
        $dto = $this->main->requireDto('save', $this->validForm());

        $this->assertSame(['id' => 7, 'first_name' => 'Johnny'], $dto->asColumns(false, 'main'));
        $this->assertSame(['first_name' => 'Johnny'], $dto->asColumns(true, 'main'));

        // the same Dto instance, asked by the other model
        $this->assertSame(['id' => 3, 'child_name' => 'Peter'], $dto->asColumns(false, 'join'));

        // and it says as much when asked what it describes
        $this->assertSame(['main', 'join'], $dto->tables());
    }

    /**
     * The scoping follows the table Sql and Crud were built with, so a
     * config override moves it too.
     */
    public function testAConfigTablenameOverrideMovesTheScope(): void
    {
        $model = MainProfileModel::newInstance(['tablename' => 'join'], $this->pdo);

        $this->assertSame(['id' => 3, 'child_name' => 'Peter'], $model->validateFields('save', $this->validForm()));
    }
}
