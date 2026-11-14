<?php
namespace Tests\Feature;
use Tests\TestCase;
class RetiredGeekPasteApiTest extends TestCase
{
    public function testLegacyCallbacksAndProfilesAreNoLongerRouted(): void
    {
        foreach (['/api/geekpaste','/api/geekpaste/recheck','/api/geekpaste/recheck/status','/api/geekpaste/integrity','/api/geekpaste/executions/test/result'] as $path)
            $this->postJson($path,['token'=>'obsolete'])->assertNotFound();
        $this->getJson('/api/geekpaste/users/42')->assertNotFound();
    }
}
