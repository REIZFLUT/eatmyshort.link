<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * The login identifier must be treated the same way on every driver: e-mails are stored
 * trimmed and lowercased, so a mixed-case (or padded) input has to resolve. `=` is exact
 * on SQLite but case-insensitive on MariaDB/MySQL, hence the explicit normalisation.
 */
final class LoginIdentifierTest extends AppTestCase
{
    public function testLoginAcceptsAMixedCasePaddedEmail(): void
    {
        $this->createUser('demo@example.com');

        $this->login(' Demo@Example.COM ', self::PASSWORD);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');
    }

    public function testUnknownAddressStillFails(): void
    {
        $this->createUser('demo@example.com');

        $this->login('ghost@example.com', self::PASSWORD);
        $this->client->followRedirect();

        self::assertSelectorExists('.errors', 'an unknown address is still refused');
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}
