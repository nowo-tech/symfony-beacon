<?php

declare(strict_types=1);

namespace App\Issues\Service;

use Symfony\Component\Mime\Email;

/**
 * Narrow mail seam for issue collaboration emails (testable without mocking final ConfiguredMailer).
 */
interface IssueUserMailTransport
{
    public function isAvailable(): bool;

    public function getFromAddress(): string;

    public function send(Email $email): void;
}
