<?php
declare(strict_types=1);
namespace App\Api;

class ApiError extends \Exception
{
    public int $status;
    public string $errorCode;

    public function __construct(int $status, string $code, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $code;
    }
}
