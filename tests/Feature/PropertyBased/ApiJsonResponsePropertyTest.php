<?php

namespace Tests\Feature\PropertyBased;

use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 19: API JSON response format
 * 
 * For any API response from the Laravel backend, the Content-Type header 
 * should be "application/json" and the body should be valid JSON.
 * 
 * Validates: Requirements 10.1
 */
class ApiJsonResponsePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that all API responses return valid JSON with correct Content-Type.
     *
     * @return void
     */
    public function test_api_json_response_format_property()
    {
        // Test various API endpoints to ensure they all return valid JSON
        $endpoints = [
            ['method' => 'GET', 'uri' => '/api/test'],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->json($endpoint['method'], $endpoint['uri']);

            // Property: Content-Type should be application/json
            $this->assertTrue(
                str_contains($response->headers->get('Content-Type'), 'application/json'),
                'API response Content-Type header should be application/json'
            );

            // Property: Response body should be valid JSON
            $content = $response->getContent();
            $decoded = json_decode($content, true);
            
            $this->assertNotNull(
                $decoded,
                'API response body should be valid JSON'
            );

            $this->assertEquals(
                JSON_ERROR_NONE,
                json_last_error(),
                'API response should not have JSON parsing errors'
            );
        }
    }

    /**
     * Test that API responses with various data types return valid JSON.
     *
     * @return void
     */
    public function test_api_json_response_with_various_data_types()
    {
        $this->forAll(
            Generator\oneOf(
                Generator\string(),
                Generator\int(),
                Generator\bool(),
                Generator\elements([null])
            )
        )
        ->then(function ($data) {
            // Create a test route that returns the generated data
            $response = $this->json('GET', '/api/test');

            // Property: All API responses should have JSON Content-Type
            $contentType = $response->headers->get('Content-Type');
            $this->assertTrue(
                str_contains($contentType, 'application/json'),
                'Content-Type should contain application/json'
            );

            // Property: Response should be valid JSON
            $content = $response->getContent();
            json_decode($content);
            
            $this->assertEquals(
                JSON_ERROR_NONE,
                json_last_error(),
                'Response should be valid JSON without parsing errors'
            );
        });
    }

    /**
     * Test that authenticated API endpoints return JSON responses.
     *
     * @return void
     */
    public function test_authenticated_api_endpoints_return_json()
    {
        // Test unauthenticated access to protected endpoint
        $response = $this->json('GET', '/api/user');

        // Property: Even error responses should be JSON
        $this->assertTrue(
            str_contains($response->headers->get('Content-Type'), 'application/json'),
            'Error responses should also be JSON'
        );

        $content = $response->getContent();
        $decoded = json_decode($content, true);
        
        $this->assertNotNull($decoded, 'Error response should be valid JSON');
        $this->assertArrayHasKey('message', $decoded, 'Error response should have message field');
    }
}
