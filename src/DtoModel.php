<?php

declare(strict_types=1);

namespace orange\model;

use PDO;
use orange\dto\Dto;
use orange\model\exceptions\Model as ModelException;
use orange\model\exceptions\DtoValidationFailed;

/**
 * A model whose validation comes from a Dto class instead of the validate service.
 *
 * Model keeps an operation's contract in two parallel arrays - $rules per field
 * and $ruleSets naming which fields take part - and runs them through the
 * validate service. Here an operation names one Dto class instead: the Dto's
 * attributes already carry the rules, the filters, the human labels and the
 * database column/table mapping, so a set is a single class rather than two
 * array entries that have to be kept in step. It also means the values handed
 * to the insert are the Dto's db shape, so #[Column] remapping is honoured
 * without the model knowing about it.
 *
 * A Dto can also describe more than one table - tag the properties
 * #[Table('users')] and #[Table('user_meta')] and one class carries a whole
 * form. validateFields() then takes only the columns tagged for the table this
 * model writes to; a hand-written method holding the Dto itself asks for the
 * same share with $dto->asColumns($withoutPrimary, $this->tablename).
 *
 * The table settings, the $sql and the $crud come from ModelAbstract. Because a
 * Dto validates itself the moment it is constructed, there is no validate
 * service to inject - so this takes that two argument constructor as it is,
 * where Model has to widen it.
 *
 * @phpstan-consistent-constructor Subclasses keep DtoModel's constructor
 *     signature, so getInstance()'s `new static()` is safe.
 *
 * Singleton supplies the per-class instance cache and newInstance(). Its
 * getInstance() is declared getInstance(): mixed and forwards func_get_args(),
 * which PHP will not let a subclass narrow to a real signature - that is a
 * fatal, not a warning - so the types live in annotations instead.
 *
 * @method static static getInstance(array $config, PDO $pdo)
 * @method static static newInstance(array $config, PDO $pdo)
 */
abstract class DtoModel extends ModelAbstract
{
    /**
     * Operation name => the Dto class that validates it.
     *
     * @var array<string, class-string<Dto>>
     */
    protected array $dtos = [];
    /* dtos example:
      'create' => CreateUserDto::class,
      'update' => UpdateUserDto::class,
      'delete' => DeleteUserDto::class,
    */

    /**
     * The Dto class registered for an operation.
     *
     * @return class-string<Dto>
     * @throws DtoValidationFailed When the operation has no Dto registered
     */
    public function getDtoClass(string $set): string
    {
        if (!isset($this->dtos[$set])) {
            throw new DtoValidationFailed('No dto registered for "' . $set . '" on ' . static::class . '.', [], 500);
        }

        return $this->dtos[$set];
    }

    /**
     * Run input through an operation's Dto, valid or not.
     *
     * The Dto validates and filters during construction, so what comes back is
     * already the answer - ask it isValid()/errors(), then asColumns() for the
     * values to persist. Use this when a failure is an expected outcome you want
     * to report on; use requireDto() when it isn't.
     */
    public function makeDto(string $set, array $input): Dto
    {
        $dtoClass = $this->getDtoClass($set);

        return new $dtoClass($input);
    }

    /**
     * Run input through an operation's Dto, insisting it passes.
     *
     * @throws DtoValidationFailed Carrying the Dto's own errors, nested detail included
     */
    public function requireDto(string $set, array $input): Dto
    {
        $dto = $this->makeDto($set, $input);

        if (!$dto->isValid()) {
            $errors = $dto->allErrors();

            throw new DtoValidationFailed($this->errorsAsText($errors), $errors);
        }

        return $dto;
    }

    /**
     * The validated columns for an operation, ready for an insert or update.
     *
     * The DtoModel counterpart to Model::validateFields(): nothing that failed
     * validation, and nothing the Dto didn't declare, reaches the database. Keys
     * are database column names, so #[Column] remapping is already applied.
     *
     * This model's share of them, at that: a Dto tagged #[Table] for more than
     * one table hands back only the columns tagged for the table this model
     * writes to. A Dto that tags nothing hands back all of them.
     *
     * Pass $withoutPrimary for an update's SET clause, where the primary belongs
     * in the WHERE instead.
     *
     * @throws DtoValidationFailed When the input does not pass
     * @throws ModelException When the Dto names tables and none of them is this model's
     */
    public function validateFields(string $set, array $input, bool $withoutPrimary = false): array
    {
        $dto = $this->requireDto($set, $input);

        // the assembled config, not the raw $tablename property - a config
        // override is the table Sql and Crud were built with. A Dto that names
        // no table takes the name and answers with everything it has, which is
        // the single-model case
        $columns = $dto->asColumns($withoutPrimary, (string)$this->config['tablename']);

        if ($columns === null) {
            // it does name tables, just not this model's - a mistyped #[Table]
            // or the wrong Dto registered for the operation. Persisting another
            // table's columns into this one is not a recoverable reading of that
            throw new ModelException($dto::class . ' has no columns for table "' . $this->config['tablename'] . '" on ' . static::class . '. It names: ' . implode(', ', $dto->tables() ?? []) . '.');
        }

        return $columns;
    }

    /**
     * Flatten a Dto error set into one message per line.
     *
     * @param array<string, array<int, string>> $errors
     */
    protected function errorsAsText(array $errors): string
    {
        $lines = [];

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $lines[] = $message;
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
