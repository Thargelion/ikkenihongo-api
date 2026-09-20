<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_swagger_ui_and_spec_are_publicly_available(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('swagger-ui')
            ->assertSee('/openapi.yaml');

        $this->get('/openapi.yaml')
            ->assertOk()
            ->assertHeader('content-type', 'application/yaml')
            ->assertStreamedContent(file_get_contents(base_path('openapi.yaml')));
    }
}
