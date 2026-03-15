<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests;

use Dgtlss\Atlas\Tests\Fixtures\CountingJsonTransformer;
use Dgtlss\Atlas\Tests\Fixtures\CountingMarkdownTransformer;

class AtlasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CountingMarkdownTransformer::$count = 0;
        CountingJsonTransformer::$count = 0;
    }

    public function test_opted_in_route_returns_html_by_default(): void
    {
        $response = $this->get('/page');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertDontSee('title:');
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_opted_in_route_returns_markdown_from_accept_header(): void
    {
        $response = $this->get('/page', ['Accept' => 'text/markdown']);

        $response->assertOk();
        $response->assertHeader('X-Atlas-Format', 'markdown');
        $response->assertHeader('X-Atlas-Source-URL', 'http://localhost/page');
        $response->assertSee('title: "Atlas Docs"', false);
        $response->assertSee('# Atlas Docs', false);
        $response->assertSee('## Why Atlas?', false);
    }

    public function test_opted_in_route_returns_json_from_accept_header(): void
    {
        $response = $this->get('/page', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('X-Atlas-Format', 'json');
        $response->assertJsonPath('title', 'Atlas Docs');
        $response->assertJsonPath('url', 'http://localhost/page');
        $response->assertJsonPath('metadata.section', 'docs');
        $response->assertJsonPath('metadata.surface', 'atlas');
        $response->assertJsonPath('headings.0.text', 'Atlas Docs');
    }

    public function test_non_opted_in_route_ignores_negotiation(): void
    {
        $response = $this->get('/plain', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_auth_protected_routes_remain_protected_in_transformed_modes(): void
    {
        $forbidden = $this->get('/protected', ['Accept' => 'application/json']);
        $authorized = $this->get('/protected', ['Accept' => 'application/json', 'X-Authorized' => 'yes']);

        $forbidden->assertForbidden();
        $authorized->assertOk();
        $authorized->assertHeader('X-Atlas-Format', 'json');
    }

    public function test_query_parameter_override_works_when_enabled(): void
    {
        $response = $this->get('/query-format?format=json&ref=docs', ['Accept' => 'text/markdown']);

        $response->assertOk();
        $response->assertHeader('X-Atlas-Format', 'json');
        $response->assertHeader('X-Atlas-Source-URL', 'http://localhost/query-format?ref=docs');
        $response->assertHeader('Link', '<http://localhost/query-format?ref=docs>; rel="canonical"');
        $response->assertJsonPath('url', 'http://localhost/query-format?ref=docs');
    }

    public function test_query_parameter_can_be_disabled_per_route(): void
    {
        $response = $this->get('/query-disabled?atlas=json', ['Accept' => 'text/markdown']);

        $response->assertOk();
        $response->assertHeader('X-Atlas-Format', 'markdown');
        $response->assertSee('# Atlas Docs', false);
    }

    public function test_route_level_format_restrictions_are_respected(): void
    {
        $json = $this->get('/markdown-only', ['Accept' => 'application/json']);
        $markdown = $this->get('/markdown-only', ['Accept' => 'text/markdown']);

        $json->assertOk();
        $json->assertSee('Atlas Docs', false);
        $this->assertFalse($json->headers->has('X-Atlas-Format'));

        $markdown->assertOk();
        $markdown->assertHeader('X-Atlas-Format', 'markdown');
    }

    public function test_presenter_output_overrides_generic_transformation(): void
    {
        $markdown = $this->get('/presented', ['Accept' => 'text/markdown']);
        $json = $this->get('/presented', ['Accept' => 'application/json']);

        $markdown->assertOk();
        $markdown->assertSee('# Presented', false);
        $markdown->assertSee('Custom markdown for http://localhost/presented', false);

        $json->assertOk();
        $json->assertJsonPath('title', 'Presented title');
        $json->assertJsonPath('metadata.presenter', 'custom');
    }

    public function test_cache_stores_distinct_entries_per_format(): void
    {
        $firstMarkdown = $this->get('/cached', ['Accept' => 'text/markdown']);
        $secondMarkdown = $this->get('/cached', ['Accept' => 'text/markdown']);
        $firstJson = $this->get('/cached', ['Accept' => 'application/json']);
        $secondJson = $this->get('/cached', ['Accept' => 'application/json']);

        $firstMarkdown->assertOk();
        $secondMarkdown->assertOk();
        $firstJson->assertOk();
        $secondJson->assertOk();

        $this->assertSame(1, CountingMarkdownTransformer::$count);
        $this->assertSame(1, CountingJsonTransformer::$count);
    }

    public function test_global_disable_mode_bypasses_transformation(): void
    {
        $this->app['config']->set('atlas.enabled', false);

        $response = $this->get('/page', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_disallowed_environment_bypasses_transformation(): void
    {
        $this->app['config']->set('atlas.allowed_environments', ['production']);
        $this->app->detectEnvironment(fn (): string => 'local');

        $response = $this->get('/page', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_headers_and_cookies_are_preserved_when_transforming(): void
    {
        $response = $this->get('/headers-cookies', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('X-Atlas-Format', 'json');
        $response->assertHeader('X-Atlas-Test', 'preserved');
        $this->assertSame('atlas_session', $response->headers->getCookies()[0]->getName());
        $this->assertSame('abc123', $response->headers->getCookies()[0]->getValue());
    }

    public function test_non_html_successful_responses_are_not_transformed_without_presenter(): void
    {
        $response = $this->get('/non-html', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertExactJson(['hello' => 'world']);
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_redirects_and_streams_fall_back_to_original_responses(): void
    {
        $redirect = $this->get('/redirecting', ['Accept' => 'text/markdown']);
        $stream = $this->get('/streamed', ['Accept' => 'application/json']);

        $redirect->assertRedirect('/page');
        $stream->assertOk();
        $this->assertFalse($redirect->headers->has('X-Atlas-Format'));
        $this->assertFalse($stream->headers->has('X-Atlas-Format'));
    }

    public function test_invalid_format_request_falls_back_to_html(): void
    {
        $response = $this->get('/page?atlas=xml&ref=docs');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertFalse($response->headers->has('X-Atlas-Format'));
    }

    public function test_status_codes_and_headers_are_preserved_when_transforming(): void
    {
        $response = $this->get('/created', ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $response->assertHeader('X-Atlas-Format', 'json');
        $response->assertHeader('Link', '<http://localhost/created>; rel="canonical"');
        $response->assertJsonPath('url', 'http://localhost/created');
    }
}
