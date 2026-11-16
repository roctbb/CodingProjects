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

    public function test_defaults_use_the_shared_light_and_dark_palette(): void
    {
        config(['branding.logo_url' => null, 'branding.colors' => [], 'branding.dark_colors' => []]);

        $this->assertSame(url('images/icons/icons8-idea-64.png'), Branding::logoUrl());
        $this->assertSame('#345bd1', Branding::palette()['primary']);
        $this->assertSame('#5877cf', Branding::palette()['info']);
        $this->assertSame('#237052', Branding::palette()['success']);
        $this->assertSame('#a9bdff', Branding::palette('dark')['primary']);
        $this->assertSame('#237052', Branding::buttons()['success']);
        $this->assertSame('#5877cf', Branding::buttons()['info']);
        $this->assertSame('#9bdbbc', Branding::buttons('dark')['success']);
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

        $this->assertSame('#345bd1', Branding::palette()['primary']);
        $this->assertSame('#ad3547', Branding::palette()['danger']);
    }

    public function test_logo_accepts_remote_urls_and_public_paths(): void
    {
        config(['branding.logo_url' => 'https://example.com/brand.png']);
        $this->assertSame('https://example.com/brand.png', Branding::logoUrl());

        config(['branding.logo_url' => '/images/custom-brand.png']);
        $this->assertSame(url('images/custom-brand.png'), Branding::logoUrl());
    }

    public function test_dark_primary_action_has_contrast_against_panels_and_readable_text_for_custom_brands(): void
    {
        $luminance = function (string $hex): float {
            $channels = array_map(function ($channel) {
                $value = hexdec($channel) / 255;
                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));
            return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        };
        $contrast = fn ($a, $b) => (max($luminance($a), $luminance($b)) + 0.05) / (min($luminance($a), $luminance($b)) + 0.05);
        foreach (['#345bd1', '#397698', '#705782', '#000000', '#ffffaa'] as $brand) {
            config(['branding.colors' => ['primary' => $brand], 'branding.dark_colors' => []]);
            $before = \App\Support\Branding::palette('dark');
            $buttons = \App\Support\Branding::buttons('dark');
            $this->assertGreaterThanOrEqual(3, $contrast($buttons['primary'], '#26313f'), $brand);
            $this->assertGreaterThanOrEqual(4.5, $contrast($buttons['primary'], $buttons['primary_text']), $brand);
            $this->assertGreaterThanOrEqual(4.5, $contrast($buttons['primary_hover'], $buttons['primary_text']), $brand);
            $this->assertSame($before, \App\Support\Branding::palette('dark'));
            $this->assertSame($brand, \App\Support\Branding::buttons()['primary']);
        }
    }

    public function test_custom_palette_chooses_readable_button_text_in_both_modes(): void
    {
        config(['branding.colors' => ['primary' => '#705782'], 'branding.dark_colors' => []]);

        $this->assertSame('#ffffff', Branding::buttons()['primary_text']);
        $this->assertSame('#111827', Branding::buttons('dark')['primary_text']);
        $this->assertSame(Branding::palette()['primary'], Branding::palette()['accent']);
        $this->assertSame(Branding::palette('dark')['primary'], Branding::palette('dark')['accent']);
        config(['branding.colors.primary' => '#777777']);
        $this->assertSame('#000000', Branding::buttons()['primary_text']);
    }
}
