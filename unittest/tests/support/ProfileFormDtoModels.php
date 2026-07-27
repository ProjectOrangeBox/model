<?php

declare(strict_types=1);

use orange\dto\Dto;
use orange\model\DtoModel;
use orange\dto\attributes\Table;
use orange\dto\attributes\Column;
use orange\dto\attributes\IsPrimary;
use orange\dto\attributes\filters\ToInteger;
use orange\dto\attributes\validations\Integer;
use orange\dto\attributes\validations\IsRequired;

/**
 * One form spanning two tables: the profile fields belong to `main`, the child
 * name to `join`, and the confirmation to neither.
 *
 * Handed to both models below - each takes the columns tagged for its own
 * table and never sees the other's.
 */
class ProfileFormDto extends Dto
{
    #[IsRequired]
    #[ToInteger]
    #[Integer]
    #[IsPrimary]
    #[Column('id')]
    #[Table('main')]
    public int $id;

    #[IsRequired]
    #[Column('first_name')]
    #[Table('main')]
    public string $firstName;

    // the join side carries its own key, so each model has one
    #[IsRequired]
    #[ToInteger]
    #[Integer]
    #[IsPrimary]
    #[Column('id')]
    #[Table('join')]
    public int $childId;

    #[IsRequired]
    #[Column('child_name')]
    #[Table('join')]
    public string $childName;

    // no #[Table]: the form carries it, neither table stores it
    #[IsRequired]
    public string $confirm;
}

class MainProfileModel extends DtoModel
{
    protected string $tablename = 'main';

    protected array $dtos = [
        'save' => ProfileFormDto::class,
    ];
}

class JoinProfileModel extends DtoModel
{
    protected string $tablename = 'join';

    protected array $dtos = [
        'save' => ProfileFormDto::class,
    ];
}

/**
 * The single-model case: written for one table, so it never bothers naming it.
 * Every column it has belongs to whichever model holds it.
 */
class TokenDto extends Dto
{
    #[IsRequired]
    #[Column('token')]
    public string $token;

    #[IsRequired]
    #[ToInteger]
    #[Integer]
    #[Column('user_id')]
    public int $userId;
}

class TokenModel extends DtoModel
{
    protected string $tablename = 'tokens';

    protected array $dtos = [
        'create' => TokenDto::class,
    ];
}
