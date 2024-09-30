<?php

namespace Tunnela\TremedCodingChallenge\App\Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UsersController
{
    public function index($app, $database)
    {
        $users = [];

        try {
            $statement = $database->prepare('
                SELECT * FROM users LIMIT 100
            ');

            $statement->execute();

            $users = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return $app->json(['error' => $e->getMessage()], 422);
        }
        return $app->json(
            array_map(function($user) {
                unset($user['password']);
                
                return $user;
            }, $users)
        );
    }

    public function create($app, $database, $data) 
    {
        $errors = [];

        foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
            if (!empty($data->{$field})) {
                continue;
            }
            $errors[] = 'Value for field `' . $field . '` is missing.';
        }
        if ($errors) {
            return $app->json(['error' => implode(' ', $errors)], 422);
        }
        $now = time();
        $user = [];

        try {
            $database->beginTransaction();

            $database->prepare('
                INSERT INTO users (first_name, last_name, email, phone, password, updated_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ')
            ->execute([
                $data->first_name,
                $data->last_name,
                $data->email,
                $data->phone ?? null,
                password_hash($data->password, PASSWORD_BCRYPT),
                $now,
                $now
            ]);

            $statement = $database->prepare('
                SELECT * FROM users WHERE id = ?
            ');

            $statement->execute([$database->lastInsertId()]);

            $user = $statement->fetch(\PDO::FETCH_ASSOC);

            // Usually a bad idea. Instead we should have a unique,
            // one-time link which they can use to login.
            $this->mailCredentials($data->email, $data->password);

            $database->commit();

        } catch (\Exception $e) {
            return $app->json(['error' => $e->getMessage()], 422);
        }
        unset($user['password']);

        return $app->json($user, 201);
    }

    protected function mailCredentials($email, $password)
    {
        if (!getenv('SMTP_HOST')) {
            return;
        }
        $link = getenv('APP_DOMAIN') . '/login';
        $title = 'New user account';
        $message = 'A new user account has been created for you:' . "<br><br>" .
        'Email: ' . $email . "<br>" .
        'Password: ' . $password . "<br><br>" .
        'You can login at <a href="' . $link . '">' . $link . '</a>';

        $mail = new PHPMailer(true);

        $mail->IsSMTP();
        $mail->CharSet = 'UTF-8';

        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = !!filter_var(
            getenv('SMTP_AUTH'),
            FILTER_VALIDATE_BOOLEAN
        );
        $mail->SMTPSecure = getenv('SMTP_SECURE');

        $mail->Port = +(getenv('SMTP_PORT') ?: 25); 
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');

        $mail->setFrom(getenv('MAIL_FROM'));
        $mail->addAddress($email);

        $mail->isHTML(true);   

        $mail->Subject = $title;
        $mail->Body = $message;
        $mail->AltBody = $message;

        $mail->send();
    }

    public function delete($id, $app, $database)
    {
        $users = [];

        try {
            $statement = $database->prepare('
                DELETE FROM users WHERE id = ?
            ');

            $statement->execute([$id]);
        } catch (\Exception $e) {
            return $app->json(['error' => $e->getMessage()], 422);
        }
        return $app->json([], 204);
    }

    public function update($id, $app, $data, $database)
    {
        $user = [];
        $now = time();

        try {
            if ($data->password) {
                $statement = $database->prepare('
                    UPDATE users SET first_name = ?, last_name = ?, 
                    email = ?, phone = ?, password = ?, 
                    updated_at = ? WHERE id = ?
                ')
                ->execute([
                    $data->first_name,
                    $data->last_name,
                    $data->email,
                    $data->phone ?? null,
                    password_hash($data->password, PASSWORD_BCRYPT),
                    $now,
                    $id
                ]);
            } else {
                $statement = $database->prepare('
                    UPDATE users SET first_name = ?, last_name = ?, 
                    email = ?, phone = ?, 
                    updated_at = ? WHERE id = ?
                ')
                ->execute([
                    $data->first_name,
                    $data->last_name,
                    $data->email,
                    $data->phone ?? null,
                    $now,
                    $id
                ]);
            }
            $statement = $database->prepare('SELECT * FROM users WHERE id = ?');

            $statement->execute([$id]);

            $user = $statement->fetch(\PDO::FETCH_ASSOC);

            unset($user['password']);

        } catch (\Exception $e) {
            return $app->json(['error' => $e->getMessage()], 422);
        }
        return $app->json($user, 200);
    }
}