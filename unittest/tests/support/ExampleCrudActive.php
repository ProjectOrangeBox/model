<?php

declare(strict_types=1);

use orange\model\ModelAbstract;

/**
 * The soft-delete flavour of ExampleCrud, on ModelAbstract for the same reason.
 */
class ExampleCrudActive extends ModelAbstract
{
    // required in extending class
    protected string $tablename = 'crud';
    protected string $primaryColumn = 'id';
    protected string $activeColumn = 'is_active';
    protected bool $deactiveOnDelete = true;
    protected bool $readOnlyActive = true;

    protected function __construct(array $config, PDO $pdo)
    {
        parent::__construct($config, $pdo);

        $this->crud->activeColumn = $this->activeColumn;
        $this->crud->deactiveOnDelete = $this->deactiveOnDelete;
        $this->crud->readOnlyActive = $this->readOnlyActive;
    }

    public function read(int $id): array|bool
    {
        return $this->crud->read($id);
    }

    public function readAll(): array|bool
    {
        return $this->crud->readAll();
    }

    public function create(string $lastname, string $firstname, int $age): int
    {
        return $this->crud->create(['first_name' => $firstname, 'last_name' => $lastname, 'age' => $age]);
    }

    public function update(array $columnValues, int $id): bool
    {
        return $this->crud->update($columnValues, $id);
    }

    public function delete(int $id): bool
    {
        return $this->crud->delete($id);
    }

    public function activate(int $id): bool
    {
        return $this->crud->activate($id);
    }

    public function deactivate(int $id): bool
    {
        return $this->crud->deactivate($id);
    }

    public function readOnlyActive(bool $bool): self
    {
        $this->crud->readOnlyActive = $bool;

        return $this;
    }
}
