<?php

declare(strict_types=1);

use orange\model\ModelAbstract;

/**
 * Hand-written queries and nothing else, so it extends ModelAbstract rather
 * than DtoModel - there is no per-operation contract here to check input against.
 */
class ExampleModel extends ModelAbstract
{
    // required in extending class
    protected string $tablename = 'main';
    protected string $primaryColumn = 'id';

    public function getUser(int $id)
    {
        return $this->crud->read($id);
    }

    public function getUserDetailed(int $id)
    {
        $record = [];

        $dbc = $this->sql->select('main.id, first_name, last_name, join.id as jid, child_name')->where('main.id', '=', $id)->innerJoin('join', 'main.id', 'join.parent_id')->execute()->rows();

        if ($dbc) {
            foreach ($dbc as $record) {
                $child[] = [
                    'id' => $record['jid'],
                    'child_name' => $record['child_name'],
                ];
            }

            $record = [
                'fname' => $record['first_name'],
                'lname' => $record['last_name'],
                'childern' => $child,
            ];
        }

        return $record;
    }
}
