<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__ . '/TestHelper/Constants.php';
require_once __DIR__ . '/TestHelper/DataGenerator/PayloadMutator.php';

uses(TestCase::class)->in('Feature', 'Unit', '../modules/*/tests/Feature', '../modules/*/tests/Unit');
uses(DatabaseTransactions::class)->in('Feature', 'Unit', '../modules/*/tests/Feature', '../modules/*/tests/Unit');

/**
 * Helper to test authorized access for various HTTP methods.
 * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE).
 * @param string $url The endpoint URL to test.
 * @param array $payload The payload to send with the request (for POST, PUT, PATCH).
 * @throws Exception If an error occurs during the request.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */

function authorizedUserAssertion(string $method, string $url, array $payload = []): \Illuminate\Testing\TestResponse {
    try {
        $response = match (strtolower($method)) {
            'get' => test()->getJson($url),
            'post' => test()->postJson($url, $payload),
            'put' => test()->putJson($url, $payload),
            'patch' => test()->patchJson($url, $payload),
            'delete' => test()->deleteJson($url, $payload),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        expect($response->getStatusCode())->toBeIn([200, 201]);

        return $response;
    } catch (InvalidArgumentException $e) {
        throw new Exception($e->getMessage(), 0, $e);
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}


/**
 * Helper to test unauthorized access for various HTTP methods.
 * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE).
 * @param string $url The endpoint URL to test.
 * @param string $connection The database connection name to check for active transactions.
 * @param array $payload The payload to send with the request (for POST, PUT, PATCH).
 * @throws Exception If an error occurs during the request or transaction handling.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */
function unauthorizedUserAssertion(string $method, string $url, string $connection, array $payload = []): \Illuminate\Testing\TestResponse {
    try {
        $response = match (strtolower($method)) {
            'get' => test()->getJson($url),
            'post' => test()->postJson($url, $payload),
            'put' => test()->putJson($url, $payload),
            'patch' => test()->patchJson($url, $payload),
            'delete' => test()->deleteJson($url),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        $responseData = $response->getData(true);
        expect($response->getStatusCode())->toBeIn([401, 403, 404, 500]);
        expect($responseData)->toHaveKey('message');
        expect($responseData)->not->toHaveKey('data');

        // Safely rollback DB if the controller does not rollback safely
        if (DB::connection($connection)->transactionLevel() > 0) {
            DB::connection($connection)->rollBack();
        }

        return $response;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}


/**
 * Helper to test unauthenticated access for various HTTP methods.
 * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE).
 * @param string $url The endpoint URL to test.
 * @param array $payload The payload to send with the request (for POST, PUT, PATCH).
 * @throws Exception If an error occurs during the request.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */
function unauthenticatedUserAssertion(string $method, string $url, array $payload = []): \Illuminate\Testing\TestResponse {
    try {
        $response = match (strtolower($method)) {
            'get' => test()->getJson($url),
            'post' => test()->postJson($url, $payload),
            'put' => test()->putJson($url, $payload),
            'patch' => test()->patchJson($url, $payload),
            'delete' => test()->deleteJson($url),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        $responseData = $response->getData(true);

        expect($response->getStatusCode())->toBeIn([401, 500, 422]);
        expect($responseData)->toHaveKey('message');
        expect($responseData)->not->toHaveKey('data');

        return $response;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}


/**
 * Helper to test invalid payload
 * @param string $method The HTTP method (POST, PUT, PATCH).
 * @param string $url The endpoint URL to test.
 * @param array $payload The invalid payload to send with the request.
 * @throws Exception If an error occurs during the request.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */
function invalidPayloadAssertion(string $method, string $url, array $payload = []): \Illuminate\Testing\TestResponse {
    try {
        if (!in_array(strtolower($method), ['post', 'put', 'patch', 'delete', 'get'])) {
            throw new InvalidArgumentException("Invalid method [$method] for invalid payload assertion. Use POST, PUT, PATCH, or DELETE.");
        }

        $response = match (strtolower($method)) {
            'post' => test()->postJson($url, $payload),
            'put' => test()->putJson($url, $payload),
            'patch' => test()->patchJson($url, $payload),
            'delete' => test()->deleteJson($url, $payload),
            'get' => test()->getJson($url, $payload),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        $responseData = $response->getData(true);
        expect($response->getStatusCode())->toBeIn([422, 409, 400, 500]);
        expect($responseData)->not->toHaveKey('data');
        expect(
            array_key_exists('message', $responseData) ||
                array_key_exists('errors', $responseData) ||
                array_key_exists('error', $responseData)
        )->toBeTrue();

        return $response;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}


/**
 * Helper to test not found response
 * @param string $method The HTTP method (GET, PUT, PATCH, DELETE).
 * @param string $url The endpoint URL to test.
 * @throws Exception If an error occurs during the request.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */
function notFoundAssertion(string $method, string $url, string $connection, array $payload = []): \Illuminate\Testing\TestResponse {
    try {
        if (!in_array(strtolower($method), ['get', 'put', 'patch', 'delete', 'post'])) {
            throw new InvalidArgumentException("Invalid method [$method] for not found assertion. Use GET, PUT, PATCH, or DELETE.");
        }

        $response = match (strtolower($method)) {
            'get' => test()->getJson($url),
            'put' => test()->putJson($url),
            'patch' => test()->patchJson($url),
            'delete' => test()->deleteJson($url),
            'post' => test()->postJson($url, $payload),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        $responseData = $response->getData(true);

        expect($response->getStatusCode())->toBeIn([404, 422, 403]);
        expect($responseData)->not->toHaveKey('data');

        // Safely rollback DB if the controller does not rollback safely
        if (DB::connection($connection)->transactionLevel() > 0) {
            DB::connection($connection)->rollBack();
        }

        return $response;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}

/**
 * Helper to test metuated payload
 * @param string $method The HTTP method (POST, PUT, PATCH).
 * @param string $url The endpoint URL to test.
 * @param array $payload The payload to send with the request (for POST, PUT, PATCH).
 * @throws Exception If an error occurs during the request.
 * @return \Illuminate\Testing\TestResponse The response from the request.
 */
function metuatedPayloadAssertion(string $method, string $url, array $payload, string $connection): \Illuminate\Testing\TestResponse {
    try {
        if (!in_array(strtolower($method), ['post', 'put', 'patch', 'delete', 'get'])) {
            throw new InvalidArgumentException("Invalid method [$method] for metuated payload assertion. Use POST, PUT, PATCH, or DELETE.");
        }

        $response = match (strtolower($method)) {
            'post' => test()->postJson($url, $payload),
            'put' => test()->putJson($url, $payload),
            'patch' => test()->patchJson($url, $payload),
            'delete' => test()->deleteJson($url, $payload),
            'get' => test()->getJson($url, $payload),
            default => throw new InvalidArgumentException("Unsupported method [$method]"),
        };

        expect($response->getStatusCode())->not->toBeIn([200, 201]);

        // Safely rollback DB if the controller does not rollback safely
        if (DB::connection($connection)->transactionLevel() > 0) {
            DB::connection($connection)->rollBack();
        }

        return $response;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}


/**
 * Reset the auto-increment sequence for the given model.
 *
 * @param string $modelClass The model class (e.g., User::class).
 */
function resetSequence(string $modelClass, string $connection): void {
    $table = $modelClass::getTableName();

    try {
        DB::connection($connection)->statement(
            "SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), coalesce(max(id), 0) + 1, false) FROM \"{$table}\""
        );
    } catch (\Throwable $e) {
        throw new \Exception("Error resetting sequence for table {$table}: " . $e->getMessage(), 0, $e);
    }
}
