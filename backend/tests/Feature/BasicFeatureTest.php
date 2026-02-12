<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicFeatureTest extends TestCase
{
    public function test_homepage_loads()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
