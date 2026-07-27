<?php

declare(strict_types=1);

namespace orange\model;

use PDO;
use orange\validate\interfaces\ValidateInterface;

/**
 * A model whose validation comes from the validate service.
 *
 * An operation's contract lives in two parallel arrays - $rules per field and
 * $ruleSets naming which fields take part - and the fields handed in are
 * filtered down to the set before they are validated. See DtoModel for the
 * same idea with a Dto class carrying the contract instead.
 *
 * The table settings, the $sql and the $crud come from ModelAbstract.
 *
 * @phpstan-consistent-constructor Subclasses (UserModel, ...) keep Model's
 *     constructor signature, so getInstance()'s `new static()` is safe.
 *
 * Singleton supplies the per-class instance cache and newInstance(). Its
 * getInstance() is declared getInstance(): mixed and forwards func_get_args(),
 * which PHP will not let a subclass narrow to a real signature - that is a
 * fatal, not a warning - so the types live in annotations instead.
 *
 * @method static static getInstance(array $config, PDO $pdo, ValidateInterface $validate)
 * @method static static newInstance(array $config, PDO $pdo, ValidateInterface $validate)
 */
abstract class Model extends ModelAbstract
{
    // extended by child models
    protected array $rules = [];
    /* rules example:
      'id' => ['isRequired|isInteger', 'Id'],
      'firstname' => ['isRequired|isString|isAlphaNumericSpace|maxLength[32]', 'First Name'],
      'lastname' => ['isRequired|isString|isAlphaNumericSpace|maxLength[32]', 'Last Name'],
      'age' => ['isRequired|isInteger|isGreaterThan[17]|isLessThan[111]', 'Age'],
    */

    protected array $ruleSets = [];
    /* ruleSets example:
      'create' => ['firstname', 'lastname', 'age'],
      'update' => ['id', 'firstname', 'lastname', 'age'],
      'delete' => ['id'],
    */

    protected function __construct(array $config, PDO $pdo, protected ValidateInterface $validate)
    {
        parent::__construct($config, $pdo);

        // validateService should throw exceptions for all failed rules so we can catch them
        $this->validate->throwExceptionOnFailure(true);
    }

    public function getRules(string $set): array
    {
        // we only need these rules
        return array_intersect_key($this->rules, array_flip($this->ruleSets[$set]));
    }

    public function filterFields(string $set, array &$fields): array
    {
        // we only need these columns (any additional are removed!)
        return array_intersect_key($fields, array_flip($this->ruleSets[$set]));
    }

    public function validateFields(string $set, array $fields): bool|array
    {
        // we only need these rules
        $rules = $this->getRules($set);

        // we only need these columns (any additional are removed!)
        $fields = $this->filterFields($set, $fields);

        // validate our record and get out our values
        // above we request that an exception is thrown containing all failed rules
        $fields = $this->validate->values($fields)->forEach($rules)->run();

        // if any rules failed technically we shouldn't get here because an exception was thrown
        return $this->validate->hasNoErrors() ? $fields : false;
    }
}
