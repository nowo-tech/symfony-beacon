<?php

declare(strict_types=1);

namespace App\Identity\Routing;

use App\Identity\Controller\MagicLoginConfirmController;
use Nowo\AuthKitBundle\Routing\AuthKitRouteLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Routing\RouteCollection;

/**
 * Allows POST on AuthKit magic-login check routes and swaps in the confirm interstitial controller.
 */
#[AsDecorator(decorates: AuthKitRouteLoader::class)]
final class MagicLoginCheckRouteLoaderDecorator implements LoaderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly LoaderInterface $inner,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        /** @var RouteCollection $collection */
        $collection = $this->inner->load($resource, $type);

        foreach ($collection->all() as $name => $route) {
            if (!str_starts_with((string) $name, 'nowo_auth_kit_magic_login_check')) {
                continue;
            }
            $route->setMethods(['GET', 'POST']);
            $route->setDefault('_controller', MagicLoginConfirmController::class.'::check');
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }
}
