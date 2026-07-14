<?php

namespace Helper\Response;

use Illuminate\Http\JsonResponse;
use Translation\Message;

class Response {
    /**
     * Returns a 200 response
     *
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _200($data): JsonResponse {
        return response()->json($data, 200);
    }

    /**
     * Returns a 201 response for creation
     *
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _201($data): JsonResponse {
        return response()->json($data, 201);
    }

    /**
     * Returns a 204 response for no content
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _204(): JsonResponse {
        return response()->json(null, 204);
    }

    /**
     * Returns a 401 response
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _401($message = null): JsonResponse {
        $response = ['message' => Message::get('unauthorized')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 401);
    }

    /**
     * Returns a 404 response for not found
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _404($message = null): JsonResponse {
        $response = ['message' => Message::get('not_found')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 404);
    }

    /**
     * Returns a 422 validation error
     * with messages
     *
     * @param string|null $message
     * @param array|null $errors
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _422($message = null, $errors = null): JsonResponse {
        $responseData = [];
        if ($errors) {
            $responseData['errors'] = $errors;
        }
        if ($message) {
            $responseData['message'] = $message;
        }

        return response()->json($responseData, 422);
    }

    /**
     * Returns a 500 internal server error
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _500($message = null): JsonResponse {
        $response = ['message' => Message::get('internal_server_error')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 500);
    }

    /**
     * Returns a 403 forbidden error
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _403($message = null): JsonResponse {
        $response = ['message' => Message::get('forbidden')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 403);
    }

    /**
     * Returns a 400 bad request error
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _400($message = null): JsonResponse {
        $response = ['message' => Message::get('bad_request')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 400);
    }


    /**
     * Returns a 207 multi-status response
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _207($data): JsonResponse {
        return response()->json($data, 207);
    }

    /**
     * Returns a _409 too many requests response
     */
    public static function _409($message = null): JsonResponse {
        $response = ['message' => Message::get('too_many_requests')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 409);
    }

    /**
     * Returns a 402 payment required response (tenant subscription expired /
     * upgrade needed).
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _402($message = null): JsonResponse {
        $response = ['message' => Message::get('payment_required')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 402);
    }

    /**
     * Returns a 429 too many requests response (usage limit reached).
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function _429($message = null): JsonResponse {
        $response = ['message' => Message::get('too_many_requests')];
        if (is_string($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 429);
    }
}
