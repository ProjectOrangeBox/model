<?php

declare(strict_types=1);

namespace orange\model;

use PDO;
use orange\dto\Dto;
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
 * Because a Dto validates itself the moment it is constructed, there is no
 * validate service to inject - hence the two argument constructor.
 *
 * @phpstan-consistent-constructor Subclasses keep DtoModel's constructor
 *     signature, so getInstance()'s `new static()` is safe.
 */
abstract class DtoModel
{
    private static array $instances = [];

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

    // required in extending class
    protected string $tablename;
    protected string $primaryColumn = 'id';

    protected string $entityClass;

    // https://www.php.net/manual/en/pdostatement.fetch.php
    protected int $defaultFetchType = PDO::FETCH_ASSOC;

    // if type is PDO::FETCH_CLASS provide the class here
    protected string $fetchClass = '';

    // throw an exception on error or simply capture for further processing
    protected bool $throwException = false;

    protected Sql $sql;
    protected Crud $crud;

    protected function __construct(protected array $config, protected PDO $pdo)
    {
        if (!isset($this->tablename)) {
            $this->tablename = $this->generateTablename();
        }

        // setup sql config
        $this->config = [
            'primaryColumn' => $config['primaryColumn'] ?? $this->primaryColumn,
            'tablename' => $config['tablename'] ?? $this->tablename,
            'defaultFetchType' => $config['defaultFetchType'] ?? $this->defaultFetchType,
            'fetchClass' => $config['fetchClass'] ?? $this->fetchClass,
            // should the model throw exceptions?
            'throwException' => $config['throwException'] ?? $this->throwException,
        ];

        // setup our own personal versions
        $this->sql = new Sql($this->config, $pdo);

        // crud always throws sql exceptions
        $this->crud = new Crud($this->config, $pdo);
    }

    public static function getInstance(array $config, PDO $pdo): self
    {
        $subclass = static::class;

        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static($config, $pdo);
        }

        return self::$instances[$subclass];
    }

    /**
     * Build a model without touching the shared instance cache.
     *
     * This should ONLY be called if you MUST get a new instance - for testing
     * etc. getInstance() caches per class for the life of the process, which in
     * a test run would hand every later test the first one's PDO connection.
     */
    public static function newInstance(array $config, PDO $pdo): self
    {
        return new static($config, $pdo);
    }

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
     * Pass $withoutPrimary for an update's SET clause, where the primary belongs
     * in the WHERE instead.
     *
     * @throws DtoValidationFailed When the input does not pass
     */
    public function validateFields(string $set, array $input, bool $withoutPrimary = false): array
    {
        return $this->requireDto($set, $input)->asColumns($withoutPrimary);
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

    public function generateTablename(): string
    {
        $tablename = static::class;

        $pos = strrpos($tablename, '\\');

        if ($pos) {
            $tablename = substr($tablename, $pos + 1);
        }

        if (str_ends_with(strtolower($tablename), 'model')) {
            $tablename = substr($tablename, 0, -5);
        }

        return $tablename;
    }

    public function getLastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }
}
