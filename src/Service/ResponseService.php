<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ResponseService
{
    public function successfulJsonResponse($data = null): JsonResponse
    {
        if (null === $data) {
            return new JsonResponse([
                'success' => true
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data
        ]);
    }

    public function invalidJsonResponse($data = null): JsonResponse
    {
        if (null === $data) {
            return new JsonResponse([
                'success' => true
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data
        ], JsonResponse::HTTP_BAD_REQUEST);
    }
}
