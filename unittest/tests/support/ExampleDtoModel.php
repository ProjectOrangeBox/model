<?php

declare(strict_types=1);

use orange\dto\Dto;
use orange\model\DtoModel;
use orange\model\exceptions\Model as ModelException;
use orange\dto\attributes\Column;
use orange\dto\attributes\Label;
use orange\dto\attributes\Table;
use orange\dto\attributes\IsPrimary;
use orange\dto\attributes\FieldName;
use orange\dto\attributes\filters\Trim;
use orange\dto\attributes\filters\ToInteger;
use orange\dto\attributes\validations\Integer;
use orange\dto\attributes\validations\LessThan;
use orange\dto\attributes\validations\MaxLength;
use orange\dto\attributes\validations\IsRequired;
use orange\dto\attributes\validations\GreaterThan;

/**
 * The create contract: no id, it is assigned by the insert.
 *
 * fname/lname show the point of #[FieldName] + #[Column] - the request speaks
 * one name and the table another, and the model never has to know.
 */
class CreateUserDto extends Dto
{
    #[IsRequired]
    #[Trim]
    #[MaxLength(32)]
    #[FieldName('fname')]
    #[Column('first_name')]
    #[Table('crud')]
    #[Label('First Name')]
    public string $firstName;

    #[IsRequired]
    #[Trim]
    #[MaxLength(32)]
    #[FieldName('lname')]
    #[Column('last_name')]
    #[Table('crud')]
    #[Label('Last Name')]
    public string $lastName;

    #[IsRequired]
    #[ToInteger]
    #[Integer]
    #[GreaterThan(17)]
    #[LessThan(111)]
    #[Column('age')]
    #[Table('crud')]
    #[Label('Age')]
    public int $age;
}

/**
 * The update contract: same fields plus the primary, which identifies the row
 * rather than being written to it - hence #[IsPrimary].
 */
class UpdateUserDto extends CreateUserDto
{
    #[IsRequired]
    #[ToInteger]
    #[Integer]
    #[IsPrimary]
    #[Column('id')]
    #[Table('crud')]
    #[Label('Id')]
    public int $id;
}

class ExampleDtoModel extends DtoModel
{
    protected string $tablename = 'crud';
    protected string $primaryColumn = 'id';

    protected array $dtos = [
        'create' => CreateUserDto::class,
        'update' => UpdateUserDto::class,
    ];

    public function create(array $input): int
    {
        return $this->crud->create($this->validateFields('create', $input));
    }

    public function update(array $input): bool
    {
        $dto = $this->requireDto('update', $input);

        // the primary identifies the row, so it belongs in the WHERE and not in
        // the SET - the first argument drops it for us. Naming the table takes
        // this model's share of a Dto that spans several, and the last argument
        // says an untagged Dto is all ours - the same ask validateFields() makes
        $columns = $dto->asColumns(true, $this->tablename, true);

        // null means the Dto names tables and none of them is ours
        if ($columns === null) {
            throw new ModelException($dto::class . ' has no columns for table "' . $this->tablename . '".');
        }

        return $this->crud->update($columns, (int) $dto->primaryValue());
    }

    public function read(int $id): array|bool
    {
        return $this->crud->read($id);
    }
}
