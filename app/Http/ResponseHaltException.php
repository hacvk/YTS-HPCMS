<?php
declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * 用于中止控制器执行并返回 Response
 */
final class ResponseHaltException extends \Error
{
    public function __construct(
        private readonly Response $response
    ) {
        parent::__construct('Response halted');
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
