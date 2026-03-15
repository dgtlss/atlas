<?php

declare(strict_types=1);

namespace Dgtlss\Atlas;

use Dgtlss\Atlas\Contracts\AtlasPresenter;
use Dgtlss\Atlas\Contracts\JsonTransformer;
use Dgtlss\Atlas\Contracts\MarkdownTransformer;
use Dgtlss\Atlas\Support\AtlasContext;
use Dgtlss\Atlas\Support\Format;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AtlasManager
{
    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected CacheManager $cache,
    ) {
    }

    public function enabled(): bool
    {
        if (! (bool) $this->config->get('atlas.enabled', true)) {
            return false;
        }

        $allowed = $this->config->get('atlas.allowed_environments');

        if ($allowed === null) {
            return true;
        }

        $environment = method_exists($this->container, 'environment')
            ? $this->container->environment()
            : null;

        return in_array($environment, (array) $allowed, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsFor(?Route $route): array
    {
        $routeOptions = $route ? (array) ($route->defaults['_atlas'] ?? []) : [];

        $options = array_replace_recursive([
            'formats' => $this->config->get('atlas.default_formats', Format::all()),
            'presenter' => null,
            'cache' => $this->config->get('atlas.cache', []),
            'metadata' => [],
            'query_parameter' => $this->config->get('atlas.negotiation.query_parameter', 'atlas'),
            'markdown_transformer' => $this->config->get('atlas.transformers.markdown'),
            'json_transformer' => $this->config->get('atlas.transformers.json'),
        ], $routeOptions);

        if (array_key_exists('formats', $routeOptions)) {
            $options['formats'] = $routeOptions['formats'];
        }

        if (isset($routeOptions['cache']) && is_array($routeOptions['cache']) && array_key_exists('vary', $routeOptions['cache'])) {
            $options['cache']['vary'] = $routeOptions['cache']['vary'];
        }

        $options['formats'] = array_values(array_intersect(
            array_map(static fn (mixed $format): string => strtolower((string) $format), (array) $options['formats']),
            Format::all()
        ));

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function shouldRun(Request $request, array $options): bool
    {
        return $this->enabled()
            && $request->route() !== null
            && $options['formats'] !== [];
            // Route middleware opt-in guarantees the route was marked for Atlas.
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function negotiateFormat(Request $request, array $options): ?string
    {
        $queryParameter = $this->queryParameterName($options);

        if ($queryParameter !== null && $this->queryParametersEnabled($options)) {
            $requested = strtolower((string) $request->query($queryParameter, ''));

            if (in_array($requested, $options['formats'], true)) {
                return $requested;
            }
        }

        if (! (bool) $this->config->get('atlas.negotiation.accept_header', true)) {
            return null;
        }

        $acceptable = array_values(array_filter(
            array_map(static fn (string $format): ?string => Format::acceptValue($format), $options['formats'])
        ));

        if ($acceptable === []) {
            return null;
        }

        $requestedTypes = array_map('strtolower', $request->getAcceptableContentTypes());
        $explicitTypes = array_intersect($requestedTypes, $acceptable);

        if ($explicitTypes === []) {
            return null;
        }

        $preferred = $request->prefers($acceptable);

        return match ($preferred) {
            'text/markdown' => Format::MARKDOWN,
            'application/json' => Format::JSON,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function transformable(Response $response, array $options): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        if ($response instanceof RedirectResponse || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($options['presenter']) {
            return true;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType !== '' && ! Str::contains(strtolower($contentType), 'text/html')) {
            return false;
        }

        return trim((string) $response->getContent()) !== '';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function makeResponse(Request $request, Response $original, string $format, array $options): Response
    {
        $route = $request->route();
        $canonicalUrl = $this->canonicalUrl($request, $options);
        $metadata = $this->metadataFor($request, $route, $options);
        $context = new AtlasContext($request, $original, $route, $format, $options, $metadata, $canonicalUrl);

        $payload = $this->cachedPayload($request, $context, $options, $format);

        $response = match ($format) {
            Format::MARKDOWN => response($this->markdownBody($payload, $context), $original->getStatusCode()),
            Format::JSON => new JsonResponse($payload, $original->getStatusCode(), [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => $original,
        };

        if (! $response instanceof Response) {
            return $original;
        }

        $this->copyHeaders($original, $response);
        $this->applyAtlasHeaders($response, $canonicalUrl, $format);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function markdownBody(array $payload, AtlasContext $context): string
    {
        if (! (bool) $this->config->get('atlas.metadata.include_frontmatter', true)) {
            return (string) $payload['content'];
        }

        $frontmatter = [
            'title' => $payload['title'] ?? $context->title(),
            'url' => $payload['url'] ?? $context->url(),
            'metadata' => $payload['metadata'] ?? $context->metadata(),
        ];

        $lines = ["---"];

        foreach ($frontmatter as $key => $value) {
            $lines[] = $key . ': ' . $this->stringifyFrontmatterValue($value);
        }

        $lines[] = "---";
        $lines[] = '';
        $lines[] = (string) $payload['content'];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function cachedPayload(Request $request, AtlasContext $context, array $options, string $format): array
    {
        $cache = $this->normalizeCacheOptions($options['cache'] ?? false);

        if (! ($cache['enabled'] ?? false)) {
            return $this->payloadFor($context, $format);
        }

        $store = $cache['store'] ? $this->cache->store((string) $cache['store']) : $this->cache->store();
        $key = $this->cacheKey($request, $context, $format, $cache);

        return $store->remember($key, (int) $cache['ttl'], fn (): array => $this->payloadFor($context, $format));
    }

    /**
     * @param  array<string, mixed>  $cache
     */
    protected function cacheKey(Request $request, AtlasContext $context, string $format, array $cache): string
    {
        $varyData = [];

        foreach ((array) ($cache['vary'] ?? []) as $dimension) {
            $varyData[$dimension] = match ($dimension) {
                'locale' => $this->container->make('translator')->getLocale(),
                'user' => $request->user()?->getAuthIdentifier(),
                'path' => $request->path(),
                default => $request->headers->get((string) $dimension),
            };
        }

        return 'atlas:' . sha1(json_encode([
            'route' => $context->route()?->getName() ?? $context->route()?->uri(),
            'url' => $context->url(),
            'format' => $format,
            'presenter' => $context->options()['presenter'] ?? null,
            'markdown_transformer' => $context->options()['markdown_transformer'] ?? null,
            'json_transformer' => $context->options()['json_transformer'] ?? null,
            'vary' => $varyData,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFor(AtlasContext $context, string $format): array
    {
        $presenter = $this->presenter($context->options());

        if ($presenter instanceof AtlasPresenter) {
            $payload = $format === Format::MARKDOWN
                ? $this->normalizeMarkdownPayload($presenter->toMarkdown($context), $context)
                : $this->normalizeJsonPayload($presenter->toJson($context), $context);

            return $payload;
        }

        if ($format === Format::MARKDOWN) {
            /** @var MarkdownTransformer $transformer */
            $transformer = $this->container->make($context->options()['markdown_transformer']);

            return $this->normalizeMarkdownPayload($transformer->transform($context->html(), $context), $context);
        }

        /** @var JsonTransformer $transformer */
        $transformer = $this->container->make($context->options()['json_transformer']);

        return $this->normalizeJsonPayload($transformer->transform($context->html(), $context), $context);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function presenter(array $options): ?AtlasPresenter
    {
        $presenter = $options['presenter'] ?? null;

        if (! is_string($presenter) || $presenter === '') {
            return null;
        }

        $instance = $this->container->make($presenter);

        return $instance instanceof AtlasPresenter ? $instance : null;
    }

    /**
     * @param  string|bool|array<string, mixed>|int|null  $cache
     * @return array<string, mixed>
     */
    protected function normalizeCacheOptions(string|bool|array|int|null $cache): array
    {
        if ($cache === false || $cache === null) {
            return ['enabled' => false];
        }

        if ($cache === true) {
            return array_replace((array) $this->config->get('atlas.cache', []), ['enabled' => true]);
        }

        if (is_int($cache)) {
            return array_replace((array) $this->config->get('atlas.cache', []), [
                'enabled' => true,
                'ttl' => $cache,
            ]);
        }

        return array_replace(
            (array) $this->config->get('atlas.cache', []),
            $cache,
            ['enabled' => (bool) Arr::get($cache, 'enabled', true)]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeJsonPayload(array $payload, AtlasContext $context): array
    {
        return [
            'title' => $payload['title'] ?? $context->title(),
            'url' => $payload['url'] ?? $context->url(),
            'content' => $payload['content'] ?? '',
            'summary' => $payload['summary'] ?? $context->document()->summary(),
            'headings' => $payload['headings'] ?? $context->document()->headings(),
            'metadata' => array_replace($context->metadata(), (array) ($payload['metadata'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeMarkdownPayload(string $markdown, AtlasContext $context): array
    {
        return [
            'title' => $context->title(),
            'url' => $context->url(),
            'content' => $markdown,
            'summary' => $context->document()->summary(),
            'headings' => $context->document()->headings(),
            'metadata' => $context->metadata(),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function metadataFor(Request $request, ?Route $route, array $options): array
    {
        return array_filter(array_replace(
            (array) $this->config->get('atlas.metadata.defaults', []),
            (array) ($options['metadata'] ?? []),
            [
                'route' => $route?->getName(),
                'method' => $request->method(),
            ]
        ), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function canonicalUrl(Request $request, array $options): string
    {
        $query = $request->query();
        $parameter = $this->queryParameterName($options);

        if ($parameter !== null) {
            unset($query[$parameter]);
        }

        $url = $request->url();

        if ($query === []) {
            return $url;
        }

        return $url . '?' . Arr::query($query);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function queryParametersEnabled(array $options): bool
    {
        if ($options['query_parameter'] === false) {
            return false;
        }

        return (bool) $this->config->get('atlas.negotiation.allow_query_parameter', true);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function queryParameterName(array $options): ?string
    {
        if ($options['query_parameter'] === false) {
            return null;
        }

        $value = $options['query_parameter'] ?? $this->config->get('atlas.negotiation.query_parameter', 'atlas');

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function stringifyFrontmatterValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    }

    protected function copyHeaders(Response $original, Response $transformed): void
    {
        foreach ($original->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            if (in_array(strtolower($name), ['content-type', 'content-length'], true)) {
                continue;
            }

            $transformed->headers->set($name, $values, true);
        }

        foreach ($original->headers->getCookies() as $cookie) {
            $transformed->headers->setCookie($cookie);
        }
    }

    protected function applyAtlasHeaders(Response $response, string $canonicalUrl, string $format): void
    {
        $response->headers->set('Content-Type', (string) Format::mimeType($format));
        $response->headers->set('X-Atlas-Format', $format);

        if ((bool) $this->config->get('atlas.headers.source_url', true)) {
            $response->headers->set('X-Atlas-Source-URL', $canonicalUrl);
        }

        if ((bool) $this->config->get('atlas.headers.canonical', true)) {
            $response->headers->set('Link', '<' . $canonicalUrl . '>; rel="canonical"', false);
        }
    }
}
