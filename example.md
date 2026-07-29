# orange/model — Examples

Worked examples for the four pieces you use directly: the `Sql` query builder,
the `Crud` shortcut helper, and the two model bases. See [README.md](README.md)
for the reference.

Which one to reach for:

| | Use it when |
| --- | --- |
| `Crud` | The operation is a plain insert/update/delete/read on one table by primary key. |
| `Sql` | You need joins, grouped conditions, paging, raw fragments — anything `Crud` doesn't cover. |
| `ModelAbstract` | You want a model with `$crud`/`$sql` set up and no validation contract. |
| `DtoModel` | The same, plus operations checked against a Dto before they hit the database. |

Both model bases hand you a ready `$this->crud` and `$this->sql`, so these
examples compose.

---

## Sql — the query builder

Every method returns `$this`, and `execute()` is what actually runs the
statement. Values are bound, never interpolated.

```php
use PDO;
use orange\model\Sql;

$sql = new Sql([
    'tablename' => 'users',
    'primaryColumn' => 'id',
    'defaultFetchType' => PDO::FETCH_ASSOC,
    'throwException' => false,
], $pdo);
```

### Reading

```php
// one row by primary key
$row = $sql->select()->from()->wherePrimary(12)->execute()->row();

// specific columns, with aliases - "cow as c" and "cow c" both work
$rows = $sql->select('id, first_name as fname, last_name lname')
    ->from()
    ->execute()
    ->rows();

// a single scalar
$email = $sql->select('email')->from()->wherePrimary(12)->execute()->column(0);

// id => name, straight into a select box
$options = $sql->select('id, first_name')->from()->execute()->keyPair();
```

`reset()` clears the builder between queries; `execute()` does it for you, so a
fresh chain is the normal case.

### Conditions

```php
$sql->select()->from()->where('age', '>=', 21)->execute()->rows();

$sql->select()->from()->whereEqual('last_name', 'Appleseed')->execute()->rows();

$sql->select()->from()->whereIn('id', [1, 2, 3])->execute()->rows();

$sql->select()->from()->whereIsNull('deleted_at')->execute()->rows();
```

Chain them with `and()` / `or()`, and group with `groupStart()` / `groupEnd()`:

```php
// WHERE `is_active` = :... AND ( `first_name` = :... OR `last_name` = :... )
$sql->select()->from()
    ->whereEqual('is_active', 1)
    ->and()
    ->groupStart()
        ->whereEqual('first_name', 'Johnny')
        ->or()
        ->whereEqual('last_name', 'Appleseed')
    ->groupEnd()
    ->execute()
    ->rows();
```

For anything the builder has no vocabulary for, `whereColumnRaw()` takes the
fragment and its bindings — the column is still escaped for you:

```php
$sql->select()->from()
    ->whereColumnRaw('price', 'NOT BETWEEN :low AND :high', ['low' => 10, 'high' => 20])
    ->execute()
    ->rows();
```

### Ordering, paging, joins

```php
$sql->select()->from()
    ->orderBy('last_name', 'asc')     // 'a'/'az' and 'd'/'za' are accepted shorthand
    ->orderBy('age', 'desc')
    ->limit(20, 40)                   // LIMIT 20 OFFSET 40
    ->execute()
    ->rows();

// page 5 at 100 per page -> LIMIT 100 OFFSET 400
$sql->select()->from()->limitByPage(5, 100)->execute()->rows();

$sql->select('users.id, users.first_name, orders.total')
    ->from('users')
    ->innerJoin('orders', 'users.id', 'orders.user_id')
    ->execute()
    ->rows();
```

`join()` takes the comparison explicitly (`join('orders', '=', 'users.id',
'orders.user_id')`); `innerJoin()`, `leftJoin()` and `rightJoin()` assume `=`.

### Writing

```php
$id = $sql->insert()->into()->set(['first_name' => 'Ada', 'age' => 36])
    ->execute()->lastInsertId();

$changed = $sql->update()->set(['age' => 37])->wherePrimary($id)
    ->execute()->rowCount() > 0;

$gone = $sql->delete()->from()->wherePrimary($id)
    ->execute()->rowCount() > 0;
```

`setRaw()` is the escape hatch for an expression that must not be bound. It is
emitted verbatim, so escape it yourself. Either pass the whole assignment as one
string, or name the column and the expression separately:

```php
$sql->update()->setRaw('`views` = `views` + 1')->wherePrimary($id)->execute();
$sql->update()->setRaw('views', '`views` + 1')->wherePrimary($id)->execute();
$sql->update()->setRaw(['views' => '`views` + 1'])->wherePrimary($id)->execute();
```

`valueRaw()`/`valuesRaw()` do the same for an INSERT, where the expression
becomes the value:

```php
$sql->insert()->value('name', $name)->valueRaw('created', 'NOW()')->execute();
```

### Inspecting and error handling

```php
$sql->select()->from()->wherePrimary(1)->build();   // the SQL string, unexecuted
$sql->getLast();                                    // ['sql' => ..., 'args' => [...]]
```

By default a failure is captured rather than thrown, so you check for it:

```php
if ($sql->select('nosuchcolumn')->from()->execute()->hasError()) {
    logMsg('ERROR', $sql->error());
}
```

Call `throwExceptions(true)` to get an `orange\model\exceptions\Sql` instead.
`Crud` always does this, which is why its methods never need checking.

For a statement the builder cannot express at all, `query()` gives you the
`PDOStatement` directly:

```php
$statement = $sql->query('SELECT * FROM `main` WHERE `id` = :id', ['id' => 1]);
$row = $statement->fetchObject(UserRow::class);
```

---

## Crud — one table, by primary key

`Crud` is the shortcut for the four operations you write over and over. It
builds on `Sql` and always throws on failure.

```php
use orange\model\Crud;

$crud = new Crud(['tablename' => 'users', 'primaryColumn' => 'id'], $pdo);

$id = $crud->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'age' => 36]);

$row  = $crud->read($id);          // array, or false when missing
$rows = $crud->readAll();

$crud->update(['age' => 37], $id); // true when a row changed
$crud->delete($id);

$name = $crud->readValueById('first_name', $id);
```

### Soft deletes and active-only reads

Two config flags change what `delete()` and the reads mean, so a table with an
`is_active` column needs no special-case code:

```php
$crud = new Crud([
    'tablename' => 'users',
    'primaryColumn' => 'id',
    'activeColumn' => 'is_active',
    'deactiveOnDelete' => true,   // delete() sets is_active = 0 instead of removing
    'readOnlyActive' => true,     // read()/readAll() only see is_active = 1
], $pdo);

$crud->delete($id);        // row still there, is_active = 0
$crud->read($id);          // false - filtered out
$crud->activate($id);      // back
```

`readOnlyActive(bool)` flips it per call, for the admin screen that has to see
deactivated rows:

```php
$all = $crud->readOnlyActive(false)->readAll();
```

---

## DtoModel — validation from a Dto

`DtoModel` adds one thing to `ModelAbstract`: an operation names a Dto class,
and input is checked against it before anything reaches the database. The Dto's
attributes carry the rules, the filters, the labels *and* the column mapping
together, so the whole contract for an operation is one class.

```php
use orange\dto\Dto;
use orange\dto\attributes\Column;
use orange\dto\attributes\FieldName;
use orange\dto\attributes\IsPrimary;
use orange\dto\attributes\Label;
use orange\dto\attributes\filters\ToInteger;
use orange\dto\attributes\filters\Trim;
use orange\dto\attributes\validations\GreaterThan;
use orange\dto\attributes\validations\IsRequired;
use orange\dto\attributes\validations\MaxLength;

class CreateUserDto extends Dto
{
    // the request says "fname", the table says "first_name"
    #[IsRequired]
    #[Trim]
    #[MaxLength(32)]
    #[FieldName('fname')]
    #[Column('first_name')]
    #[Label('First Name')]
    public string $firstName;

    #[IsRequired]
    #[ToInteger]
    #[GreaterThan(17)]
    #[Column('age')]
    #[Label('Age')]
    public int $age;
}

class UpdateUserDto extends CreateUserDto
{
    // identifies the row rather than being written to it
    #[IsRequired]
    #[ToInteger]
    #[IsPrimary]
    #[Column('id')]
    public int $id;
}
```

The model maps operations to those classes. Note the two-argument constructor —
a Dto validates itself the moment it is built, so there is nothing to inject:

```php
use orange\model\DtoModel;

class UserModel extends DtoModel
{
    protected string $tablename = 'users';

    protected array $dtos = [
        'create' => CreateUserDto::class,
        'update' => UpdateUserDto::class,
    ];

    public function create(array $input): int
    {
        // validateFields() returns the db shape: keys are column names, so the
        // FieldName -> Column remapping is already applied
        return $this->crud->create($this->validateFields('create', $input));
    }

    public function update(array $input): bool
    {
        $dto = $this->requireDto('update', $input);

        // the primary belongs in the WHERE, not the SET. Naming the table takes
        // this model's share of the Dto (see below); a Dto that names no
        // table takes the name and answers with all of it
        $columns = $dto->asColumns(true, $this->tablename);

        // this table's key too - a Dto spanning several tables carries one
        // primary per table
        return $this->crud->update($columns, (int) $dto->primaryValue($this->tablename));
    }
}

$users = UserModel::newInstance([], $pdo);

$id = $users->create(['fname' => '  Ada  ', 'age' => '36']);
// stored as ['first_name' => 'Ada', 'age' => 36] - Trim and ToInteger ran first
```

### One Dto, several tables

The Dtos above name no table, so each model takes every column they carry —
the ordinary case, one Dto per model. Tag the properties and one Dto can carry
a whole form instead, with each model taking only the columns that are its own:

```php
class ProfileFormDto extends Dto
{
    #[IsRequired] #[ToInteger] #[IsPrimary]
    #[Column('id')] #[Table('users')]
    public int $id;

    #[IsRequired] #[Column('first_name')] #[Table('users')]
    public string $firstName;

    #[IsRequired] #[Column('bio')] #[Table('user_meta')]
    public string $bio;

    #[IsRequired]                                  // no table: neither model's
    public string $confirm;
}

class UserModel extends DtoModel
{
    protected string $tablename = 'users';
    protected array $dtos = ['save' => ProfileFormDto::class];
}

class UserMetaModel extends DtoModel
{
    protected string $tablename = 'user_meta';
    protected array $dtos = ['save' => ProfileFormDto::class];
}

$users->validateFields('save', $input);      // ['id' => 7, 'first_name' => 'Ada']
$userMeta->validateFields('save', $input);   // ['bio' => 'Engineer']
```

An untagged property (`$confirm`) belongs to no table, so it reaches neither
insert — which is what you want for a field that validates but never persists.
`$withoutPrimary` drops the primary from its own table only, so a second table
keeping its own `id` column keeps it.

A Dto that names tables **requires** the name: `asColumns()` with no table — or
with one the Dto does not name — throws a `LogicException`, because "every
column" is not an answer any one of several tables can use and a name it has
not got is a typo. Ask `$dto->tables()` to see which case a Dto is — `null`
means it names none, and takes any name you give it.

Each table carries its own `#[IsPrimary]`, so ask for the key by table too —
`$dto->primaryValue($this->tablename)`. Unqualified, `primaryValue()` on a Dto
with more than one primary throws rather than answering with another table's
key. Note `Crud`/`Sql::wherePrimary()` are single-column, so a genuinely
**compound** key (two `#[IsPrimary]` in one table) can be described by a Dto but
not yet consumed by `Crud` — build that WHERE yourself from `primaryValues()`.

If a Dto names tables and none of them is this model's — a mistyped `#[Table]`,
or the wrong Dto registered for the operation — `validateFields()` throws
`orange\model\exceptions\Model`, naming the model that was asking and the
tables the Dto does describe. (`asColumns()` would throw a `LogicException` on
its own; the model checks first only to put itself in the message.)

### Failing validation

Two doors, depending on whether a rejection is an expected outcome:

```php
use orange\model\exceptions\DtoValidationFailed;

// requireDto()/validateFields() throw
try {
    $users->create(['fname' => '', 'age' => 12]);
} catch (DtoValidationFailed $e) {
    $e->getErrors();    // ['fname' => ['First Name is required'], 'age' => [...]]
    $e->getKeys();      // ['fname', 'age']
    $e->getHttpCode();  // 406
    $e->getOutput();    // the JSON body the framework's handler sends
}

// makeDto() never throws - a rejection is just the answer
$dto = $users->makeDto('create', $input);

if (!$dto->isValid()) {
    return $this->response('errors', $dto->allErrors());
}

$id = $users->crud->create($dto->asColumns());
```

`allErrors()` is the flattened export, dot-keyed through nested dto-arrays
(`'lines.1.sku' => [...]`) — the shape to hand an API client. `errors()` rolls
child failures up into one message per parent field instead.
