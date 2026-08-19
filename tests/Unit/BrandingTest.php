<?php

namespace Tests\Unit;

use App\Support\Branding;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    private array $originalBranding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBranding = config('branding');
    }

    protected function tearDown(): void
    {
        config(['branding' => $this->originalBranding]);
        parent::tearDown();
    }

    public function test_defaults_preserve_the_existing_brand(): void
    {
        config(['branding.logo_url' => null, 'branding.colors' => [], 'branding.dark_colors' => []]);

        $this->assertSame(url('images/icons/icons8-idea-64.png'), Branding::logoUrl());
        $this->assertSame('#2d9ccc', Branding::palette()['primary']);
        $this->assertSame('#17a2b8', Branding::palette()['info']);
        $this->assertSame('#28a745', Branding::palette()['success']);
        $this->assertSame('#5bbce0', Branding::palette('dark')['primary']);
        $this->assertSame('#28a745', Branding::buttons()['success']);
        $this->assertSame('#17a2b8', Branding::buttons()['info']);
        $this->assertSame('#4ade80', Branding::buttons('dark')['success']);
        $this->assertFalse(Branding::hasCustomPalette());
    }

    public function test_custom_brand_palette_is_normalized_and_adapted(): void
    {
        config([
            'branding.colors.primary' => '#397698',
            'branding.colors.secondary' => '#EACF72',
            'branding.colors.accent' => '#FFA117',
            'branding.colors.success' => '#28A745',
            'branding.colors.warning' => '#EB4F27',
            'branding.colors.danger' => '#C92325',
            'branding.dark_colors' => [],
        ]);

        $light = Branding::palette();

        $this->assertSame('#397698', $light['primary']);
        $this->assertSame('#eacf72', $light['secondary']);
        $this->assertSame('#ffa117', $light['accent']);
        $this->assertSame('#5d8fab', $light['info']);
        $this->assertSame('#2f617d', $light['primary_hover']);
        $this->assertSame('#e7eff3', $light['primary_light']);
        $this->assertNotSame($light['primary'], Branding::palette('dark')['primary']);
        $this->assertSame('#81a8bd', Branding::palette('dark')['info']);
        $this->assertSame('#81a8bd', Branding::buttons('dark')['info']);
        $this->assertSame('#57ba6e', Branding::buttons('dark')['success']);
        $this->assertSame('#28a745', Branding::buttons()['success']);
        $this->assertTrue(Branding::hasCustomPalette());
    }

    public function test_invalid_colors_fall_back_without_entering_css(): void
    {
        config([
            'branding.colors.primary' => 'red; body { display:none }',
            'branding.colors.danger' => '#xyzxyz',
        ]);

        $this->assertSame('#2d9ccc', Branding::palette()['primary']);
        $this->assertSame('#dc3545', Branding::palette()['danger']);
    }

    public function test_logo_accepts_remote_urls_and_public_paths(): void
    {
        config(['branding.logo_url' => 'https://example.com/brand.png']);
        $this->assertSame('https://example.com/brand.png', Branding::logoUrl());

        config(['branding.logo_url' => '/images/custom-brand.png']);
        $this->assertSame(url('images/custom-brand.png'), Branding::logoUrl());
    }
}
