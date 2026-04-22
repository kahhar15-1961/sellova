<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Http\Auth\AuthenticationRequiredException;
use App\Http\ExceptionToHttpMapper;
use App\Http\Validation\ValidationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

final class ExceptionMapperTest extends TestCase
{
    public function test_maps_authentication_required_to_401(): void
    {
        $r = ExceptionToHttpMapper::map(new AuthenticationRequiredException());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $r->getStatusCode());
    }

    public function test_maps_resource_not_found_to_404(): void
    {
        $r = ExceptionToHttpMapper::map(new ResourceNotFoundException());
        self::assertSame(Response::HTTP_NOT_FOUND, $r->getStatusCode());
    }

    public function test_maps_method_not_allowed_to_405(): void
    {
        $r = ExceptionToHttpMapper::map(new MethodNotAllowedException(['GET', 'HEAD']));
        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $r->getStatusCode());
        self::assertSame('GET, HEAD', $r->headers->get('Allow'));
    }

    public function test_maps_order_not_found_reason_to_404(): void
    {
        $r = ExceptionToHttpMapper::map(new OrderValidationFailedException(9, 'order_not_found', []));
        self::assertSame(Response::HTTP_NOT_FOUND, $r->getStatusCode());
    }

    public function test_maps_idempotency_conflict_to_409(): void
    {
        $r = ExceptionToHttpMapper::map(new IdempotencyConflictException('k', 'scope'));
        self::assertSame(Response::HTTP_CONFLICT, $r->getStatusCode());
    }

    public function test_maps_authorization_denied_to_403(): void
    {
        $r = ExceptionToHttpMapper::map(new DomainAuthorizationDeniedException('x', 1));
        self::assertSame(Response::HTTP_FORBIDDEN, $r->getStatusCode());
    }

    public function test_maps_validation_failed_exception(): void
    {
        $r = ExceptionToHttpMapper::map(new ValidationFailedException([
            ['field' => 'email', 'message' => 'Invalid.'],
        ]));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $r->getStatusCode());
    }
}
