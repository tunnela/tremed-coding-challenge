<?php

namespace Tunnela\TremedCodingChallenge\App\Controllers;

class AuthController
{
    public function state($app) 
    {
        return $app->json(['state' => $this->createNonce()], 201);
    }

    public function delete($app) 
    {
        if (isset($_COOKIE['access_token'])) {
            unset($_COOKIE['access_token']);
        }
        if (isset($_COOKIE['refresh_token'])) {
            unset($_COOKIE['refresh_token']);
        }
        setcookie(
            'access_token',
            null,
            [
                'samesite' => 'Strict',
                'expires' => time() - 3600,
                'httponly' => true,
                'path' => '/api/'
            ],
        );

        setcookie(
            'refresh_token',
            null,
            [
                'samesite' => 'Strict',
                'expires' => time() - 3600,
                'httponly' => true,
                'path' => '/api/auth/refresh'
            ],
        );

        return $app->json([], 204);
    }

    public function create($app, $database, $data) 
    {
        $nonce = $_COOKIE['nonce'] ?? null;

        $this->deleteNonce();

        // Additional CSRF/XSS/MITM protection
        if (!$nonce || !hash_equals($data->state, $nonce)) {
            return $app->json(['error' => '`state` does not match `nonce`.'], 403);
        }
        $statement = $database->prepare('SELECT * FROM users WHERE email = ?');

        $statement->execute([$data->email]);

        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!password_verify($data->password, $user['password'])) {
            return $app->json(['error' => 'Invalid email/password combo.'], 401);
        }
        unset($user['password']);

        return $this->createToken($user, $app, $database);
    }

    protected function createNonce($expires = 10 * 60)
    {
        $nonce = bin2hex(random_bytes(32));

        // Additional CSRF/XSS/MITM protection
        setcookie(
            'nonce',
            $nonce,
            [
                'samesite' => 'Strict',
                'expires' => time() + $expires, // 10 mins
                'httponly' => true,
                'path' => '/api/auth/'
            ],
        );

        return $nonce;
    }

    protected function deleteNonce()
    {
        if (!isset($_COOKIE['nonce'])) {
            return;
        }
        unset($_COOKIE['nonce']);

        setcookie(
            'nonce',
            null,
            [
                'samesite' => 'Strict',
                'expires' => time() - 3600,
                'httponly' => true,
                'path' => '/api/auth/'
            ],
        );
    }

    protected function createToken($user, $app, $database)
    {
        $now = time();
        $userId = $user['id'];
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));
        $expires = $now + 30 * 24 * 60 * 60; // 1 month

        try {
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
        } catch (\Exception $e) {
            return $app->json(['error' => 'Can\'t auth right now!'], 401);
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

        return $app->json(
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires' => $expires
            ] + $user, 
            201
        );
    }
}