<?php

namespace Tunnela\TremedCodingChallenge\App\Controllers;

class SetupController
{
    public function __invoke($app, $database, $data) 
    {
        $errors = [];

        foreach (['email', 'password'] as $field) {
            if (!empty($data->{$field})) {
                continue;
            }
            $errors[] = 'Value for field `' . $field . '` is missing.';
        }
        if ($errors) {
            return $app->json(['error' => implode(' ', $errors)], 422);
        }
        try {
            $database->beginTransaction();

            $database->query('
                CREATE TABLE IF NOT EXISTS "users" (
                    "id" INTEGER,
                    "first_name" TEXT,
                    "last_name" TEXT,
                    "email" TEXT NOT NULL UNIQUE,
                    "phone" TEXT,
                    "password" TEXT NOT NULL,
                    "updated_at" INTEGER NOT NULL,
                    "created_at" INTEGER NOT NULL,
                    PRIMARY KEY("id" AUTOINCREMENT)
                )
            ');

            $database->query('
                CREATE TABLE IF NOT EXISTS "auth_tokens" (
                    "id" INTEGER,
                    "user_id" INTEGER,
                    "access_token" TEXT NOT NULL,
                    "refresh_token" TEXT NOT NULL,
                    "expires" INTEGER NOT NULL,
                    "created_at" INTEGER NOT NULL,
                    PRIMARY KEY("id" AUTOINCREMENT)
                )
            ');

            $now = time();

            $database->prepare('
                INSERT INTO users (email, password, updated_at, created_at) 
                VALUES (?, ?, ?, ?)
            ')
            ->execute([
                $data->email,
                password_hash($data->password, PASSWORD_BCRYPT),
                $now,
                $now
            ]);

            $userId = 1;
            $accessToken = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $expires = $now + 30 * 24 * 60 * 60; // 1 month

            $database->prepare('
                INSERT INTO auth_tokens (user_id, access_token, refresh_token, expires, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ')
            ->execute([
                $userId,
                $accessToken,
                $refreshToken,
                $expires,
                $now
            ]);

            $database->commit();

        } catch (\Exception $e) {
            return $app->json(['error' => $e->getMessage()], 422);
        }
        setcookie(
            'access_token',
            $accessToken,
            [
                'samesite' => 'Strict',
                'expires' => $expires,
                'httponly' => true,
                'path' => '/api/'
            ],
        );

        setcookie(
            'refresh_token',
            $refreshToken,
            [
                'samesite' => 'Strict',
                'expires' => $expires,
                'httponly' => true,
                'path' => '/api/auth/refresh'
            ],
        );
        
        return $app->json([
            'user_id' => $userId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires' => $expires
        ], 201);
    }
}