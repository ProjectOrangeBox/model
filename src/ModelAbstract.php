<?php

declare(strict_types=1);

namespace orange\model;

use PDO;
use orange\framework\base\Singleton;

/**
 * The plumbing Model and DtoModel share: the table/fetch settings, the config
 * they are assembled into, and the ready-made $sql and $crud built from it.
 *
 * What is left to the two children is how an operation is validated - Model
 * pairs $rules with $ruleSets and runs them through the validate service,
 * DtoModel names one Dto class per operation - which is also why validation
 * has no place in here: their validateFields() take different arguments and
 * return different things, so there is nothing common to hoist.
 *
 * Not extended directly. Extend Model or DtoModel.
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
