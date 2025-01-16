<?php

namespace Lucid\Domains\Http\Actions;

use Illuminate\Routing\ResponseFactory;
use Lucid\Units\Action;

class RespondWithJsonAction extends Action
{
    protected mixed $content;

    protected int $status;

    protected array $headers;

    protected int $options;

    public function __construct($content, $status = 200, array $headers = [], $options = 0)
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
        $this->options = $options;
    }

    public function handle(ResponseFactory $factory)
    {
        $response = [
            'data' => $this->content,
            'status' => $this->status,
        ];

        return $factory->json($response, $this->status, $this->headers, $this->options);
    }
}
