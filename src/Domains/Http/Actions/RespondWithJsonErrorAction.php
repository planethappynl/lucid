<?php

namespace Lucid\Domains\Http\Actions;

use Illuminate\Routing\ResponseFactory;
use Lucid\Units\Action;

class RespondWithJsonErrorAction extends Action
{
    protected array $content;

    protected int $status;

    protected array $headers;

    protected int $options;

    public function __construct($message = 'An error occurred', $code = 400, $status = 400, $headers = [], $options = 0)
    {
        $this->content = [
            'status' => $status,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        $this->status = $status;
        $this->headers = $headers;
        $this->options = $options;
    }

    public function handle(ResponseFactory $response)
    {
        return $response->json($this->content, $this->status, $this->headers, $this->options);
    }
}
