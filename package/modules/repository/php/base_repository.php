<?php

declare(strict_types=1);

namespace __COMPONENT__\local\clapi\repository;

use moodle_database;

abstract class base_repository
{
    public function __construct(
        protected readonly moodle_database $db,
    ) {
    }

    protected function db(): moodle_database
    {
        return $this->db;
    }
}
