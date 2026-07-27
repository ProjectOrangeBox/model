# Model

A small database model layer over PDO. `Model` is an abstract base you extend per table — it merges config, sets up a `Crud` helper and a `Sql` query builder, and validates input against per-field rules grouped into named rule sets (`create`, `update`, `delete`, ...). `Crud` does the actual insert/update/delete/read work.

`DtoModel` is the same idea with validation coming from an [orange/dto](https://github.com/ProjectOrangeBox/request) class instead of the validate service — one Dto per operation rather than parallel `$rules`/`$ruleSets` arrays.

Validation is in fact the only thing the two differ on: the table/fetch settings, the config they are assembled into and the ready-made `$sql`/`$crud` all come from `ModelAbstract`, which both extend and neither of your classes should.

**A cookbook of worked examples lives in [example.md](example.md)** — `Sql`, `Crud` and `DtoModel`.

## Example

```php
use PDO;
use orange\model\Model;
use orange\validate\interfaces\ValidateInterface;

class UserModel extends Model
{
    protected string $tablename = 'users';

    protected array $rules = [
        'id' => ['isRequired|isInteger', 'Id'],
        'firstname' => ['isRequired|isString|isAlphaNumericSpace|maxLength[32]', 'First Name'],
        'email' => ['isRequired|isValidEmail', 'Email'],
    ];

    protected array $ruleSets = [
        'create' => ['firstname', 'email'],
        'update' => ['id', 'firstname', 'email'],
        'delete' => ['id'],
    ];

    // $this->crud and $this->validateFields() come from Model
    public function create(array $fields): int
    {
        $fields = $this->validateFields('create', $fields);

        return $this->crud->create($fields);
    }

    public function find(int $id): array|bool
    {
        return $this->crud->read($id);
    }
}

$userModel = UserModel::getInstance($config, $pdo, $validate);

// throws ValidationFailed (via $validate) if firstname/email fail their rules
$id = $userModel->create(['firstname' => 'Ada', 'email' => 'ada@example.com']);
$row = $userModel->find($id);
```

`validateFields()` both filters `$fields` down to the rule set's columns and validates them, so extra input keys are dropped automatically. Set `readOnlyActive`/`deactiveOnDelete` on `Crud` to scope reads to, or soft-delete via, an `is_active` column instead of hard deletes.
