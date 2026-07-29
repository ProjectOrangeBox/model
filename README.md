# Model

A small database model layer over PDO. `ModelAbstract` is the base you extend per table — it merges config, sets up a `Crud` helper and a `Sql` query builder, and leaves the queries to you. `Crud` does the actual insert/update/delete/read work.

`DtoModel` extends it with one thing: an operation can name a [orange/dto](https://github.com/ProjectOrangeBox/dto) class, and input is checked against it before anything reaches the database. The Dto's attributes carry the rules, the filters, the human labels and the column mapping together, so a contract is one class rather than several lists kept in step. A Dto whose properties are tagged `#[Table]` can describe several tables at once, and each `DtoModel` takes only the columns tagged for the table it writes to — see [example.md](example.md#one-dto-several-tables).

Extend `ModelAbstract` when a model has no such contract to enforce; extend `DtoModel` when it does. Either way the table/fetch settings, the config they are assembled into and the ready-made `$sql`/`$crud` are the same.

**A cookbook of worked examples lives in [example.md](example.md)** — `Sql`, `Crud` and `DtoModel`.

## Example

```php
use PDO;
use orange\dto\Dto;
use orange\model\DtoModel;
use orange\dto\attributes\Label;
use orange\dto\attributes\Column;
use orange\dto\attributes\filters\Trim;
use orange\dto\attributes\validations\MaxLength;
use orange\dto\attributes\validations\IsRequired;
use orange\dto\attributes\validations\ValidEmail;

class CreateUserDto extends Dto
{
    #[IsRequired]
    #[Trim]
    #[MaxLength(32)]
    #[Column('first_name')]
    #[Label('First Name')]
    public string $firstname;

    #[IsRequired]
    #[Trim]
    #[ValidEmail]
    #[Label('Email')]
    public string $email;
}

class UserModel extends DtoModel
{
    protected string $tablename = 'users';

    protected array $dtos = [
        'create' => CreateUserDto::class,
    ];

    // $this->crud and $this->validateFields() come from DtoModel
    public function create(array $fields): int
    {
        return $this->crud->create($this->validateFields('create', $fields));
    }

    public function find(int $id): array|bool
    {
        return $this->crud->read($id);
    }
}

$userModel = UserModel::getInstance($config, $pdo);

// throws DtoValidationFailed if firstname/email fail their rules
$id = $userModel->create(['firstname' => 'Ada', 'email' => 'ada@example.com']);
$row = $userModel->find($id);
```

`validateFields()` hands back only what the Dto declared and only what passed, keyed by database column name — so extra input keys are dropped, and `#[Column]` remapping (`firstname` in, `first_name` out) is already applied. Set `readOnlyActive`/`deactiveOnDelete` on `Crud` to scope reads to, or soft-delete via, an `is_active` column instead of hard deletes.

## Upgrading

`Model` — the base that paired `$rules` with `$ruleSets` and ran them through the [orange/validate](https://github.com/ProjectOrangeBox/validate) service — has been removed, and this package no longer depends on `orange/validate`. Port a `Model` subclass by turning each rule set into a Dto class and listing it in `$dtos`; the constructor loses its third argument, and the exception raised on failure becomes `orange\model\exceptions\DtoValidationFailed`, which answers `getErrors()`/`getErrorsAsHtml()` the same way `ValidationFailed` did.

Rules with no Dto equivalent — a uniqueness check, verifying a password against a stored hash — need a database connection or a secret that a Dto has no way to receive, so they belong in the model method around the Dto rather than in it.
