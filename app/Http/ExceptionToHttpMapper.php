<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\DisputeResolutionConflictException;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\EscrowReleaseConflictException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidDisputeStateTransitionException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Domain\Exceptions\InvalidEscrowStateTransitionException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Domain\Exceptions\ProductValidationFailedException;
use App\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Domain\Exceptions\WalletNotFoundException;
use App\Domain\Exceptions\WithdrawalValidationFailedException;
use App\Http\Auth\AuthenticationRequiredException;
use App\Http\Validation\ValidationFailedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

final class ExceptionToHttpMapper
{
    public static function map(\Throwable $e): Response
    {
        if ($e instanceof AuthenticationRequiredException) {
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($e instanceof ResourceNotFoundException) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The requested route was not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($e instanceof MethodNotAllowedException) {
            return new JsonResponse([
                'error' => 'method_not_allowed',
                'message' => $e->getMessage(),
            ], Response::HTTP_METHOD_NOT_ALLOWED, [
                'Allow' => implode(', ', $e->getAllowedMethods()),
            ]);
        }

        if ($e instanceof ValidationFailedException) {
            return new JsonResponse([
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'violations' => $e->errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof DomainAuthorizationDeniedException) {
            return new JsonResponse([
                'error' => 'forbidden',
                'message' => $e->getMessage(),
                'action' => $e->action,
                'actor_user_id' => $e->actorUserId,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($e instanceof IdempotencyConflictException) {
            return new JsonResponse([
                'error' => 'idempotency_conflict',
                'message' => $e->getMessage(),
                'idempotency_key' => $e->idempotencyKey,
                'scope' => $e->scope,
            ], Response::HTTP_CONFLICT);
        }

        if ($e instanceof InsufficientWalletBalanceException) {
            return new JsonResponse([
                'error' => 'insufficient_balance',
                'message' => $e->getMessage(),
                'wallet_id' => $e->walletId,
                'currency' => $e->currency,
                'requested_amount' => $e->requestedAmount,
                'available_amount' => $e->availableAmount,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof WalletNotFoundException) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($e instanceof OrderValidationFailedException) {
            $status = self::orderValidationStatus($e);

            return new JsonResponse([
                'error' => $status === Response::HTTP_NOT_FOUND ? 'not_found' : 'validation_failed',
                'message' => $e->getMessage(),
                'reason_code' => $e->reasonCode,
                'order_id' => $e->orderId,
                'violations' => $e->violations,
            ], $status);
        }

        if ($e instanceof WithdrawalValidationFailedException) {
            $status = self::withdrawalValidationStatus($e);

            return new JsonResponse([
                'error' => $status === Response::HTTP_NOT_FOUND ? 'not_found' : 'validation_failed',
                'message' => $e->getMessage(),
                'reason_code' => $e->reasonCode,
                'withdrawal_request_id' => $e->withdrawalRequestId,
                'violations' => $e->violations,
            ], $status);
        }

        if ($e instanceof AuthValidationFailedException) {
            $status = self::authValidationStatus($e);
            $error = match ($e->reasonCode) {
                'invalid_credentials', 'invalid_refresh_token' => 'unauthenticated',
                'email_taken', 'phone_taken' => 'conflict',
                'account_inactive' => 'forbidden',
                'user_not_found', 'seller_profile_not_found' => 'not_found',
                default => $status === Response::HTTP_UNAUTHORIZED ? 'unauthenticated' : 'validation_failed',
            };

            return new JsonResponse([
                'error' => $error,
                'message' => $e->getMessage(),
                'reason_code' => $e->reasonCode,
                'violations' => $e->violations,
            ], $status);
        }

        if ($e instanceof ProductValidationFailedException) {
            $status = self::productValidationStatus($e);

            return new JsonResponse([
                'error' => $status === Response::HTTP_NOT_FOUND ? 'not_found' : 'validation_failed',
                'message' => $e->getMessage(),
                'reason_code' => $e->reasonCode,
                'product_id' => $e->productId,
                'violations' => $e->violations,
            ], $status);
        }

        if ($e instanceof DisputeResolutionConflictException) {
            $status = self::disputeConflictStatus($e);

            return new JsonResponse([
                'error' => self::disputeConflictErrorKey($status),
                'message' => $e->getMessage(),
                'reason_code' => $e->reasonCode,
                'dispute_case_id' => $e->disputeCaseId,
            ], $status);
        }

        if ($e instanceof EscrowReleaseConflictException) {
            return new JsonResponse([
                'error' => 'conflict',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        if ($e instanceof InvalidOrderStateTransitionException
            || $e instanceof InvalidDisputeStateTransitionException
            || $e instanceof InvalidEscrowStateTransitionException
            || $e instanceof InvalidDomainStateTransitionException) {
            return new JsonResponse([
                'error' => 'invalid_state_transition',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        if ($e instanceof InvalidLedgerOperationException || $e instanceof WalletCurrencyMismatchException) {
            return new JsonResponse([
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof DomainException) {
            return new JsonResponse([
                'error' => 'domain_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof \LogicException) {
            return new JsonResponse([
                'error' => 'not_implemented',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_IMPLEMENTED);
        }

        return new JsonResponse([
            'error' => 'internal_error',
            'message' => 'An unexpected error occurred.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private static function orderValidationStatus(OrderValidationFailedException $e): int
    {
        return match ($e->reasonCode) {
            'order_not_found', 'payment_transaction_not_found', 'payment_intent_not_found', 'actor_user_not_found' => Response::HTTP_NOT_FOUND,
            'payment_mutation_actor_required' => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    private static function withdrawalValidationStatus(WithdrawalValidationFailedException $e): int
    {
        return match ($e->reasonCode) {
            'withdrawal_request_not_found', 'seller_profile_not_found' => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    private static function disputeConflictStatus(DisputeResolutionConflictException $e): int
    {
        return match ($e->reasonCode) {
            'dispute_case_not_found' => Response::HTTP_NOT_FOUND,
            'dispute_already_resolved_differently', 'active_dispute_exists_for_order' => Response::HTTP_CONFLICT,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    private static function disputeConflictErrorKey(int $status): string
    {
        return match ($status) {
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_CONFLICT => 'conflict',
            default => 'validation_failed',
        };
    }

    private static function authValidationStatus(AuthValidationFailedException $e): int
    {
        return match ($e->reasonCode) {
            'invalid_credentials', 'invalid_refresh_token' => Response::HTTP_UNAUTHORIZED,
            'email_taken', 'phone_taken' => Response::HTTP_CONFLICT,
            'account_inactive' => Response::HTTP_FORBIDDEN,
            'user_not_found', 'seller_profile_not_found' => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    private static function productValidationStatus(ProductValidationFailedException $e): int
    {
        return match ($e->reasonCode) {
            'product_not_found' => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }
}
