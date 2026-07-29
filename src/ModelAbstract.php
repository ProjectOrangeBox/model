<?php

declare(strict_types=1);

namespace orange\model;

use PDO;
use orange\framework\base\Singleton;

/**
 * A model's plumbing with no opinion on validation: the table/fetch settings,
 * the config they are assembled into, and the ready-made $sql and $crud built
 * from it.
 *
 * Extend this directly for a model that has no per-operation contract to
 * enforce - one whose methods are hand-written queries, or whose input was
 * already validated before it got here. Extend DtoModel when an operation
 * should be checked against a Dto first; that is the only thing it adds.
 *
 * There used to be a second child, Model, which paired $rules with $ruleSets
 * and ran them through the orange/validate service. Dtos carry the rules, the
 * filters, the labels and the column mapping in one class instead, so it was
 * dropped and this package no longer depends on orange/validate.
 *
 * @phpstan-consistent-constructor Subclasses keep this constructor signature,
 *     so getInstance()'s `new static()` is safe.
 *
 * Singleton supplies the per-class instance cache and newInstance(). Its
 * getInstance() is declared getInstance(): mixed and forwards func_get_args(),
 * which PHP will not let a subclass narrow to a real signature - that is a
 * fatal, not a warning - so the types live in annotations instead.
 *
 * @method static static getInstance(array $config, PDO $pdo)
 * @method static static newInstance(array $config, PDO $pdo)
 */
abstract class ModelAbstract extends Singleton
{
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

    /**
     * The table a model works on when it doesn't name one itself:
     * the class's short name minus a trailing "Model" - UserModel => User.
     */
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
