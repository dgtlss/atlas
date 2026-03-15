<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class AtlasContext
{
    protected ?HtmlDocument $document = null;

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        protected Request $request,
        protected Response $response,
        protected ?Route $route,
        protected string $format,
        protected array $options,
        protected array $metadata,
        protected string $url,
    ) {
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function route(): ?Route
    {
        return $this->route;
    }

    public function format(): string
    {
        return $this->format;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function html(): string
    {
        return (string) $this->response->getContent();
    }

    public function document(): HtmlDocument
    {
        return $this->document ??= new HtmlDocument($this->html());
    }

    public function title(): ?string
    {
        return $this->document()->title();
    }
}
