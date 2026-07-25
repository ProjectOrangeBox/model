<?php

declare(strict_types=1);

/**
 * Builds the throwaway database the model tests run against.
 *
 * In-memory SQLite: no server, no credentials, nothing to provision, and each
 * connection gets a private database that dies with it - so every test method
 * starts from the same seeded state instead of having to undo the previous one.
 *
 * The schema mirrors the MySQL one in setup.sql, which stays the reference for
 * what these tables look like in production.
 */
class TestDatabase
{
    /**
     * A fresh in-memory SQLite database, seeded from setup.sqlite.sql.
     */
    public static function sqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // SQLite's exec() runs every statement in the file, so the schema and
        // its seed rows load in one call
        $pdo->exec((string) file_get_contents(__DIR__ . '/setup.sqlite.sql'));

        return $pdo;
    }
}
