<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Service\AccountSocialAccounts;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class AccountSocialAccountsTest extends TestCase
{
    public function testSocialLoginDisabledWhenProfileModeDisabled(): void
    {
        $accounts = new AccountSocialAccounts(
            $this->gate(mode: 'disabled'),
            $this->createStub(SocialLoginAccountRepository::class),
        );

        self::assertFalse($accounts->isSocialLoginEnabled());
    }

    public function testSocialLoginEnabledWhenModeOnAndCredentialsExist(): void
    {
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findEnabledOrdered')->willReturn([new stdClass()]);

        $accounts = new AccountSocialAccounts(
            $this->gate(mode: 'enabled', credentials: $credentials),
            $this->createStub(SocialLoginAccountRepository::class),
        );

        self::assertTrue($accounts->isSocialLoginEnabled());
    }

    public function testLinkedForReturnsEmptyWhenUserHasNoId(): void
    {
        $repo = $this->createMock(SocialLoginAccountRepository::class);
        $repo->expects(self::never())->method('findBy');

        $accounts = new AccountSocialAccounts($this->gate(mode: 'disabled'), $repo);
        $user = new User();
        $user->setEmail('fresh@example.com');

        self::assertSame([], $accounts->linkedFor($user));
    }

    public function testLinkedForQueriesRepository(): void
    {
        $user = new User();
        $user->setEmail('linked@example.com');
        $idProp = new ReflectionProperty(User::class, 'id');
        $idProp->setValue($user, 42);

        $row = new SocialLoginAccount();
        $row->setProvider('github');

        $repo = $this->createStub(SocialLoginAccountRepository::class);
        $repo->method('findBy')->willReturnCallback(
            static function (array $criteria, array $order) use ($row): array {
                self::assertSame(User::class, $criteria['userClass']);
                self::assertSame('42', $criteria['userId']);
                self::assertSame(['provider' => 'ASC', 'id' => 'ASC'], $order);

                return [$row];
            },
        );

        $accounts = new AccountSocialAccounts($this->gate(mode: 'disabled'), $repo);

        self::assertSame([$row], $accounts->linkedFor($user));
    }

    private function gate(
        string $mode,
        ?SocialLoginCredentialRepository $credentials = null,
    ): SocialLoginGate {
        if (!$credentials instanceof SocialLoginCredentialRepository) {
            $credentials = $this->createStub(SocialLoginCredentialRepository::class);
            $credentials->method('findEnabledOrdered')->willReturn([]);
        }

        $registry = new ProfileRegistry([
            'main' => [
                'user_class' => User::class,
                'user_identifier_field' => 'email',
                'registration_role' => 'ROLE_USER',
                'registration_mode' => 'first_user_only',
                'login_fields' => [],
                'remember_me' => ['enabled' => false],
                'password_strength' => [],
                'registration_fields' => [],
                'templates' => [],
                'css' => ['button_class' => 'a', 'secondary_button_class' => 'b'],
                'embed' => ['mode' => 'disabled'],
                'password_reset' => [],
                'magic_login' => [],
                'social_login' => [
                    'mode' => $mode,
                    'create_user_if_missing' => true,
                    'require_verified_email' => true,
                ],
                'qr_login' => ['mode' => 'disabled'],
                'routes' => [
                    'login' => ['path' => '/login', 'name' => 'l'],
                    'logout' => ['path' => '/logout', 'name' => 'lo'],
                    'register' => ['path' => '/register', 'name' => 'r'],
                    'reset_password_request' => ['path' => '/r', 'name' => 'rp'],
                    'reset_password' => ['path' => '/rr', 'name' => 'rrr'],
                    'reset_password_code' => ['path' => '/rc', 'name' => 'rc'],
                    'magic_login_request' => ['path' => '/m', 'name' => 'm'],
                    'magic_login_check' => ['path' => '/mc', 'name' => 'mc'],
                    'social_login_start' => ['path' => '/s', 'name' => 's'],
                    'social_login_check' => ['path' => '/sc', 'name' => 'sc'],
                    'qr_login_start' => ['path' => '/q', 'name' => 'q'],
                    'qr_login_show' => ['path' => '/qs', 'name' => 'qs'],
                    'qr_login_status' => ['path' => '/qst', 'name' => 'qst'],
                    'qr_login_complete' => ['path' => '/qc', 'name' => 'qc'],
                    'qr_login_approve' => ['path' => '/qa', 'name' => 'qa'],
                    'qr_login_deny' => ['path' => '/qd', 'name' => 'qd'],
                ],
                'firewall' => 'main',
                'login_success_route' => 'home',
            ],
        ], 'main');

        return new SocialLoginGate($registry, $credentials);
    }
}
